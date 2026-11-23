<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_chapter_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reader_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chapter');
            // The reader's local calendar day on which the chapter was first left forward.
            $table->date('read_on');
            $table->timestamp('created_at')->nullable();

            $table->unique(['reader_document_id', 'chapter']);
            $table->index(['reader_document_id', 'read_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_chapter_reads');
    }
};
