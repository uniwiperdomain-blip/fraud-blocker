<?php

namespace App\Jobs;

use App\Models\BlockedIp;
use App\Models\GoogleAdsAccount;
use App\Services\GoogleAdsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncGoogleAdsExclusionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public ?int $tenantId = null,
    ) {}

    public function handle(GoogleAdsService $googleAdsService): void
    {
        $query = GoogleAdsAccount::where('is_active', true)
            ->where('auto_sync_enabled', true);

        if ($this->tenantId) {
            $query->where('tenant_id', $this->tenantId);
        }

        foreach ($query->cursor() as $account) {
            $unsyncedIps = BlockedIp::where('tenant_id', $account->tenant_id)
                ->where('is_active', true)
                ->where('synced_to_google_ads', false)
                ->pluck('ip_address')
                ->toArray();

            if (empty($unsyncedIps)) {
                continue;
            }

            try {
                $googleAdsService->pushIpExclusions($account, $unsyncedIps);

                BlockedIp::where('tenant_id', $account->tenant_id)
                    ->whereIn('ip_address', $unsyncedIps)
                    ->update([
                        'synced_to_google_ads' => true,
                        'synced_at' => now(),
                    ]);

                $account->update([
                    'last_synced_at' => now(),
                    'last_sync_status' => 'success',
                    'last_sync_error' => null,
                ]);
            } catch (\Exception $e) {
                $account->update([
                    'last_synced_at' => now(),
                    'last_sync_status' => 'error',
                    'last_sync_error' => $e->getMessage(),
                ]);
            }
        }
    }
}
