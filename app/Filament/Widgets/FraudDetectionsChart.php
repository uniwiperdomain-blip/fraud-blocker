<?php

namespace App\Filament\Widgets;

use App\Models\FraudLog;
use Filament\Widgets\ChartWidget;

class FraudDetectionsChart extends ChartWidget
{
    protected ?string $heading = 'Fraud Detections (Last 30 Days)';

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'half';

    protected function getData(): array
    {
        $signalTypes = [
            'rapid_clicks' => ['label' => 'Rapid Clicks', 'color' => '#ef4444'],
            'bot_detected' => ['label' => 'Bot Detected', 'color' => '#f59e0b'],
            'low_engagement' => ['label' => 'Low Engagement', 'color' => '#3b82f6'],
            'datacenter_ip' => ['label' => 'Datacenter IP', 'color' => '#6b7280'],
        ];

        $labels = [];
        $datasets = [];

        // Initialize datasets
        foreach ($signalTypes as $type => $config) {
            $datasets[$type] = [
                'label' => $config['label'],
                'data' => [],
                'borderColor' => $config['color'],
                'backgroundColor' => $config['color'] . '20',
                'fill' => false,
                'tension' => 0.3,
            ];
        }

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $nextDate = now()->subDays($i - 1)->startOfDay();
            $labels[] = $date->format('M j');

            foreach ($signalTypes as $type => $config) {
                $datasets[$type]['data'][] = FraudLog::where('signal_type', $type)
                    ->whereBetween('created_at', [$date, $nextDate])
                    ->count();
            }
        }

        return [
            'datasets' => array_values($datasets),
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
        ];
    }
}
