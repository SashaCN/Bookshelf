<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_book_id')->constrained()->cascadeOnDelete();
            // Repeated from the entry so statistics and access checks never need a join.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('from_page');
            $table->unsignedInteger('to_page');
            // The reader's own calendar day (in their time zone), which decides what a streak is.
            $table->date('logged_on');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_book_id', 'id']);
            $table->index(['user_id', 'logged_on']);
        });

        // A log entry records a real change.
        DB::statement('ALTER TABLE reading_logs ADD CONSTRAINT reading_logs_page_changed CHECK (from_page <> to_page)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_logs');
    }
};
