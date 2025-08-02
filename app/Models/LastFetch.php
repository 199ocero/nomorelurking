<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LastFetch extends Model
{
    protected $fillable = [
        'user_id',
        'reddit_credential_id',
        'dispatch_at',
        'last_fetched_at',
    ];

    protected $casts = [
        'dispatch_at' => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    /**
     * Get the user that owns the last fetch.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Reddit credential that owns the last fetch.
     */
    public function credential()
    {
        return $this->belongsTo(RedditCredential::class);
    }
}
