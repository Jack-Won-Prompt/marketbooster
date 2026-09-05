<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 점포를 "분야(디저트·식당 …)" 와 "프랜차이즈 브랜드" 로 분류한 결과를 담는다.
 *
 * 매번 상호를 훑어 분류하면 수십만 건에서 느리므로 컬럼에 미리 적어 둔다.
 * 채우는 쪽은 StoreClassifier(= php artisan stores:classify)다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('sector', 24)->nullable()->after('small_name')->comment('분야 코드');
            $table->string('brand', 120)->nullable()->after('sector')->comment('대표 브랜드명');
            $table->boolean('is_franchise')->default(false)->after('brand');

            $table->index(['region_code', 'sector']);
            $table->index(['brand']);
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['region_code', 'sector']);
            $table->dropIndex(['brand']);
            $table->dropColumn(['sector', 'brand', 'is_franchise']);
        });
    }
};
