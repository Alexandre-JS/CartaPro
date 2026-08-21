<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->unique();
            $table->string('type')->default('teorico');
            $table->json('categories');
            $table->text('statement');
            $table->string('image')->nullable();
            $table->json('options');
            $table->unsignedTinyInteger('correct_index');
            $table->text('explanation');
            $table->unsignedInteger('article_ref')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
