<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // One like per reader per post.
            $table->primary(['user_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
