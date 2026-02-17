<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Custom Events';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Pixel')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_name')
                    ->label('Event')
                    ->searchable()
                    ->badge(),

                Tables\Columns\TextColumn::make('data_summary')
                    ->label('Data')
                    ->limit(50),

                Tables\Columns\TextColumn::make('visitor.display_name')
                    ->label('Visitor'),

                Tables\Columns\TextColumn::make('url')
                    ->label('Page')
                    ->limit(40)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tenant')
                    ->relationship('tenant', 'name')
                    ->label('Pixel'),

                Tables\Filters\SelectFilter::make('event_name')
                    ->options(fn () => Event::distinct()->pluck('event_name', 'event_name')->toArray())
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Infolists\Components\Section::make('Event Information')
                ->schema([
                    Infolists\Components\TextEntry::make('event_name')
                        ->label('Event Name')
                        ->badge(),
                    Infolists\Components\TextEntry::make('url')
                        ->label('Page URL')
                        ->url(fn ($record) => $record->url),
                    Infolists\Components\TextEntry::make('created_at')
                        ->dateTime(),
                ])->columns(3),

            Infolists\Components\Section::make('Event Data')
                ->schema([
                    Infolists\Components\KeyValueEntry::make('event_data')
                        ->label(''),
                ]),

            Infolists\Components\Section::make('Visitor & Meta')
                ->schema([
                    Infolists\Components\TextEntry::make('visitor.display_name')
                        ->label('Visitor'),
                    Infolists\Components\TextEntry::make('tenant.name')
                        ->label('Pixel'),
                    Infolists\Components\TextEntry::make('pageview.path')
                        ->label('Pageview'),
                ])->columns(3),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'view' => Pages\ViewEvent::route('/{record}'),
        ];
    }
}
