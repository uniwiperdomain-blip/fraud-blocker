<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlockedIpResource\Pages;
use App\Models\BlockedIp;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;

class BlockedIpResource extends Resource
{
    protected static ?string $model = BlockedIp::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationLabel = 'Blocked IPs';

    protected static \UnitEnum | string | null $navigationGroup = 'Fraud Protection';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Blocked IP';

    protected static ?string $pluralModelLabel = 'Blocked IPs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Block IP Address')
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->relationship('tenant', 'name')
                        ->label('Tracking Pixel')
                        ->required()
                        ->searchable(),

                    Forms\Components\TextInput::make('ip_address')
                        ->label('IP Address')
                        ->required()
                        ->ip()
                        ->maxLength(45),

                    Forms\Components\Select::make('block_reason')
                        ->label('Reason')
                        ->options([
                            'manual' => 'Manual Block',
                            'auto' => 'Auto-detected',
                        ])
                        ->default('manual')
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expires At')
                        ->nullable()
                        ->helperText('Leave empty for permanent block'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fraud_score')
                    ->label('Score')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 100 => 'danger',
                        $state >= 50 => 'warning',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('block_reason')
                    ->label('Reason')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'auto' => 'danger',
                        'manual' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'auto' => 'Auto-blocked',
                        'manual' => 'Manual',
                        default => $state,
                    }),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\IconColumn::make('synced_to_google_ads')
                    ->label('Google Ads')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Synced At')
                    ->dateTime()
                    ->placeholder('Not synced')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Pixel')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Blocked At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('Never')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tenant')
                    ->relationship('tenant', 'name')
                    ->label('Pixel'),

                Tables\Filters\SelectFilter::make('block_reason')
                    ->options([
                        'auto' => 'Auto-blocked',
                        'manual' => 'Manual',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TernaryFilter::make('synced_to_google_ads')
                    ->label('Synced to Google Ads'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_ips')
                    ->label('Export IPs')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->action(function () {
                        $ips = BlockedIp::where('is_active', true)
                            ->pluck('ip_address')
                            ->join("\n");

                        return response()->streamDownload(function () use ($ips) {
                            echo "ip_address\n" . $ips;
                        }, 'blocked-ips-' . now()->format('Y-m-d') . '.csv');
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            Infolists\Components\Section::make('Block Details')
                ->schema([
                    Infolists\Components\TextEntry::make('ip_address')
                        ->label('IP Address')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('fraud_score')
                        ->label('Fraud Score')
                        ->badge()
                        ->color(fn (int $state): string => match (true) {
                            $state >= 100 => 'danger',
                            $state >= 50 => 'warning',
                            default => 'info',
                        }),
                    Infolists\Components\TextEntry::make('block_reason')
                        ->label('Reason')
                        ->badge()
                        ->color(fn (string $state): string => $state === 'auto' ? 'danger' : 'warning'),
                    Infolists\Components\IconEntry::make('is_active')
                        ->label('Active')
                        ->boolean(),
                ])->columns(4),

            Infolists\Components\Section::make('Google Ads Sync')
                ->schema([
                    Infolists\Components\IconEntry::make('synced_to_google_ads')
                        ->label('Synced')
                        ->boolean(),
                    Infolists\Components\TextEntry::make('synced_at')
                        ->label('Last Synced')
                        ->dateTime()
                        ->placeholder('Never'),
                ])->columns(2),

            Infolists\Components\Section::make('Timing')
                ->schema([
                    Infolists\Components\TextEntry::make('tenant.name')
                        ->label('Pixel'),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Blocked At')
                        ->dateTime(),
                    Infolists\Components\TextEntry::make('expires_at')
                        ->label('Expires')
                        ->dateTime()
                        ->placeholder('Never (permanent)'),
                ])->columns(3),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlockedIps::route('/'),
            'create' => Pages\CreateBlockedIp::route('/create'),
            'view' => Pages\ViewBlockedIp::route('/{record}'),
            'edit' => Pages\EditBlockedIp::route('/{record}/edit'),
        ];
    }
}
