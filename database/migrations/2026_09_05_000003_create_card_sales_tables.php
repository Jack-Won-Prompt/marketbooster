<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 카드매출 계열.
 *  - industries              : 업종 마스터
 *  - card_sales              : 업종 × 요일 × 시간대 매출
 *  - card_sales_demographics : 업종 × 성 × 연령 매출
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('group_name', 40)->index()->comment('요식|소매|서비스|의료|교육|여가');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('card_sales', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 12);
            $table->string('base_ym', 6);
            $table->string('industry_code', 20);
            $table->string('industry_name', 80);
            $table->string('day_type', 10)->comment('weekday|weekend');
            $table->string('time_band', 12)->comment('morning|lunch|afternoon|evening|night');
            $table->unsignedBigInteger('sales_amount')->default(0)->comment('매출액(원)');
            $table->unsignedInteger('sales_count')->default(0)->comment('매출건수');
            $table->timestamps();

            $table->unique(
                ['region_code', 'base_ym', 'industry_code', 'day_type', 'time_band'],
                'card_sales_unique'
            );
            $table->index(['region_code', 'base_ym']);
            $table->index(['region_code', 'base_ym', 'industry_code'], 'card_sales_industry_idx');
        });

        Schema::create('card_sales_demographics', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 12);
            $table->string('base_ym', 6);
            $table->string('industry_code', 20);
            $table->char('gender', 1);
            $table->string('age_band', 12);
            $table->unsignedBigInteger('sales_amount')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->timestamps();

            $table->unique(
                ['region_code', 'base_ym', 'industry_code', 'gender', 'age_band'],
                'card_sales_demo_unique'
            );
            $table->index(['region_code', 'base_ym']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_sales_demographics');
        Schema::dropIfExists('card_sales');
        Schema::dropIfExists('industries');
    }
};
