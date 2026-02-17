<?php

namespace App\Filament\Widgets;

use App\Models\Pageview;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PageviewsChart extends ChartWidget
{
    protected ?string $heading = 'Pageviews (Last 30 Days)';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'half';

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $nextDate = now()->subDays($i - 1)->startOfDay();

            $labels[] = $date->format('M j');
            $data[] = Pageview::whereBetween('created_at', [$date, $nextDate])->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pageviews',
                    'data' => $data,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
