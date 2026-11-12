<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class);
    }

    /** Super admins see every template; other admins only the ones assigned to them. */
    public function scopeVisibleToAdmin(Builder $query, ?Admin $admin = null): void
    {
        $admin ??= auth()->user();

        if ($admin?->isSuperAdmin()) {
            return;
        }

        $query->whereHas('admins', fn (Builder $q) => $q->whereKey($admin?->getKey()));
    }
}
