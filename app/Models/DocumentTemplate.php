<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Only active templates are offered when creating documents. */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
