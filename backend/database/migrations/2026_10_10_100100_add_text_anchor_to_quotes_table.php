<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where in an uploaded text a quote comes from. Offsets are in UTF-16 code units of the text of the chapter.
        // Deleting the text only clears `book_text_id` (the quote stays); the actions that delete a text clear the
        // other three columns too.
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('book_text_id')->nullable()->after('page')->constrained()->nullOnDelete();
            $table->unsignedInteger('chapter_position')->nullable()->after('book_text_id');
            $table->unsignedInteger('start_offset')->nullable()->after('chapter_position');
            $table->unsignedInteger('end_offset')->nullable()->after('start_offset');

            $table->index(['book_text_id', 'chapter_position']);
        });

        // MySQL forbids a CHECK on a column that has a referential action (ON DELETE SET NULL), so `book_text_id`
        // is left out of these and kept consistent by the application.
        DB::statement('ALTER TABLE quotes ADD CONSTRAINT quotes_anchor_complete CHECK ('
            .'(chapter_position IS NULL) = (start_offset IS NULL) AND (start_offset IS NULL) = (end_offset IS NULL))');
        DB::statement('ALTER TABLE quotes ADD CONSTRAINT quotes_anchor_ordered CHECK (end_offset IS NULL OR end_offset > start_offset)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE quotes DROP CHECK quotes_anchor_ordered');
        DB::statement('ALTER TABLE quotes DROP CHECK quotes_anchor_complete');

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropForeign(['book_text_id']);
            $table->dropIndex(['book_text_id', 'chapter_position']);
            $table->dropColumn(['book_text_id', 'chapter_position', 'start_offset', 'end_offset']);
        });
    }
};
