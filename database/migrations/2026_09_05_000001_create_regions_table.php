<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 행정동 마스터. 모든 통계 테이블은 이 테이블의 code(행정동코드)를 기준으로 연결된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique()->comment('행정동코드');
            $table->string('sido_code', 2)->index()->comment('시도코드');
            $table->string('sido_name', 40);
            $table->string('sigungu_code', 5)->index()->comment('시군구코드');
            $table->string('sigungu_name', 40);
            $table->string('dong_name', 60);
            $table->string('full_name', 160)->comment('시도 시군구 행정동 전체 명칭');
            $table->decimal('lat', 10, 7)->nullable()->comment('중심 위도');
            $table->decimal('lng', 10, 7)->nullable()->comment('중심 경도');
            $table->decimal('area_km2', 10, 4)->nullable();
            $table->timestamps();

            $table->index(['sido_name', 'sigungu_name']);
            $table->index(['lat', 'lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
