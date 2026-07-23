<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use SoftDeletes;

    protected $table = 'media';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'vote_average' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_recommended' => 'boolean',
            'is_pinned' => 'boolean',
            'is_premium' => 'boolean',
            'is_published' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class);
    }
}
