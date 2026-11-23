<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_book_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_book_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at');
            $table->timestamps();

            $table->index(['user_book_id', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_book_reads');
    }
};
