<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            // "post" or "comment" (see the morph map in AppServiceProvider). No foreign key: it points at two tables.
            $table->string('reportable_type', 16);
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason', 16);
            $table->text('note')->nullable();
            $table->string('status', 16)->default('open');
            $table->timestamps();

            // One report per reader per object, which also makes "3 reports" mean "3 different readers".
            $table->unique(['reporter_id', 'reportable_type', 'reportable_id']);
            $table->index(['reportable_type', 'reportable_id', 'status']);
            $table->index(['status', 'id']);
        });

        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_type_known CHECK (reportable_type IN ('post', 'comment'))");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_reason_known CHECK (reason IN ('spam', 'abuse', 'other'))");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_known CHECK (status IN ('open', 'resolved'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
