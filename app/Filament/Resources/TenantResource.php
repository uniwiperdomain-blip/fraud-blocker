<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationLabel = 'Tracking Pixels';

    protected static ?string $modelLabel = 'Tracking Pixel';

    protected static ?string $pluralModelLabel = 'Tracking Pixels';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('My Website'),

                    Forms\Components\TextInput::make('domain')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://example.com'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive pixels will not track any data'),
                ])->columns(2),

            Forms\Components\Section::make('Pixel Code')
                ->schema([
                    Forms\Components\TextInput::make('pixel_code')
                        ->label('Pixel Code')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn ($record) => $record !== null)
                        ->helperText('Auto-generated unique identifier'),

                    Forms\Components\Placeholder::make('embed_code')
                        ->label('Embed Code')
                        ->visible(fn ($record) => $record !== null)
                        ->content(fn ($record) => $record?->embed_code ?? '')
                        ->helperText('Copy and paste this code into your website\'s <head> section'),
                ]),

            Forms\Components\Section::make('Advanced Settings')
                ->schema([
                    Forms\Components\TagsInput::make('allowed_domains')
                        ->label('Allowed Domains')
                        ->placeholder('Add domain')
                        ->helperText('Leave empty to allow all domains. Add specific domains to restrict tracking.'),

                    Forms\Components\KeyValue::make('settings')
                        ->label('Custom Settings')
                        ->addButtonLabel('Add Setting')
                        ->keyLabel('Setting Name')
                        ->valueLabel('Value'),
                ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->url(fn ($record) => $record->domain)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('pixel_code')
                    ->label('Pixel Code')
                    ->copyable()
                    ->copyMessage('Copied!')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('visitors_count')
                    ->label('Visitors')
                    ->counts('visitors')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pageviews_count')
                    ->label('Pageviews')
                    ->counts('pageviews')
                    ->sortable(),

                Tables\Columns\TextColumn::make('form_submissions_count')
                    ->label('Forms')
                    ->counts('formSubmissions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
            Infolists\Components\Section::make('Pixel Information')
                ->schema([
                    Infolists\Components\TextEntry::make('name'),
                    Infolists\Components\TextEntry::make('domain')
                        ->url(fn ($record) => $record->domain),
                    Infolists\Components\TextEntry::make('pixel_code')
                        ->label('Pixel Code')
                        ->copyable(),
                    Infolists\Components\IconEntry::make('is_active')
                        ->label('Active')
                        ->boolean(),
                ])->columns(2),

            Infolists\Components\Section::make('Embed Code')
                ->schema([
                    Infolists\Components\TextEntry::make('embed_code')
                        ->label('')
                        ->copyable()
                        ->fontFamily('mono'),
                ]),

            Infolists\Components\Section::make('Statistics')
                ->schema([
                    Infolists\Components\TextEntry::make('visitors_count')
                        ->label('Total Visitors')
                        ->state(fn ($record) => $record->visitors()->count()),
                    Infolists\Components\TextEntry::make('pageviews_count')
                        ->label('Total Pageviews')
                        ->state(fn ($record) => $record->pageviews()->count()),
                    Infolists\Components\TextEntry::make('form_submissions_count')
                        ->label('Total Form Submissions')
                        ->state(fn ($record) => $record->formSubmissions()->count()),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Created')
                        ->dateTime(),
                ])->columns(4),
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
