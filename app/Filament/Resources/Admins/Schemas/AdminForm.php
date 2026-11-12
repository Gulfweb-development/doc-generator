<?php

namespace App\Filament\Resources\Admins\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('mobile')
                    ->tel()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->visibleOn('create'),
                SpatieMediaLibraryFileUpload::make('image')
                    ->maxSize(1024) //
                    ->collection('admins'),
                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Select::make('documentTemplates')
                    ->label(__('Assigned templates'))
                    ->relationship('documentTemplates', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText(__('Templates this admin can see and use. Super admins see every template.'))
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ]);
    }
}
