<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follows', function (Blueprint $table) {
            // Follower and following lists are ordered by follows.created_at, newest first: the list of one reader
            // is read from the index in order and the sort is skipped (created_at is not the primary key's tail).
            $table->index(['followed_id', 'created_at', 'follower_id'], 'follows_followed_created_index');
            $table->index(['follower_id', 'created_at', 'followed_id'], 'follows_follower_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('follows', function (Blueprint $table) {
            $table->dropIndex('follows_followed_created_index');
            $table->dropIndex('follows_follower_created_index');
        });
    }
};
