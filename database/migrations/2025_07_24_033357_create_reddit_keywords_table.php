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
            $table->foreignId('persona_id')->constrained('personas');
            $table->string('reddit_id');
            $table->string('keyword');
            $table->json('subreddits')->nullable();
            $table->boolean('scan_comments')->default(false);
            $table->boolean('match_whole_word')->default(false);
            $table->boolean('case_sensitive')->default(false);
            $table->boolean('alert_enabled')->default(false);
            $table->json('alert_methods')->nullable();
            $table->json('alert_sentiments')->nullable();
            $table->json('alert_intents')->nullable();
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
