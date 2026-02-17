<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FraudLogResource\Pages;
use App\Models\FraudLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;

class FraudLogResource extends Resource
{
    protected static ?string $model = FraudLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Fraud Detections';

    protected static \UnitEnum | string | null $navigationGroup = 'Fraud Protection';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Fraud Detection';

    protected static ?string $pluralModelLabel = 'Fraud Detections';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('signal_type')
                    ->label('Signal')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'rapid_clicks' => 'danger',
                        'bot_detected' => 'warning',
                        'low_engagement' => 'info',
                        'datacenter_ip' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => FraudLog::SIGNAL_LABELS[$state] ?? $state),

                Tables\Columns\TextColumn::make('score_points')
                    ->label('Points')
                    ->sortable()
                    ->badge()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->reason),

                Tables\Columns\TextColumn::make('gclid')
                    ->label('Google Click ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(20)
                    ->copyable(),

                Tables\Columns\TextColumn::make('visitor.display_name')
                    ->label('Visitor')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Pixel')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tenant')
                    ->relationship('tenant', 'name')
                    ->label('Pixel'),

                Tables\Filters\SelectFilter::make('signal_type')
                    ->options(FraudLog::SIGNAL_LABELS)
                    ->label('Signal Type'),

                Tables\Filters\Filter::make('has_gclid')
                    ->label('Google Ads Traffic Only')
                    ->query(fn ($query) => $query->whereNotNull('gclid')),

                Tables\Filters\Filter::make('today')
                    ->label('Today Only')
                    ->query(fn ($query) => $query->whereDate('created_at', today())),
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
            Infolists\Components\Section::make('Detection Details')
                ->schema([
                    Infolists\Components\TextEntry::make('ip_address')
                        ->label('IP Address')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('signal_type')
                        ->label('Signal Type')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'rapid_clicks' => 'danger',
                            'bot_detected' => 'warning',
                            'low_engagement' => 'info',
                            'datacenter_ip' => 'gray',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => FraudLog::SIGNAL_LABELS[$state] ?? $state),
                    Infolists\Components\TextEntry::make('score_points')
                        ->label('Points')
                        ->badge()
                        ->color('danger'),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Detected At')
                        ->dateTime(),
                ])->columns(4),

            Infolists\Components\Section::make('Reason')
                ->schema([
                    Infolists\Components\TextEntry::make('reason')
                        ->label('')
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make('Evidence')
                ->schema([
                    Infolists\Components\KeyValueEntry::make('evidence')
                        ->label(''),
                ]),

            Infolists\Components\Section::make('Context')
                ->schema([
                    Infolists\Components\TextEntry::make('gclid')
                        ->label('Google Click ID')
                        ->copyable()
                        ->placeholder('N/A'),
                    Infolists\Components\TextEntry::make('visitor.display_name')
                        ->label('Visitor'),
                    Infolists\Components\TextEntry::make('tenant.name')
                        ->label('Pixel'),
                    Infolists\Components\TextEntry::make('pageview.url')
                        ->label('Page URL')
                        ->url(fn ($record) => $record->pageview?->url),
                ])->columns(4),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFraudLogs::route('/'),
            'view' => Pages\ViewFraudLog::route('/{record}'),
        ];
    }
}
