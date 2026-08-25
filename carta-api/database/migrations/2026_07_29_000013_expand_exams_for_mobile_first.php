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
            $table->json('license_categories')->nullable()->after('license_category');
        });
        DB::table('exams')->orderBy('id')->eachById(function ($exam): void {
            DB::table('exams')->where('id', $exam->id)->update([
                'license_categories' => json_encode([$exam->license_category]),
                'pass_score' => (int) ceil($exam->question_count * 0.72),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('exams', fn (Blueprint $table) => $table->dropColumn('license_categories'));
    }
};
