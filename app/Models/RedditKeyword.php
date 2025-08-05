<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RedditKeyword extends Model
{
    protected $fillable = [
        'user_id',
        'reddit_credential_id',
        'reddit_id',
        'persona_id',
        'keyword',
        'subreddits',
        'scan_comments',
        'match_whole_word',
        'case_sensitive',
        'alert_enabled',
        'alert_methods',
        'alert_sentiments',
        'alert_intents',
        'last_checked_at',
    ];

    protected $casts = [
        'subreddits' => 'array',
        'scan_comments' => 'boolean',
        'match_whole_word' => 'boolean',
        'case_sensitive' => 'boolean',
        'last_checked_at' => 'datetime',
        'alert_enabled' => 'boolean',
        'alert_methods' => 'array',
        'alert_sentiments' => 'array',
        'alert_intents' => 'array',
    ];

    /**
     * Get the user that owns the keyword.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Reddit credential that owns the keyword.
     */
    public function credential()
    {
        return $this->belongsTo(RedditCredential::class);
    }

    /**
     * Get the persona that owns the keyword.
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }
}
