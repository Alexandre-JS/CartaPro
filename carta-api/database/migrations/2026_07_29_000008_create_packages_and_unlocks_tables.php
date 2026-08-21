<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_packages', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('status')->default('published')->index();
            $table->unsignedInteger('question_count')->default(0);
            $table->json('payload');
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('unlocks', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->unique();
            $table->string('plan')->default('completo');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable()->unique();
            $table->timestamp('unlocked_at');
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unlocks');
        Schema::dropIfExists('content_packages');
    }
};
