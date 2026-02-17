<?php

namespace App\Jobs;

use App\Models\Pageview;
use App\Models\Tenant;
use App\Models\Visitor;
use App\Services\FraudDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeFraudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $tenantId,
        public int $visitorId,
        public int $pageviewId,
    ) {}

    public function handle(FraudDetectionService $service): void
    {
        $tenant = Tenant::find($this->tenantId);
        $visitor = Visitor::find($this->visitorId);
        $pageview = Pageview::find($this->pageviewId);

        if (!$tenant || !$visitor || !$pageview) {
            return;
        }

        // Run full deferred analysis (low engagement + datacenter IP)
        $service->analyzePageview($tenant, $visitor, $pageview);

        // Check if auto-block threshold is reached
        $service->checkAndBlock($tenant, $pageview->ip_address);
    }
}
