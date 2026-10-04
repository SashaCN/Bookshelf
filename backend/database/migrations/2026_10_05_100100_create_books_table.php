<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('isbn_13', 13)->nullable();
            $table->string('openlibrary_work_key')->nullable()->unique();
            $table->string('cover_url', 500)->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            // Genres / topics from Open Library: input for future recommendations.
            $table->json('subjects')->nullable();
            $table->string('source', 16);
            // Only set for manually entered books, which are visible to their creator alone.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_by');
        });

        Schema::create('author_book', function (Blueprint $table) {
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(0);

            $table->primary(['book_id', 'author_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_book');
        Schema::dropIfExists('books');
    }
};
