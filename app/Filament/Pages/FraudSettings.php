<?php

namespace App\Filament\Pages;

use App\Models\FraudSetting;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class FraudSettings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Fraud Settings';

    protected static \UnitEnum | string | null $navigationGroup = 'Fraud Protection';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.fraud-settings';

    public ?int $tenant_id = null;
    public int $block_threshold = 100;
    public bool $auto_block_enabled = true;
    public int $score_window_hours = 24;

    public bool $rapid_clicks_enabled = true;
    public int $rapid_clicks_points = 30;
    public int $rapid_clicks_count = 3;
    public int $rapid_clicks_window_seconds = 60;

    public bool $bot_detection_enabled = true;
    public int $bot_detection_points = 50;

    public bool $low_engagement_enabled = true;
    public int $low_engagement_points = 20;
    public int $low_engagement_min_time_seconds = 2;
    public int $low_engagement_min_scroll_depth = 1;

    public bool $datacenter_ip_enabled = true;
    public int $datacenter_ip_points = 40;

    public function mount(): void
    {
        $tenant = Tenant::first();
        if ($tenant) {
            $this->tenant_id = $tenant->id;
            $this->loadSettings($tenant);
        }
    }

    protected function loadSettings(Tenant $tenant): void
    {
        $settings = FraudSetting::getForTenant($tenant);

        $this->block_threshold = $settings->block_threshold;
        $this->auto_block_enabled = $settings->auto_block_enabled;
        $this->score_window_hours = $settings->score_window_hours;
        $this->rapid_clicks_enabled = $settings->rapid_clicks_enabled;
        $this->rapid_clicks_points = $settings->rapid_clicks_points;
        $this->rapid_clicks_count = $settings->rapid_clicks_count;
        $this->rapid_clicks_window_seconds = $settings->rapid_clicks_window_seconds;
        $this->bot_detection_enabled = $settings->bot_detection_enabled;
        $this->bot_detection_points = $settings->bot_detection_points;
        $this->low_engagement_enabled = $settings->low_engagement_enabled;
        $this->low_engagement_points = $settings->low_engagement_points;
        $this->low_engagement_min_time_seconds = $settings->low_engagement_min_time_seconds;
        $this->low_engagement_min_scroll_depth = $settings->low_engagement_min_scroll_depth;
        $this->datacenter_ip_enabled = $settings->datacenter_ip_enabled;
        $this->datacenter_ip_points = $settings->datacenter_ip_points;
    }

    public function updatedTenantId($value): void
    {
        if ($value) {
            $tenant = Tenant::find($value);
            if ($tenant) {
                $this->loadSettings($tenant);
            }
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('tenant_id')
                ->label('Tracking Pixel')
                ->options(Tenant::pluck('name', 'id'))
                ->required()
                ->live()
                ->columnSpanFull(),

            Forms\Components\Section::make('General Settings')
                ->schema([
                    Forms\Components\TextInput::make('block_threshold')
                        ->label('Auto-block Threshold (points)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(1000)
                        ->helperText('Total fraud score needed to auto-block an IP'),
                    Forms\Components\TextInput::make('score_window_hours')
                        ->label('Score Window (hours)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(720)
                        ->helperText('Period over which fraud points are accumulated'),
                    Forms\Components\Toggle::make('auto_block_enabled')
                        ->label('Auto-block Enabled')
                        ->helperText('Automatically block IPs exceeding the threshold'),
                ])->columns(3),

            Forms\Components\Section::make('Rapid Clicks')
                ->description('Detect multiple ad clicks from the same IP in a short window')
                ->schema([
                    Forms\Components\Toggle::make('rapid_clicks_enabled')
                        ->label('Enabled'),
                    Forms\Components\TextInput::make('rapid_clicks_points')
                        ->label('Points')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('rapid_clicks_count')
                        ->label('Click Count Threshold')
                        ->numeric()
                        ->required()
                        ->helperText('Number of clicks to trigger'),
                    Forms\Components\TextInput::make('rapid_clicks_window_seconds')
                        ->label('Window (seconds)')
                        ->numeric()
                        ->required(),
                ])->columns(4),

            Forms\Components\Section::make('Bot Detection')
                ->description('Detect headless browsers, bots, and automated tools')
                ->schema([
                    Forms\Components\Toggle::make('bot_detection_enabled')
                        ->label('Enabled'),
                    Forms\Components\TextInput::make('bot_detection_points')
                        ->label('Points')
                        ->numeric()
                        ->required(),
                ])->columns(2),

            Forms\Components\Section::make('Low Engagement')
                ->description('Flag ad clicks with no meaningful page engagement')
                ->schema([
                    Forms\Components\Toggle::make('low_engagement_enabled')
                        ->label('Enabled'),
                    Forms\Components\TextInput::make('low_engagement_points')
                        ->label('Points')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('low_engagement_min_time_seconds')
                        ->label('Min Time on Page (seconds)')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('low_engagement_min_scroll_depth')
                        ->label('Min Scroll Depth (%)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->maxValue(100),
                ])->columns(4),

            Forms\Components\Section::make('VPN / Datacenter IPs')
                ->description('Detect traffic from VPNs, proxies, and datacenter IPs')
                ->schema([
                    Forms\Components\Toggle::make('datacenter_ip_enabled')
                        ->label('Enabled'),
                    Forms\Components\TextInput::make('datacenter_ip_points')
                        ->label('Points')
                        ->numeric()
                        ->required(),
                ])->columns(2),
        ];
    }

    public function save(): void
    {
        if (!$this->tenant_id) {
            Notification::make()
                ->title('Please select a tracking pixel')
                ->danger()
                ->send();
            return;
        }

        $tenant = Tenant::find($this->tenant_id);
        if (!$tenant) return;

        $settings = FraudSetting::getForTenant($tenant);
        $settings->update([
            'block_threshold' => $this->block_threshold,
            'auto_block_enabled' => $this->auto_block_enabled,
            'score_window_hours' => $this->score_window_hours,
            'rapid_clicks_enabled' => $this->rapid_clicks_enabled,
            'rapid_clicks_points' => $this->rapid_clicks_points,
            'rapid_clicks_count' => $this->rapid_clicks_count,
            'rapid_clicks_window_seconds' => $this->rapid_clicks_window_seconds,
            'bot_detection_enabled' => $this->bot_detection_enabled,
            'bot_detection_points' => $this->bot_detection_points,
            'low_engagement_enabled' => $this->low_engagement_enabled,
            'low_engagement_points' => $this->low_engagement_points,
            'low_engagement_min_time_seconds' => $this->low_engagement_min_time_seconds,
            'low_engagement_min_scroll_depth' => $this->low_engagement_min_scroll_depth,
            'datacenter_ip_enabled' => $this->datacenter_ip_enabled,
            'datacenter_ip_points' => $this->datacenter_ip_points,
        ]);

        Notification::make()
            ->title('Fraud settings saved')
            ->success()
            ->send();
    }
}
