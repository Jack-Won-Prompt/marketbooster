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
            'unique' => ['region_code', 'base_ym', 'gender', 'age_band'],
            'values' => ['population'],
            'require' => ['region_code', 'base_ym', 'gender', 'age_band'],
        ],
        'households' => [
            'table' => 'households',
            'unique' => ['region_code', 'base_ym', 'housing_type'],
            'values' => ['households'],
            'require' => ['region_code', 'base_ym', 'housing_type'],
        ],
        'workplace_population' => [
            'table' => 'workplace_populations',
            'unique' => ['region_code', 'base_ym', 'gender', 'age_band'],
            'values' => ['population'],
            'require' => ['region_code', 'base_ym', 'gender', 'age_band'],
        ],
        'floating_population' => [
            'table' => 'floating_populations',
            'unique' => ['region_code', 'base_ym', 'day_type', 'time_band', 'gender', 'age_band'],
            'values' => ['population'],
            'require' => ['region_code', 'base_ym', 'day_type', 'time_band', 'gender', 'age_band'],
        ],
        'card_sales' => [
            'table' => 'card_sales',
            'unique' => ['region_code', 'base_ym', 'industry_code', 'day_type', 'time_band'],
            'values' => ['industry_name', 'sales_amount', 'sales_count'],
            'require' => ['region_code', 'base_ym', 'industry_code', 'day_type', 'time_band'],
        ],
        'card_sales_demographics' => [
            'table' => 'card_sales_demographics',
            'unique' => ['region_code', 'base_ym', 'industry_code', 'gender', 'age_band'],
            'values' => ['sales_amount', 'sales_count'],
            'require' => ['region_code', 'base_ym', 'industry_code', 'gender', 'age_band'],
        ],
        'students' => [
            'table' => 'students',
            'unique' => ['region_code', 'base_ym', 'school_type'],
            'values' => ['student_count'],
            'require' => ['region_code', 'base_ym', 'school_type'],
        ],
        'academies' => [
            'table' => 'academies',
            'unique' => ['region_code', 'base_ym', 'category', 'industry_name'],
            'values' => ['academy_count'],
            'require' => ['region_code', 'base_ym', 'category', 'industry_name'],
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

        foreach ($rows as $row) {
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
