<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 분기 단위 출처(서울시 상권분석서비스)를 담기 위해 base_yq 를 추가한다.
 *
 * 월 단위 행은 base_ym='202608', base_yq='' 로,
 * 분기 단위 행은 base_ym='',       base_yq='20242' 로 저장된다.
 * 두 칸을 모두 유일 키에 넣어 같은 지역·같은 교차축이라도 주기가 다르면 별도 행이 된다.
 */
return new class extends Migration
{
    /** 테이블 => [기존 유일 인덱스명, 유일 키를 이루는 나머지 컬럼] */
    private const TABLES = [
        'resident_populations' => ['resident_pop_unique', ['region_code', 'gender', 'age_band']],
        'households' => ['households_unique', ['region_code', 'housing_type']],
        'workplace_populations' => ['workplace_pop_unique', ['region_code', 'gender', 'age_band']],
        'floating_populations' => ['floating_pop_unique', ['region_code', 'day_type', 'time_band', 'gender', 'age_band']],
        'card_sales' => ['card_sales_unique', ['region_code', 'industry_code', 'day_type', 'time_band']],
        'card_sales_demographics' => ['card_sales_demo_unique', ['region_code', 'industry_code', 'gender', 'age_band']],
        'students' => ['students_unique', ['region_code', 'school_type']],
        'academies' => ['academies_unique', ['region_code', 'category', 'industry_name']],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => [$uniqueName, $keyColumns]) {
            Schema::table($table, function (Blueprint $blueprint) use ($uniqueName) {
                $blueprint->string('base_yq', 5)->default('')->after('base_ym')->comment('기준연분기 YYYYQ (월 단위면 빈 값)');
                $blueprint->dropUnique($uniqueName);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($table, $uniqueName, $keyColumns) {
                $blueprint->unique($this->uniqueColumns($keyColumns), $uniqueName);
                $blueprint->index(['region_code', 'base_yq'], $table.'_region_yq_idx');
            });
        }

        Schema::table('analyses', function (Blueprint $table) {
            $table->string('base_yq', 5)->default('')->after('base_ym')->comment('기준연분기 YYYYQ (월 단위면 빈 값)');
        });

        Schema::table('data_sources', function (Blueprint $table) {
            $table->string('base_yq', 5)->default('')->after('base_ym');
        });

        Schema::table('data_import_logs', function (Blueprint $table) {
            $table->string('base_yq', 5)->default('')->after('base_ym');
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => [$uniqueName, $keyColumns]) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $uniqueName) {
                $blueprint->dropUnique($uniqueName);
                $blueprint->dropIndex($table.'_region_yq_idx');
                $blueprint->dropColumn('base_yq');
            });

            Schema::table($table, function (Blueprint $blueprint) use ($uniqueName, $keyColumns) {
                $blueprint->unique(array_merge([$keyColumns[0], 'base_ym'], array_slice($keyColumns, 1)), $uniqueName);
            });
        }

        foreach (['analyses', 'data_sources', 'data_import_logs'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('base_yq');
            });
        }
    }

    /** region_code, base_ym, base_yq, 나머지 교차축 순으로 유일 키를 구성한다. */
    private function uniqueColumns(array $keyColumns): array
    {
        return array_merge([$keyColumns[0], 'base_ym', 'base_yq'], array_slice($keyColumns, 1));
    }
};
