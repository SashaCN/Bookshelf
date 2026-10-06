<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The shared catalog, not the private library entry of the author.
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            // A snapshot: the text of the quote at the moment of publishing, or the text of the review.
            $table->text('content')->nullable();
            // The author's own thought about a quoted passage.
            $table->text('comment')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedInteger('page')->nullable();
            // Kept by the application inside transactions, so a feed never has to count.
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            // Set by moderation; the author still sees the post, everybody else does not.
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            // Holds the book only for reviews. MySQL has no partial unique indexes, and NULLs never collide,
            // so (user_id, review_book_id) is unique for reviews and leaves the quote posts unrestricted.
            $table->unsignedBigInteger('review_book_id')->nullable()->virtualAs("CASE WHEN type = 'review' THEN book_id END");

            $table->unique(['user_id', 'review_book_id']);
            $table->index(['user_id', 'id']);
            $table->index(['book_id', 'type', 'id']);
        });

        DB::statement("ALTER TABLE posts ADD CONSTRAINT posts_type_known CHECK (type IN ('quote', 'review'))");
        DB::statement("ALTER TABLE posts ADD CONSTRAINT posts_rating_matches_type CHECK ((type = 'review' AND rating IS NOT NULL AND rating BETWEEN 1 AND 5) OR (type = 'quote' AND rating IS NULL))");
        DB::statement("ALTER TABLE posts ADD CONSTRAINT posts_quote_has_content CHECK (type <> 'quote' OR content IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
