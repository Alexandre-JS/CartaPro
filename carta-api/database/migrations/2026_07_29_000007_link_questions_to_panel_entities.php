<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->after('topic_id')->constrained('users')->nullOnDelete();
            $table->foreignId('school_id')->nullable()->after('author_id')->constrained()->nullOnDelete();
            $table->foreignId('sign_id')->nullable()->after('image')->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->after('sign_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('article_id');
            $table->dropConstrainedForeignId('sign_id');
            $table->dropConstrainedForeignId('school_id');
            $table->dropConstrainedForeignId('author_id');
        });
    }
};
