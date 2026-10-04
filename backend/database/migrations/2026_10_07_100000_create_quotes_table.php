<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_book_id')->constrained()->cascadeOnDelete();
            // Repeated from the entry so listing and access checks never need a join.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16)->default('quote');
            $table->text('content');
            // The reader's own thought about the passage.
            $table->text('note')->nullable();
            $table->unsignedInteger('page')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'is_favorite']);
            $table->index(['user_book_id', 'id']);
        });

        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_type_known CHECK (type IN ('quote', 'insight'))");
        DB::statement('ALTER TABLE quotes ADD CONSTRAINT quotes_page_positive CHECK (page IS NULL OR page > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
