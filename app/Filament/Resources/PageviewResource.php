<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageviewResource\Pages;
use App\Models\Pageview;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;

class PageviewResource extends Resource
{
    protected static ?string $model = Pageview::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'Pageviews';

    protected static ?int $navigationSort = 4;

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

                Tables\Columns\TextColumn::make('path')
                    ->label('Page')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('title')
                    ->limit(30)
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('utm_summary')
                    ->label('UTM')
                    ->limit(30),

                Tables\Columns\IconColumn::make('has_fbclid')
                    ->label('FB')
                    ->boolean()
                    ->state(fn ($record) => !empty($record->fbclid)),

                Tables\Columns\IconColumn::make('has_gclid')
                    ->label('Google')
                    ->boolean()
                    ->state(fn ($record) => !empty($record->gclid)),

                Tables\Columns\TextColumn::make('visitor.display_name')
                    ->label('Visitor')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_mobile')
                    ->label('Mobile')
                    ->boolean(),

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

                Tables\Filters\TernaryFilter::make('is_mobile')
                    ->label('Mobile'),

                Tables\Filters\Filter::make('has_utm')
                    ->label('Has UTM')
                    ->query(fn ($query) => $query->whereNotNull('utm_source')),

                Tables\Filters\Filter::make('has_fbclid')
                    ->label('Has Facebook Click ID')
                    ->query(fn ($query) => $query->whereNotNull('fbclid')),

                Tables\Filters\Filter::make('has_gclid')
                    ->label('Has Google Click ID')
                    ->query(fn ($query) => $query->whereNotNull('gclid')),
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
            Infolists\Components\Section::make('Page Information')
                ->schema([
                    Infolists\Components\TextEntry::make('url')
                        ->label('Full URL')
                        ->url(fn ($record) => $record->url)
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('title'),
                    Infolists\Components\TextEntry::make('referrer')
                        ->url(fn ($record) => $record->referrer),
                ])->columns(2),

            Infolists\Components\Section::make('UTM Parameters')
                ->schema([
                    Infolists\Components\TextEntry::make('utm_source')
                        ->label('Source'),
                    Infolists\Components\TextEntry::make('utm_medium')
                        ->label('Medium'),
                    Infolists\Components\TextEntry::make('utm_campaign')
                        ->label('Campaign'),
                    Infolists\Components\TextEntry::make('utm_content')
                        ->label('Content'),
                    Infolists\Components\TextEntry::make('utm_term')
                        ->label('Term'),
                ])->columns(5),

            Infolists\Components\Section::make('Click IDs')
                ->schema([
                    Infolists\Components\TextEntry::make('fbclid')
                        ->label('Facebook Click ID')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('gclid')
                        ->label('Google Click ID')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('ttclid')
                        ->label('TikTok Click ID')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('fbp')
                        ->label('Facebook Pixel')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('fbc')
                        ->label('Facebook Cookie')
                        ->copyable(),
                ])->columns(5),

            Infolists\Components\Section::make('Device & Request')
                ->schema([
                    Infolists\Components\IconEntry::make('is_mobile')
                        ->label('Mobile')
                        ->boolean(),
                    Infolists\Components\IconEntry::make('is_ios')
                        ->label('iOS')
                        ->boolean(),
                    Infolists\Components\IconEntry::make('is_safari')
                        ->label('Safari')
                        ->boolean(),
                    Infolists\Components\TextEntry::make('viewport'),
                    Infolists\Components\TextEntry::make('screen_size')
                        ->state(fn ($record) => $record->screen_width && $record->screen_height
                            ? "{$record->screen_width}x{$record->screen_height}"
                            : null),
                    Infolists\Components\TextEntry::make('ip_address'),
                ])->columns(3),

            Infolists\Components\Section::make('Visitor & Meta')
                ->schema([
                    Infolists\Components\TextEntry::make('visitor.display_name')
                        ->label('Visitor'),
                    Infolists\Components\TextEntry::make('tenant.name')
                        ->label('Pixel'),
                    Infolists\Components\TextEntry::make('created_at')
                        ->dateTime(),
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
            'index' => Pages\ListPageviews::route('/'),
            'view' => Pages\ViewPageview::route('/{record}'),
        ];
    }
}
