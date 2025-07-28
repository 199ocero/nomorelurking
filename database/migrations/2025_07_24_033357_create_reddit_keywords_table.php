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
        Schema::create('reddit_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('reddit_credential_id')->nullable()->constrained('reddit_credentials')->nullOnDelete();
            $table->string('reddit_id');
            $table->string('keyword');
            $table->json('subreddits')->nullable();
            $table->boolean('scan_comments')->default(false);
            $table->boolean('match_whole_word')->default(false);
            $table->boolean('case_sensitive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reddit_keywords');
    }
};
