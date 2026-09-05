<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 브랜드를 어떻게 알아냈는지 남긴다.
 *
 *   dictionary — 등록된 프랜차이즈 사전에서 찾음. 이름까지 확실하다.
 *   chain      — 사전에 없지만 같은 상호가 여러 행정동에 반복돼 체인으로 본 것.
 *
 * 둘을 섞어 "프랜차이즈"라고 부르면 "입주청소" 같은 일반 명사까지 브랜드가 된다.
 * 리포트에서 구분해 보여 주려고 따로 남긴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('brand_source', 12)->nullable()->after('brand');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('brand_source');
        });
    }
};
