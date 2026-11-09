<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A letterhead: the header and footer that go together on a document. */
class DocumentLayout extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function options(): array
    {
        return static::orderBy('name')->pluck('name', 'id')->all();
    }

    public static function defaultId(): ?int
    {
        return static::where('is_default', true)->value('id');
    }

    protected static function booted(): void
    {
        static::saved(function (self $layout) {
            if ($layout->is_default) {
                static::whereKeyNot($layout->id)->update(['is_default' => false]);
            }
        });
    }
}
