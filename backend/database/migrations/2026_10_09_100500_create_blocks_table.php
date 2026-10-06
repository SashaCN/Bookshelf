<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            // Who blocks, and whom.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'blocked_id']);
        });

        DB::statement('ALTER TABLE blocks ADD CONSTRAINT blocks_not_self CHECK (user_id <> blocked_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
