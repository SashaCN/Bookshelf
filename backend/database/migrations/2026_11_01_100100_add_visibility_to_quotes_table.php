<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('visibility', 16)->default('private')->after('is_favorite');
            $table->index(['user_id', 'visibility', 'id']);
        });

        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_visibility_known CHECK (visibility IN ('private', 'public'))");
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'visibility', 'id']);
        });
        DB::statement('ALTER TABLE quotes DROP CHECK quotes_visibility_known');
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
