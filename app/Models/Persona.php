<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    protected $fillable = [
        'user_id',
        'user_type',
        'name',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Get the user that owns the persona.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the keywords associated with the persona.
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(RedditKeyword::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($persona) {
            if ($persona->keywords()->count() > 0) {
                throw new \Exception('Cannot delete persona with active keyword associations. Reassign keywords first.');
            }
        });
    }
}
