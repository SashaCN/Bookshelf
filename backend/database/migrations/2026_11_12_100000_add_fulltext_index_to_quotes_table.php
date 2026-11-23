<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The ngram parser (ngram_token_size = 2 by default) splits text into two-character tokens, so it works for
        // Cyrillic, which has no word-boundary stemming in MySQL, and finds short words and parts of words.
        DB::statement('ALTER TABLE quotes ADD FULLTEXT INDEX quotes_content_note_fulltext (content, note) WITH PARSER ngram');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE quotes DROP INDEX quotes_content_note_fulltext');
    }
};
