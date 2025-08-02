<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RedditAlert extends Model
{
    protected $fillable = [
        'user_id',
        'reddit_keyword_id',
        'reddit_mention_id',
        'alert_method',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function keyword()
    {
        return $this->belongsTo(RedditKeyword::class);
    }

    public function mention()
    {
        return $this->belongsTo(RedditMention::class);
    }
}
