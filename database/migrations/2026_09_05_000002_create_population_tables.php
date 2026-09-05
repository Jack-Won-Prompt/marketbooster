<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 인구 계열 통계.
 *  - resident_populations  : 거주인구(추정)  성/연령 교차
 *  - households            : 배후세대       주거유형별
 *  - workplace_populations : 직장인구       성/연령 교차
 *  - floating_populations  : 유동인구       요일/시간대/성/연령 교차
 *  - apartment_move_ins    : 3년 이내 입주예정 아파트
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_populations', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 12);
            $table->string('base_ym', 6)->comment('기준연월 YYYYMM');
            $table->char('gender', 1)->comment('M|F');
            $table->string('age_band', 12)->comment('under10|10s|...|70s_over');
            $table->unsignedInteger('population')->default(0);
            $table->timestamps();

            $table->unique(['region_code', 'base_ym', 'gender', 'age_band'], 'resident_pop_unique');
            $table->index(['region_code', 'base_ym']);
        });

        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 12);
            $table->string('base_ym', 6);
            $table->string('housing_type', 20)->comment('apartment|officetel|villa|detached');
            $table->unsignedInteger('households')->default(0);
            $table->timestamps();

            $table->unique(['region_code', 'base_ym', 'housing_type'], 'households_unique');
            $table->index(['region_code', 'base_ym']);
        });

        Schema::create('workplace_populations', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 12);
            $table->string('base_ym', 6);
            $table->char('gender', 1);
            $table->string('age_band', 12);
            $table->unsignedInteger('population')->default(0);
            $table->timestamps();

            $table->unique(['region_code', 'base_ym', 'gender', 'age_band'], 'workplace_pop_unique');
            $table->index(['region_code', 'base_ym']);
        });

        Schema::create('floating_populations', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 12);
            $table->string('base_ym', 6);
            $table->string('day_type', 10)->comment('weekday|weekend');
            $table->string('time_band', 12)->comment('morning|lunch|afternoon|evening|night');
            $table->char('gender', 1);
            $table->string('age_band', 12);
            $table->unsignedInteger('population')->default(0);
            $table->timestamps();

            $table->unique(
                ['region_code', 'base_ym', 'day_type', 'time_band', 'gender', 'age_band'],
                'floating_pop_unique'
            );
            $table->index(['region_code', 'base_ym']);
            $table->index(['region_code', 'base_ym', 'day_type', 'time_band'], 'floating_pop_band_idx');
        });

        Schema::create('apartment_move_ins', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 12)->index();
            $table->string('complex_name', 120);
            $table->unsignedInteger('households')->default(0);
            $table->string('move_in_ym', 6)->comment('입주예정 연월 YYYYMM');
            $table->timestamps();

            $table->unique(['region_code', 'complex_name', 'move_in_ym'], 'apt_move_in_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apartment_move_ins');
        Schema::dropIfExists('floating_populations');
        Schema::dropIfExists('workplace_populations');
        Schema::dropIfExists('households');
        Schema::dropIfExists('resident_populations');
    }
};
