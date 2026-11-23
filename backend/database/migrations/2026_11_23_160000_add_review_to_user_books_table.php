<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_books', function (Blueprint $table) {
            $table->text('review')->nullable()->after('rating');
            $table->string('review_visibility', 16)->default('private')->after('review');
            $table->timestamp('reviewed_at')->nullable()->after('review_visibility');
            $table->index(['user_id', 'review_visibility', 'reviewed_at']);
        });

        DB::statement("ALTER TABLE user_books ADD CONSTRAINT user_books_review_visibility_known CHECK (review_visibility IN ('private', 'public'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_books DROP CHECK user_books_review_visibility_known');
        Schema::table('user_books', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'review_visibility', 'reviewed_at']);
            $table->dropColumn(['review', 'review_visibility', 'reviewed_at']);
        });
    }
};
