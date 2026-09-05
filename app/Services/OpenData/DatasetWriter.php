<?php

namespace App\Services\OpenData;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 정규화된 레코드를 알맞은 통계 테이블에 upsert 한다.
 * API 수집(DatasetSynchronizer)과 CSV 업로드(CsvImporter)가 이 클래스를 공유한다.
 */
class DatasetWriter
{
    /**
     * 데이터 종류별 저장 스펙.
     *   table   : 대상 테이블
     *   unique  : 중복 판정 키 (재수집 시 갱신 기준)
     *   values  : upsert 로 덮어쓸 값 컬럼
     *   require : 이 중 하나라도 비면 건너뛴다
     */
    public const SCHEMA = [
        'regions' => [
            'table' => 'regions',
            'unique' => ['code'],
            'values' => ['sido_code', 'sido_name', 'sigungu_code', 'sigungu_name', 'dong_name', 'full_name', 'lat', 'lng', 'area_km2'],
            'require' => ['code', 'full_name'],
        ],
        'resident_population' => [
            'table' => 'resident_populations',
            'unique' => ['region_code', 'base_ym', 'base_yq', 'gender', 'age_band'],
            'values' => ['population'],
            'require' => ['region_code', 'gender', 'age_band'],
        ],
        'households' => [
            'table' => 'households',
            'unique' => ['region_code', 'base_ym', 'base_yq', 'housing_type'],
            'values' => ['households'],
            'require' => ['region_code', 'housing_type'],
        ],
        'workplace_population' => [
            'table' => 'workplace_populations',
            'unique' => ['region_code', 'base_ym', 'base_yq', 'gender', 'age_band'],
            'values' => ['population'],
            'require' => ['region_code', 'gender', 'age_band'],
        ],
        'floating_population' => [
            'table' => 'floating_populations',
            'unique' => ['region_code', 'base_ym', 'base_yq', 'day_type', 'time_band', 'gender', 'age_band'],
            'values' => ['population'],
            'require' => ['region_code', 'day_type', 'time_band', 'gender', 'age_band'],
        ],
        'card_sales' => [
            'table' => 'card_sales',
            'unique' => ['region_code', 'base_ym', 'base_yq', 'industry_code', 'day_type', 'time_band'],
            'values' => ['industry_name', 'sales_amount', 'sales_count'],
            'require' => ['region_code', 'industry_code', 'day_type', 'time_band'],
        ],
        'card_sales_demographics' => [
            'table' => 'card_sales_demographics',
            'unique' => ['region_code', 'base_ym', 'base_yq', 'industry_code', 'gender', 'age_band'],
            'values' => ['sales_amount', 'sales_count'],
            'require' => ['region_code', 'industry_code', 'gender', 'age_band'],
        ],
        'students' => [
            'table' => 'students',
            'unique' => ['region_code', 'base_ym', 'base_yq', 'school_type'],
            'values' => ['student_count'],
            'require' => ['region_code', 'school_type'],
        ],
        'academies' => [
            'table' => 'academies',
            'unique' => ['region_code', 'base_ym', 'base_yq', 'category', 'industry_name'],
            'values' => ['academy_count'],
            'require' => ['region_code', 'category', 'industry_name'],
        ],
        'apartment_move_ins' => [
            'table' => 'apartment_move_ins',
            'unique' => ['region_code', 'complex_name', 'move_in_ym'],
            'values' => ['households'],
            'require' => ['region_code', 'complex_name', 'move_in_ym'],
        ],
    ];

    public static function types(): array
    {
        return array_keys(self::SCHEMA);
    }

    public static function schemaFor(string $type): array
    {
        if (! isset(self::SCHEMA[$type])) {
            throw new InvalidArgumentException("알 수 없는 데이터 종류입니다: {$type} (사용 가능: ".implode(', ', self::types()).')');
        }

        return self::SCHEMA[$type];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported:int, skipped:int}
     */
    public function write(string $type, array $rows): array
    {
        $schema = self::schemaFor($type);
        $columns = array_merge($schema['unique'], $schema['values']);

        $payload = [];
        $skipped = 0;
        $now = now();

        $needsPeriod = in_array('base_yq', $schema['unique'], true);

        foreach ($rows as $row) {
            if ($needsPeriod) {
                $row = $this->normalizePeriod($row);

                // 월·분기 중 어느 쪽도 없으면 어느 기간의 값인지 알 수 없다.
                if ($row['base_ym'] === '' && $row['base_yq'] === '') {
                    $skipped++;

                    continue;
                }
            }

            if ($this->isIncomplete($row, $schema['require'])) {
                $skipped++;

                continue;
            }

            $record = [];

            foreach ($columns as $column) {
                $record[$column] = $row[$column] ?? null;
            }

            $record['created_at'] = $now;
            $record['updated_at'] = $now;
            $payload[] = $record;
        }

        if ($payload === []) {
            return ['imported' => 0, 'skipped' => $skipped];
        }

        // 같은 배치 안의 중복 키는 마지막 값만 남긴다 (MySQL upsert 가 배치 내 중복에 실패하므로).
        $deduped = [];

        foreach ($payload as $record) {
            $key = implode('|', array_map(fn ($c) => (string) $record[$c], $schema['unique']));
            $deduped[$key] = $record;
        }

        $deduped = array_values($deduped);

        foreach (array_chunk($deduped, 500) as $chunk) {
            DB::table($schema['table'])->upsert($chunk, $schema['unique'], array_merge($schema['values'], ['updated_at']));
        }

        return ['imported' => count($deduped), 'skipped' => $skipped];
    }

    /**
     * 기간 칸을 정돈한다.
     * 쓰지 않는 쪽은 NULL 이 아니라 빈 문자열로 둬야 유일 키가 제 역할을 한다.
     * (MySQL 은 유일 인덱스에서 NULL 을 서로 다른 값으로 보기 때문에 재수집 시 중복이 쌓인다.)
     */
    private function normalizePeriod(array $row): array
    {
        $ym = trim((string) ($row['base_ym'] ?? ''));
        $yq = trim((string) ($row['base_yq'] ?? ''));

        // 5자리 값이 base_ym 으로 들어오면 분기 코드로 본다.
        if ($yq === '' && preg_match('/^\d{4}[1-4]$/', $ym)) {
            $yq = $ym;
            $ym = '';
        }

        $row['base_ym'] = preg_match('/^\d{6}$/', $ym) ? $ym : '';
        $row['base_yq'] = preg_match('/^\d{4}[1-4]$/', $yq) ? $yq : '';

        return $row;
    }

    private function isIncomplete(array $row, array $required): bool
    {
        foreach ($required as $field) {
            if (! isset($row[$field]) || $row[$field] === '' || $row[$field] === null) {
                return true;
            }
        }

        return false;
    }
}
