<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('education_id')->constrained('educations')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order');
            $table->boolean('is_required')->default(true);
            $table->string('contentable_type');
            $table->unsignedBigInteger('contentable_id');
            $table->timestamps();

            $table->index(['education_id', 'sort_order']);
            $table->index(['contentable_type', 'contentable_id']);
        });

        Schema::create('video_contents', function (Blueprint $table) {
            $table->id();
            $table->string('media_driver');
            $table->string('media_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
        });

        Schema::create('document_contents', function (Blueprint $table) {
            $table->id();
            $table->string('media_driver');
            $table->string('media_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });

        Schema::create('link_contents', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2000);
            $table->timestamps();
        });

        Schema::create('content_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('education_assignments')->cascadeOnDelete();
            $table->foreignId('content_id')->constrained('education_contents')->cascadeOnDelete();
            $table->unsignedInteger('max_position_seconds')->default(0);
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_progress');
        Schema::dropIfExists('link_contents');
        Schema::dropIfExists('document_contents');
        Schema::dropIfExists('video_contents');
        Schema::dropIfExists('education_contents');
    }
};
