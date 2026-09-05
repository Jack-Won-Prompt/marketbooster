<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 회원이 요청한 상권분석과 관심지역.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('mode', 10)->default('radius')->comment('radius|region');
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->unsignedInteger('radius_m')->nullable();
            $table->string('address', 200)->nullable();
            $table->json('region_codes')->comment('분석 범위에 포함된 행정동코드 배열');
            $table->string('base_ym', 6);
            $table->string('status', 12)->default('pending')->comment('pending|processing|completed|failed');
            $table->longText('payload')->nullable()->comment('리포트 결과 JSON');
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('favorite_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('region_code', 12);
            $table->string('label', 120)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'region_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_regions');
        Schema::dropIfExists('analyses');
    }
};
