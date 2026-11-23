<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            // Set by moderation; the author of the comment still sees it, everybody else does not.
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
