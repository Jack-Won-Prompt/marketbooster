<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 소상공인시장진흥공단 상가(상권)정보 — 개별 점포.
 * 통계가 아니라 점포 목록이라 기준 기간 대신 수집 시각을 남긴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('store_id', 30)->unique()->comment('상가업소번호 bizesId');
            $table->string('name', 200)->comment('상호명');
            $table->string('branch_name', 120)->nullable()->comment('지점명');
            $table->string('region_code', 12)->index()->comment('행정동코드 adongCd');
            $table->string('sido_name', 40)->nullable();
            $table->string('sigungu_name', 40)->nullable();
            $table->string('dong_name', 60)->nullable();
            $table->string('large_code', 10)->nullable()->comment('상권업종 대분류 코드');
            $table->string('large_name', 60)->nullable();
            $table->string('middle_code', 10)->nullable()->comment('중분류 코드');
            $table->string('middle_name', 60)->nullable();
            $table->string('small_code', 12)->nullable()->comment('소분류 코드');
            $table->string('small_name', 80)->nullable();
            $table->string('road_address', 250)->nullable();
            $table->string('lot_address', 250)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->index(['region_code', 'large_code']);
            $table->index(['lat', 'lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
