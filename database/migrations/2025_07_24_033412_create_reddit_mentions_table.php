<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reddit_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('reddit_keyword_id')->constrained('reddit_keywords')->onDelete('cascade');
            $table->string('reddit_post_id')->nullable();
            $table->string('reddit_comment_id')->nullable();
            $table->string('subreddit')->nullable();
            $table->string('author');
            $table->text('content');
            $table->text('url');
            $table->enum('mention_type', ['post', 'comment']);
            $table->timestamp('found_at');
            $table->timestamps();

            $table->index(['user_id', 'found_at']);
            $table->index(['reddit_keyword_id', 'found_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reddit_mentions');
    }
};
