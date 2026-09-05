<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 리포트 마지막 장의 "데이터 출처" 표와 수집 이력.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique()->comment('floating_population 등 내부 데이터 종류');
            $table->string('category', 20)->comment('population|sales|education');
            $table->string('label', 60)->comment('유동인구 등 표시명');
            $table->string('provider', 80)->comment('제공기관');
            $table->string('base_label', 30)->nullable()->comment('2026년 5월 등 기준월 표기');
            $table->string('base_ym', 6)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('data_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index()->comment('데이터 종류');
            $table->string('channel', 20)->comment('api|csv|seed');
            $table->string('base_ym', 6)->nullable();
            $table->string('reference', 255)->nullable()->comment('엔드포인트 또는 파일 경로');
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->string('status', 12)->default('running')->comment('running|success|failed');
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_logs');
        Schema::dropIfExists('data_sources');
    }
};
