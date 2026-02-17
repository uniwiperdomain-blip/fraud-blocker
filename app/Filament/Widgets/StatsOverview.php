<?php

namespace App\Filament\Widgets;

use App\Models\FormSubmission;
use App\Models\Pageview;
use App\Models\Tenant;
use App\Models\Visitor;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        // Today's stats
        $visitorsToday = Visitor::where('first_seen_at', '>=', $today)->count();
        $pageviewsToday = Pageview::where('created_at', '>=', $today)->count();
        $formsToday = FormSubmission::where('created_at', '>=', $today)->count();

        // This week
        $visitorsWeek = Visitor::where('first_seen_at', '>=', $thisWeek)->count();
        $pageviewsWeek = Pageview::where('created_at', '>=', $thisWeek)->count();
        $formsWeek = FormSubmission::where('created_at', '>=', $thisWeek)->count();

        // Totals
        $totalVisitors = Visitor::count();
        $totalPageviews = Pageview::count();
        $totalForms = FormSubmission::count();
        $totalPixels = Tenant::where('is_active', true)->count();

        // Calculate trends (compare to yesterday)
        $yesterday = now()->subDay()->startOfDay();
        $visitorsYesterday = Visitor::whereBetween('first_seen_at', [$yesterday, $today])->count();
        $visitorsTrend = $visitorsYesterday > 0
            ? round((($visitorsToday - $visitorsYesterday) / $visitorsYesterday) * 100)
            : ($visitorsToday > 0 ? 100 : 0);

        return [
            Stat::make('Active Pixels', $totalPixels)
                ->description('Tracking pixels')
                ->descriptionIcon('heroicon-m-square-3-stack-3d')
                ->color('primary'),

            Stat::make('Visitors Today', $visitorsToday)
                ->description($visitorsTrend >= 0 ? "+{$visitorsTrend}% from yesterday" : "{$visitorsTrend}% from yesterday")
                ->descriptionIcon($visitorsTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($visitorsTrend >= 0 ? 'success' : 'danger')
                ->chart($this->getVisitorChart()),

            Stat::make('Pageviews Today', $pageviewsToday)
                ->description("{$pageviewsWeek} this week")
                ->descriptionIcon('heroicon-m-eye')
                ->color('info'),

            Stat::make('Form Submissions Today', $formsToday)
                ->description("{$formsWeek} this week")
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),

            Stat::make('Total Visitors', number_format($totalVisitors))
                ->description('All time')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),

            Stat::make('Total Pageviews', number_format($totalPageviews))
                ->description('All time')
                ->descriptionIcon('heroicon-m-eye')
                ->color('gray'),
        ];
    }

    protected function getVisitorChart(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $nextDate = now()->subDays($i - 1)->startOfDay();
            $data[] = Visitor::whereBetween('first_seen_at', [$date, $nextDate])->count();
        }
        return $data;
    }
}
