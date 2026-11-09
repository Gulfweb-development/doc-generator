<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                ...DocumentContent::components(afterBody: [
                    KeyValue::make('placeholder_values')
                        ->label('Placeholders')
                        ->addable(false)
                        ->deletable(false)
                        ->editableKeys(false)
                        ->visible(fn (Get $get): bool => filled($get('placeholder_values')))
                        ->columnSpanFull(),
                ]),
            ]);
    }
}
