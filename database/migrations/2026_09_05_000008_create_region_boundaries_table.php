<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 행정동 경계 폴리곤. 반경 분석에서 원과 행정동이 실제로 겹치는 넓이를 구하는 데 쓴다.
 * 경계가 없는 행정동은 "면적이 같은 원" 근사로 자동 대체된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('region_boundaries', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 12)->unique();
            $table->decimal('min_lat', 10, 7);
            $table->decimal('max_lat', 10, 7);
            $table->decimal('min_lng', 10, 7);
            $table->decimal('max_lng', 10, 7);
            $table->longText('rings')->comment('외곽 링 좌표 배열 JSON [[[lng,lat], ...], ...]');
            $table->timestamps();

            $table->index(['min_lat', 'max_lat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_boundaries');
    }
};
