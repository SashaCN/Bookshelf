<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The text of a book the reader uploaded: one per library entry. Only the sanitized chapters are kept,
        // never the original file.
        Schema::create('book_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_book_id')->unique()->constrained()->cascadeOnDelete();
            // Repeated from the entry so access checks never need a join.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_name');
            $table->unsignedInteger('chapters_count');
            // Counted in UTF-16 code units, the unit of a JavaScript string index (see App\Services\Epub\Text).
            $table->unsignedInteger('characters_count');
            $table->unsignedInteger('chapter_position')->default(0);
            // How far into the chapter the reader is, and how far into the whole text, both from 0 to 1.
            $table->double('chapter_progress')->default(0);
            $table->double('progress')->default(0);
            $table->timestamp('position_updated_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        DB::statement('ALTER TABLE book_texts ADD CONSTRAINT book_texts_progress_range CHECK (chapter_progress BETWEEN 0 AND 1 AND progress BETWEEN 0 AND 1)');

        Schema::create('book_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_text_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title', 255)->nullable();
            // Sanitized HTML, see App\Services\Epub\HtmlSanitizer.
            $table->longText('content');
            // The length of the text of `content` in UTF-16 code units: the measure of every quote anchor.
            $table->unsignedInteger('characters');

            $table->unique(['book_text_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_chapters');
        Schema::dropIfExists('book_texts');
    }
};
