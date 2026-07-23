<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stream extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['headers' => 'array', 'is_active' => 'boolean'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }
}
