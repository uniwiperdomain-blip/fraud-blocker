<?php

namespace App\Console\Commands;

use App\Jobs\SyncGoogleAdsExclusionsJob;
use Illuminate\Console\Command;

class SyncGoogleAdsCommand extends Command
{
    protected $signature = 'fraud:sync-google {--tenant= : Tenant ID to sync}';

    protected $description = 'Manually trigger Google Ads IP exclusion sync';

    public function handle(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $this->info('Dispatching Google Ads sync job...');

        SyncGoogleAdsExclusionsJob::dispatchSync($tenantId);

        $this->info('Sync completed.');

        return Command::SUCCESS;
    }
}
