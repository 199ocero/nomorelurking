<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the Reddit credentials associated with the user.
     */
    public function redditCredential()
    {
        return $this->hasOne(RedditCredential::class);
    }

    /**
     * Get the Reddit keywords associated with the user.
     */
    public function redditKeywords()
    {
        return $this->hasMany(RedditKeyword::class);
    }

    /**
     * Get the Reddit mentions associated with the user.
     */
    public function redditMentions()
    {
        return $this->hasMany(RedditMention::class);
    }
}
