<?php

namespace App\Filament\Pages;

use App\Models\BlockedIp;
use App\Models\GoogleAdsAccount;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GoogleAdsSettings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Google Ads';

    protected static \UnitEnum | string | null $navigationGroup = 'Fraud Protection';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.google-ads-settings';

    public ?int $selectedTenantId = null;
    public string $blockedIpsList = '';
    public array $accounts = [];

    public function mount(): void
    {
        $tenant = Tenant::first();
        if ($tenant) {
            $this->selectedTenantId = $tenant->id;
            $this->refreshData();
        }
    }

    public function updatedSelectedTenantId(): void
    {
        $this->refreshData();
    }

    protected function refreshData(): void
    {
        if (!$this->selectedTenantId) return;

        $this->blockedIpsList = BlockedIp::where('tenant_id', $this->selectedTenantId)
            ->where('is_active', true)
            ->pluck('ip_address')
            ->join("\n");

        $this->accounts = GoogleAdsAccount::where('tenant_id', $this->selectedTenantId)
            ->get()
            ->toArray();
    }

    public function connectGoogleAds(): void
    {
        if (!$this->selectedTenantId) {
            Notification::make()
                ->title('Please select a tracking pixel first')
                ->danger()
                ->send();
            return;
        }

        $clientId = config('services.google_ads.client_id');

        if (empty($clientId)) {
            Notification::make()
                ->title('Google Ads API not configured')
                ->body('Set GOOGLE_ADS_CLIENT_ID, GOOGLE_ADS_CLIENT_SECRET, and GOOGLE_ADS_DEVELOPER_TOKEN in your .env file.')
                ->danger()
                ->send();
            return;
        }

        $this->redirect(route('google-ads.connect', ['tenant' => $this->selectedTenantId]));
    }

    public function downloadCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $ips = BlockedIp::where('tenant_id', $this->selectedTenantId)
            ->where('is_active', true)
            ->get(['ip_address', 'fraud_score', 'block_reason', 'created_at']);

        return response()->streamDownload(function () use ($ips) {
            echo "ip_address,fraud_score,block_reason,blocked_at\n";
            foreach ($ips as $ip) {
                echo "{$ip->ip_address},{$ip->fraud_score},{$ip->block_reason},{$ip->created_at}\n";
            }
        }, 'blocked-ips-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('selectedTenantId')
                ->label('Tracking Pixel')
                ->options(Tenant::pluck('name', 'id'))
                ->required()
                ->live()
                ->columnSpanFull(),
        ];
    }
}
