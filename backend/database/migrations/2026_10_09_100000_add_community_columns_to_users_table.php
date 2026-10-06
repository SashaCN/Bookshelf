<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The public name of a reader: "@handle". Filled below for readers who registered before the community existed.
            $table->string('handle', 30)->nullable()->unique()->after('email');
            $table->string('bio', 280)->nullable()->after('timezone');
            // A technical role: the moderators work through Artisan commands.
            $table->boolean('is_admin')->default(false)->after('bio');
        });

        $this->backfillHandles();

        Schema::table('users', function (Blueprint $table) {
            $table->string('handle', 30)->nullable(false)->change();
        });

        // Case-sensitive on purpose: the column collation would let "ABC" through.
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_handle_format CHECK (REGEXP_LIKE(handle, '^[a-z0-9_]{3,30}$', 'c'))");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['handle']);
        });

        DB::statement('ALTER TABLE users DROP CHECK users_handle_format');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['handle', 'bio', 'is_admin']);
        });
    }

    /**
     * Gives every existing reader a handle made of the name, the way App\Actions\Social\GenerateHandle does for
     * new readers (kept here as a copy, so a later change of the action cannot change what this migration does).
     * Public, with the table as a parameter, so that a test can run it on a table of its own.
     */
    public function backfillHandles(string $table = 'users'): void
    {
        // Kept in memory, so a thousand readers called Olena cost a thousand updates and not a million look-ups.
        $taken = array_flip(DB::table($table)->whereNotNull('handle')->pluck('handle')->all());
        $nextNumber = [];

        DB::table($table)->orderBy('id')->chunkById(200, function ($users) use ($table, &$taken, &$nextNumber): void {
            foreach ($users as $user) {
                $base = preg_replace('/[^a-z0-9_]/', '', Str::slug((string) $user->name, '_', 'uk')) ?? '';
                $base = trim(substr($base, 0, 24), '_');
                $base = strlen($base) >= 3 ? $base : 'reader';

                $handle = $base;
                $number = $nextNumber[$base] ?? 2;

                while (isset($taken[$handle])) {
                    $handle = substr($base, 0, 30 - strlen((string) $number)).$number;
                    $number++;
                }

                $nextNumber[$base] = $number;
                $taken[$handle] = true;

                DB::table($table)->where('id', $user->id)->update(['handle' => $handle]);
            }
        });
    }
};
