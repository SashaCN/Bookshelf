<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followed_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['follower_id', 'followed_id']);
        });

        DB::statement('ALTER TABLE follows ADD CONSTRAINT follows_not_self CHECK (follower_id <> followed_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
