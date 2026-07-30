<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('publication_status')->default('draft')->index()->after('is_public');
            $table->timestamp('published_at')->nullable()->after('publication_status');
        });

        DB::table('exams')->where('is_public', true)->update(['publication_status' => 'published', 'published_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['publication_status', 'published_at']);
        });
    }
};
