<?php

namespace App\Console\Commands;

use App\Models\Pageview;
use App\Models\Tenant;
use App\Services\FraudDetectionService;
use Illuminate\Console\Command;

class AnalyzeFraudCommand extends Command
{
    protected $signature = 'fraud:analyze {--tenant= : Tenant ID to analyze} {--hours=24 : Hours of traffic to analyze}';

    protected $description = 'Run bulk fraud analysis on recent traffic';

    public function handle(FraudDetectionService $service): int
    {
        $hours = (int) $this->option('hours');
        $tenantId = $this->option('tenant');

        $query = Pageview::where('created_at', '>=', now()->subHours($hours));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $pageviews = $query->with(['tenant', 'visitor'])->get();

        $this->info("Analyzing {$pageviews->count()} pageviews from the last {$hours} hours...");

        $bar = $this->output->createProgressBar($pageviews->count());
        $detections = 0;
        $blocks = 0;

        foreach ($pageviews as $pageview) {
            if (!$pageview->tenant || !$pageview->visitor) {
                $bar->advance();
                continue;
            }

            $score = $service->analyzePageview(
                $pageview->tenant,
                $pageview->visitor,
                $pageview
            );

            if ($score > 0) {
                $detections++;
            }

            $blocked = $service->checkAndBlock($pageview->tenant, $pageview->ip_address);
            if ($blocked) {
                $blocks++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Analysis complete:");
        $this->line("  Fraud signals detected: {$detections}");
        $this->line("  IPs blocked: {$blocks}");

        return Command::SUCCESS;
    }
}
