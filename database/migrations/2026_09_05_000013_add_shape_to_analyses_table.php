<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 지도에 직접 그린 상권(원 · 사각형 · 다각형)을 저장한다.
 *
 * 기존 mode 는 radius(반경) · region(행정동 선택) 두 가지였다.
 * 상권 보고서 화면은 아무 모양이나 그릴 수 있어야 해서 mode='polygon' 을 더하고,
 * 실제 좌표열을 shape_ring 에 담는다. shape_kind 는 화면에 "원형 상권 / 사각형 상권"
 * 이라고 적기 위한 것이고, 계산은 모두 폴리곤 하나로 처리한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->string('shape_kind', 12)->nullable()->after('radius_m')->comment('circle|rectangle|polygon');
            $table->json('shape_ring')->nullable()->after('shape_kind')->comment('[[lng,lat], ...]');
            $table->unsignedBigInteger('area_m2')->nullable()->after('shape_ring');
        });
    }

    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropColumn(['shape_kind', 'shape_ring', 'area_m2']);
        });
    }
};
