<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_documents', function (Blueprint $table) {
            $table->foreignId('user_book_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reader_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_book_id');
        });
    }
};
