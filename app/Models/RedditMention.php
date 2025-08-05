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
        'keyword',
        'subreddit',
        'author',
        'title',
        'content',
        'url',
        'mention_type',
        'upvotes',
        'downvotes',
        'comment_count',
        'is_stickied',
        'is_locked',
        'sentiment',
        'sentiment_confidence',
        'intent',
        'intent_confidence',
        'suggested_reply',
        'reddit_created_at',
        'found_at',
        'persona',
    ];

    protected $casts = [
        'found_at' => 'datetime',
        'reddit_created_at' => 'datetime',
        'is_stickied' => 'boolean',
        'is_locked' => 'boolean',
        'persona' => 'array',
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
        return $this->belongsTo(RedditKeyword::class, 'reddit_keyword_id');
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
