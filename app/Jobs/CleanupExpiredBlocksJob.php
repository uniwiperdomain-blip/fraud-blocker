<?php

namespace App\Jobs;

use App\Models\BlockedIp;
use App\Models\FraudLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupExpiredBlocksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Deactivate expired blocks
        BlockedIp::expired()
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Prune old fraud logs
        $retentionDays = config('fraud.log_retention_days', 90);
        FraudLog::where('created_at', '<', now()->subDays($retentionDays))->delete();
    }
}
