<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RedditCredential extends Model
{
    protected $fillable = [
        'user_id',
        'reddit_id',
        'username',
        'access_token',
        'refresh_token',
        'expires_in',
        'token_expires_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
