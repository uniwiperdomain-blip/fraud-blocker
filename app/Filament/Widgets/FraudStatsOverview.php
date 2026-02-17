<?php

namespace App\Filament\Widgets;

use App\Models\BlockedIp;
use App\Models\FraudLog;
use App\Models\Pageview;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FraudStatsOverview extends BaseWidget
{
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();

        $fraudToday = FraudLog::where('created_at', '>=', $today)->count();
        $fraudWeek = FraudLog::where('created_at', '>=', $thisWeek)->count();

        $blockedCount = BlockedIp::where('is_active', true)->count();
        $unsyncedCount = BlockedIp::where('is_active', true)
            ->where('synced_to_google_ads', false)
            ->count();

        $pageviewsToday = Pageview::where('created_at', '>=', $today)->count();
        $suspiciousToday = Pageview::where('created_at', '>=', $today)
            ->where('is_suspicious', true)
            ->count();
        $suspiciousPercent = $pageviewsToday > 0
            ? round(($suspiciousToday / $pageviewsToday) * 100, 1)
            : 0;

        $syncedCount = BlockedIp::where('synced_to_google_ads', true)->count();

        return [
            Stat::make('Fraud Detections Today', $fraudToday)
                ->description("{$fraudWeek} this week")
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('danger')
                ->chart($this->getFraudChart()),

            Stat::make('Blocked IPs', $blockedCount)
                ->description($unsyncedCount > 0 ? "{$unsyncedCount} not yet synced" : 'All synced')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('warning'),

            Stat::make('Suspicious Traffic', $suspiciousPercent . '%')
                ->description("{$suspiciousToday} of {$pageviewsToday} pageviews today")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($suspiciousPercent > 10 ? 'danger' : 'success'),

            Stat::make('Synced to Google Ads', $syncedCount)
                ->description('IPs excluded from campaigns')
                ->descriptionIcon('heroicon-m-cloud-arrow-up')
                ->color('info'),
        ];
    }

    protected function getFraudChart(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $nextDate = now()->subDays($i - 1)->startOfDay();
            $data[] = FraudLog::whereBetween('created_at', [$date, $nextDate])->count();
        }
        return $data;
    }
}
