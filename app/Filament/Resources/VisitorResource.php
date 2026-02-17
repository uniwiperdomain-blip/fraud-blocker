<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitorResource\Pages;
use App\Models\FraudLog;
use App\Models\Visitor;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;

class VisitorResource extends Resource
{
    protected static ?string $model = Visitor::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Visitors';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\TextInput::make('identified_email')
                        ->label('Email')
                        ->email(),
                    Forms\Components\TextInput::make('identified_phone')
                        ->label('Phone'),
                    Forms\Components\TextInput::make('identified_name')
                        ->label('Name'),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('Visitor')
                    ->searchable(['identified_email', 'identified_phone', 'identified_name']),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Pixel')
                    ->sortable(),

                Tables\Columns\TextColumn::make('device_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mobile' => 'warning',
                        'tablet' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('browser')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('country')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('first_utm_source')
                    ->label('Source')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('visit_count')
                    ->label('Visits')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pageview_count')
                    ->label('Pages')
                    ->sortable(),

                Tables\Columns\TextColumn::make('form_submission_count')
                    ->label('Forms')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fraud_score')
                    ->label('Fraud Score')
                    ->state(fn ($record) => FraudLog::where('visitor_id', $record->id)->sum('score_points'))
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 100 => 'danger',
                        $state >= 50 => 'warning',
                        $state > 0 => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_identified')
                    ->label('Identified')
                    ->boolean()
                    ->state(fn ($record) => $record->isIdentified()),

                Tables\Columns\TextColumn::make('first_seen_at')
                    ->label('First Seen')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tenant')
                    ->relationship('tenant', 'name')
                    ->label('Pixel'),

                Tables\Filters\SelectFilter::make('device_type')
                    ->options([
                        'desktop' => 'Desktop',
                        'mobile' => 'Mobile',
                        'tablet' => 'Tablet',
                    ]),

                Tables\Filters\TernaryFilter::make('identified')
                    ->label('Identified')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('identified_email')->orWhereNotNull('identified_phone'),
                        false: fn ($query) => $query->whereNull('identified_email')->whereNull('identified_phone'),
                    ),

                Tables\Filters\Filter::make('suspicious')
                    ->label('Has Fraud Signals')
                    ->query(fn ($query) => $query->whereHas('fraudLogs')),
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
            Infolists\Components\Section::make('Visitor Information')
                ->schema([
                    Infolists\Components\TextEntry::make('identified_email')
                        ->label('Email')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('identified_phone')
                        ->label('Phone')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('identified_name')
                        ->label('Name'),
                    Infolists\Components\TextEntry::make('tenant.name')
                        ->label('Pixel'),
                ])->columns(4),

            Infolists\Components\Section::make('Device & Location')
                ->schema([
                    Infolists\Components\TextEntry::make('device_type')
                        ->badge(),
                    Infolists\Components\TextEntry::make('browser'),
                    Infolists\Components\TextEntry::make('os'),
                    Infolists\Components\TextEntry::make('country'),
                    Infolists\Components\TextEntry::make('city'),
                    Infolists\Components\TextEntry::make('timezone'),
                ])->columns(3),

            Infolists\Components\Section::make('First Touch Attribution')
                ->schema([
                    Infolists\Components\TextEntry::make('first_utm_source')
                        ->label('Source'),
                    Infolists\Components\TextEntry::make('first_utm_medium')
                        ->label('Medium'),
                    Infolists\Components\TextEntry::make('first_utm_campaign')
                        ->label('Campaign'),
                    Infolists\Components\TextEntry::make('first_referrer')
                        ->label('Referrer')
                        ->url(fn ($record) => $record->first_referrer),
                ])->columns(4),

            Infolists\Components\Section::make('Activity Stats')
                ->schema([
                    Infolists\Components\TextEntry::make('visit_count')
                        ->label('Total Visits'),
                    Infolists\Components\TextEntry::make('pageview_count')
                        ->label('Total Pageviews'),
                    Infolists\Components\TextEntry::make('form_submission_count')
                        ->label('Form Submissions'),
                    Infolists\Components\TextEntry::make('first_seen_at')
                        ->label('First Seen')
                        ->dateTime(),
                    Infolists\Components\TextEntry::make('last_seen_at')
                        ->label('Last Seen')
                        ->dateTime(),
                ])->columns(5),

            Infolists\Components\Section::make('Technical Details')
                ->schema([
                    Infolists\Components\TextEntry::make('cookie_id')
                        ->label('Cookie ID')
                        ->copyable()
                        ->fontFamily('mono'),
                    Infolists\Components\TextEntry::make('fingerprint_hash')
                        ->label('Fingerprint')
                        ->copyable()
                        ->fontFamily('mono'),
                ])->columns(2)->collapsed(),
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
            'index' => Pages\ListVisitors::route('/'),
            'view' => Pages\ViewVisitor::route('/{record}'),
            'edit' => Pages\EditVisitor::route('/{record}/edit'),
        ];
    }
}
