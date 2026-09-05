<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 학생 수 / 학원 수 통계.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 12);
            $table->string('base_ym', 6);
            $table->string('school_type', 20)->comment('daycare|kindergarten|elementary|middle|high|university');
            $table->unsignedInteger('student_count')->default(0);
            $table->timestamps();

            $table->unique(['region_code', 'base_ym', 'school_type'], 'students_unique');
            $table->index(['region_code', 'base_ym']);
        });

        Schema::create('academies', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 12);
            $table->string('base_ym', 6);
            $table->string('category', 20)->comment('education|arts_sports');
            $table->string('industry_name', 60)->comment('수학학원, 피아노/음악학원 등');
            $table->unsignedInteger('academy_count')->default(0);
            $table->timestamps();

            $table->unique(['region_code', 'base_ym', 'category', 'industry_name'], 'academies_unique');
            $table->index(['region_code', 'base_ym']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academies');
        Schema::dropIfExists('students');
    }
};
