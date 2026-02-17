<?php

namespace App\Filament\Widgets;

use App\Models\FormSubmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentFormSubmissions extends BaseWidget
{
    protected static ?string $heading = 'Recent Form Submissions';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FormSubmission::query()->latest('created_at')->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Pixel'),

                Tables\Columns\TextColumn::make('email')
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->copyable(),

                Tables\Columns\TextColumn::make('full_name_display')
                    ->label('Name'),

                Tables\Columns\TextColumn::make('form_id')
                    ->label('Form')
                    ->limit(20),

                Tables\Columns\TextColumn::make('trigger_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'standard_submit' => 'success',
                        'button_click' => 'info',
                        default => 'gray',
                    }),
            ])
            ->paginated(false);
    }
}
