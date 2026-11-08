<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('education_id')->constrained('educations')->cascadeOnDelete();
            $table->unsignedBigInteger('personnel_id')->index();
            $table->string('personnel_name');
            $table->string('personnel_email');
            $table->string('department_name')->nullable();
            $table->string('status')->default('assigned');
            $table->timestamp('assigned_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['education_id', 'personnel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_assignments');
    }
};
