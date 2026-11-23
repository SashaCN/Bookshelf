<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reader_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chapter');
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique(['reader_document_id', 'chapter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_bookmarks');
    }
};
