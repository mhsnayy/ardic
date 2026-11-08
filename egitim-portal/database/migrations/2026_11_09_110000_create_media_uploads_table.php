<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('education_id')->constrained('educations')->cascadeOnDelete();
            $table->string('kind');
            $table->string('media_driver');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('max_bytes');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status')->default('pending'); // pending | uploaded
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');
    }
};
