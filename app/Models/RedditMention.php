<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RedditMention extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reddit_keyword_id',
        'reddit_post_id',
        'reddit_comment_id',
        'subreddit',
        'author',
        'content',
        'url',
        'mention_type',
        'found_at',
    ];

    protected $casts = [
        'found_at' => 'datetime',
    ];

    /**
     * Get the user that owns the mention.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the keyword that triggered this mention.
     */
    public function keyword()
    {
        return $this->belongsTo(RedditKeyword::class);
    }

    /**
     * Scope a query to only include post mentions.
     */
    public function scopePosts($query)
    {
        return $query->where('mention_type', 'post');
    }

    /**
     * Scope a query to only include comment mentions.
     */
    public function scopeComments($query)
    {
        return $query->where('mention_type', 'comment');
    }
}
