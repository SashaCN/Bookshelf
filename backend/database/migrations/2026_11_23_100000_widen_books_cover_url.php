<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A cover link typed in by hand may be up to 2048 characters long. */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('cover_url', 2048)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('cover_url', 500)->nullable()->change();
        });
    }
};
