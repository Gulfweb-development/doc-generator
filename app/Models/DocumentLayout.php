<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentLayout extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function options(string $type): array
    {
        return static::where('type', $type)->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function defaultId(string $type): ?int
    {
        return static::where('type', $type)->where('is_default', true)->value('id');
    }

    protected static function booted(): void
    {
        static::saved(function (self $layout) {
            if ($layout->is_default) {
                static::where('type', $layout->type)->whereKeyNot($layout->id)->update(['is_default' => false]);
            }
        });
    }
}
