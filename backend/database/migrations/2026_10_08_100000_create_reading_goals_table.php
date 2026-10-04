<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('target_books');
            $table->timestamps();

            // One goal per reader per year.
            $table->unique(['user_id', 'year']);
        });

        DB::statement('ALTER TABLE reading_goals ADD CONSTRAINT reading_goals_target_range CHECK (target_books BETWEEN 1 AND 1000)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_goals');
    }
};
