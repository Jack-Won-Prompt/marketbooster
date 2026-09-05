<?php

namespace App\Services\Stores;

use App\Support\Franchises;
use App\Support\StoreSectors;
use Illuminate\Support\Facades\DB;

/**
 * 점포에 분야(sector)와 프랜차이즈 브랜드(brand)를 붙인다.
 *
 * 분야는 업종코드만 보면 되므로 SQL 한 번으로 끝난다.
 * 브랜드는 두 단계로 찾는다.
 *   1) 사전 매칭 — Franchises::BRANDS 에 등록된 표기를 상호에서 찾는다.
 *   2) 데이터 매칭 — 사전에 없더라도 같은 상호가 여러 행정동에 반복되면 체인으로 본다.
 *
 * 2)가 필요한 이유는 지역 체인·소형 프랜차이즈가 사전에 다 있을 수 없기 때문이고,
 * "여러 행정동"을 기준으로 삼는 이유는 한 동네에 같은 상호가 몇 개 있다고 해서
 * 프랜차이즈는 아니기 때문이다.
 */
class StoreClassifier
{
    /** 사전에 없는 상호를 체인으로 볼 최소 행정동 수 */
    public const CHAIN_MIN_DONGS = 3;

    /**
     * @return array{sectors:int, dictionary:int, chains:int, brands:int}
     */
    public function classify(?callable $progress = null): array
    {
        $sectors = $this->fillSectors($progress);
        $dictionary = $this->fillDictionaryBrands($progress);
        $chains = $this->fillChainBrands($progress);

        return [
            'sectors' => $sectors,
            'dictionary' => $dictionary,
            'chains' => $chains,
            'brands' => DB::table('stores')->whereNotNull('brand')->distinct()->count('brand'),
        ];
    }

    /** 업종코드 → 분야. 코드 묶음마다 한 번씩 UPDATE 한다. */
    private function fillSectors(?callable $progress = null): int
    {
        $updated = 0;

        // 좁은 코드가 이기도록 대분류 → 중분류 → 소분류 순서로 덮어쓴다.
        foreach ([
            ['large_code', StoreSectors::BY_LARGE],
            ['middle_code', StoreSectors::BY_MIDDLE],
            ['small_code', StoreSectors::BY_SMALL],
        ] as [$column, $map]) {
            $bySector = [];

            foreach ($map as $code => $sector) {
                $bySector[$sector][] = $code;
            }

            foreach ($bySector as $sector => $codes) {
                $updated += DB::table('stores')->whereIn($column, $codes)->update(['sector' => $sector]);
            }
        }

        $updated += DB::table('stores')->whereNull('sector')->update(['sector' => StoreSectors::UNKNOWN]);

        $progress && $progress('분야 분류를 마쳤습니다.');

        return $updated;
    }

    /**
     * 사전에 등록된 브랜드를 상호에서 찾는다.
     *
     * 패턴마다 LIKE '%...%' 를 돌리면 인덱스를 못 타서 180번 풀스캔이 된다.
     * 대신 상호를 한 번만 읽어 PHP 에서 판별하고, 기본키로 묶어 갱신한다.
     */
    private function fillDictionaryBrands(?callable $progress = null): int
    {
        $byBrand = [];
        $seen = 0;

        DB::table('stores')
            ->select('id', 'name')
            ->whereNull('brand')
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$byBrand, &$seen, $progress) {
                foreach ($rows as $row) {
                    $seen++;
                    $matched = Franchises::match($row->name);

                    if ($matched !== null) {
                        $byBrand[$matched[0]][] = $row->id;
                    }
                }

                $progress && $progress(sprintf('  상호 %s건 확인…', number_format($seen)));
            });

        $updated = 0;

        foreach ($byBrand as $brand => $ids) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                $updated += DB::table('stores')
                    ->whereIn('id', $chunk)
                    ->update(['brand' => $brand, 'is_franchise' => true]);
            }
        }

        $progress && $progress(sprintf(
            '사전 브랜드 %s개 / %s건을 표시했습니다.',
            number_format(count($byBrand)),
            number_format($updated)
        ));

        return $updated;
    }

    /**
     * 사전에 없는 상호 중 여러 행정동에 반복되는 것을 체인으로 본다.
     */
    private function fillChainBrands(?callable $progress = null): int
    {
        $names = DB::table('stores')
            ->select('name', DB::raw('COUNT(DISTINCT region_code) AS dongs'))
            ->whereNull('brand')
            ->whereNotNull('name')
            ->groupBy('name')
            ->havingRaw('COUNT(DISTINCT region_code) >= ?', [self::CHAIN_MIN_DONGS])
            ->pluck('name');

        $usable = $names->filter(fn (string $name) => Franchises::isUsableName($name))->values();
        $updated = 0;

        foreach ($usable->chunk(200) as $chunk) {
            $updated += DB::table('stores')
                ->whereNull('brand')
                ->whereIn('name', $chunk->all())
                ->update(['brand' => DB::raw('name'), 'is_franchise' => true]);
        }

        $progress && $progress(sprintf('데이터로 찾은 체인 %s개 / %s건', number_format($usable->count()), number_format($updated)));

        return $updated;
    }

    /**
     * 상호에 붙어 있는 분류 정보를 한 행에 대해 계산한다.
     * 수집 직후 바로 채워 두려고 StoreCollector 가 쓴다. (체인 판별은 전체를 봐야 하므로 제외)
     *
     * @return array{sector:string, brand:?string, is_franchise:bool}
     */
    public static function forRow(?string $name, ?string $large, ?string $middle, ?string $small): array
    {
        $matched = Franchises::match($name);

        return [
            'sector' => StoreSectors::resolve($large, $middle, $small),
            'brand' => $matched[0] ?? null,
            'is_franchise' => $matched !== null,
        ];
    }

    /** LIKE 안에서 특수 의미를 갖는 문자를 막는다. */
    private function likeEscape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
