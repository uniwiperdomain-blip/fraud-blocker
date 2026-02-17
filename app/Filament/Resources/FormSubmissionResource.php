<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FormSubmissionResource\Pages;
use App\Models\FormSubmission;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;

class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Form Submissions';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Contact Information')
                ->schema([
                    Forms\Components\TextInput::make('email'),
                    Forms\Components\TextInput::make('phone'),
                    Forms\Components\TextInput::make('full_name'),
                    Forms\Components\TextInput::make('company'),
                ])->columns(2),
        ]);
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

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('full_name_display')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name', 'full_name']),

                Tables\Columns\TextColumn::make('form_id')
                    ->label('Form')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('trigger_type')
                    ->label('Trigger')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'standard_submit' => 'success',
                        'button_click' => 'info',
                        'ajax_auto_submit' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('step_number')
                    ->label('Step')
                    ->formatStateUsing(fn ($record) => $record->total_steps
                        ? "{$record->step_number}/{$record->total_steps}"
                        : null),

                Tables\Columns\TextColumn::make('visitor.display_name')
                    ->label('Visitor')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tenant')
                    ->relationship('tenant', 'name')
                    ->label('Pixel'),

                Tables\Filters\SelectFilter::make('trigger_type')
                    ->options([
                        'standard_submit' => 'Standard Submit',
                        'button_click' => 'Button Click',
                        'ajax_auto_submit' => 'Ajax Auto Submit',
                        'popup_button_click' => 'Popup Button',
                    ]),

                Tables\Filters\Filter::make('has_email')
                    ->query(fn ($query) => $query->whereNotNull('email')),

                Tables\Filters\Filter::make('has_phone')
                    ->query(fn ($query) => $query->whereNotNull('phone')),
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
            Infolists\Components\Section::make('Contact Information')
                ->schema([
                    Infolists\Components\TextEntry::make('email')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('phone')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('full_name_display')
                        ->label('Name'),
                    Infolists\Components\TextEntry::make('company'),
                ])->columns(4),

            Infolists\Components\Section::make('Form Details')
                ->schema([
                    Infolists\Components\TextEntry::make('form_id')
                        ->label('Form ID'),
                    Infolists\Components\TextEntry::make('trigger_type')
                        ->label('Trigger')
                        ->badge(),
                    Infolists\Components\TextEntry::make('step_info')
                        ->label('Step')
                        ->state(fn ($record) => $record->total_steps
                            ? "Step {$record->step_number} of {$record->total_steps}"
                            : 'Single step'),
                    Infolists\Components\TextEntry::make('page_url')
                        ->label('Page URL')
                        ->url(fn ($record) => $record->page_url),
                ])->columns(2),

            Infolists\Components\Section::make('All Form Fields')
                ->schema([
                    Infolists\Components\KeyValueEntry::make('fields')
                        ->label(''),
                ]),

            Infolists\Components\Section::make('Visitor & Meta')
                ->schema([
                    Infolists\Components\TextEntry::make('visitor.display_name')
                        ->label('Visitor'),
                    Infolists\Components\TextEntry::make('tenant.name')
                        ->label('Pixel'),
                    Infolists\Components\TextEntry::make('ip_address')
                        ->label('IP Address'),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Submitted At')
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
            'index' => Pages\ListFormSubmissions::route('/'),
            'view' => Pages\ViewFormSubmission::route('/{record}'),
        ];
    }
}
