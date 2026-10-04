<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('want');
            $table->unsignedInteger('current_page')->default(0);
            // A copy of books.page_count that the reader may correct for their own edition.
            $table->unsignedInteger('total_pages')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'book_id']);
            $table->index(['user_id', 'status']);
        });

        // Enforced by the database as well, so no bug in the application can store an impossible state.
        DB::statement('ALTER TABLE user_books ADD CONSTRAINT user_books_rating_range CHECK (rating IS NULL OR rating BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE user_books ADD CONSTRAINT user_books_page_within_total CHECK (total_pages IS NULL OR current_page <= total_pages)');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_books');
    }
};
