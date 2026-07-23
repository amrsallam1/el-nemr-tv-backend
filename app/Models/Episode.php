<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Episode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['air_date' => 'date', 'is_premium' => 'boolean'];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class);
    }
}
