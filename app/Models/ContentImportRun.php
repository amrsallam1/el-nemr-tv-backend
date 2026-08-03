<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentImportRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'report' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
