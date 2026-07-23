<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    protected $fillable = ['name', 'slug'];

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class);
    }
}
