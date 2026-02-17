<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'block_threshold',
        'rapid_clicks_enabled',
        'rapid_clicks_points',
        'rapid_clicks_count',
        'rapid_clicks_window_seconds',
        'bot_detection_enabled',
        'bot_detection_points',
        'low_engagement_enabled',
        'low_engagement_points',
        'low_engagement_min_time_seconds',
        'low_engagement_min_scroll_depth',
        'datacenter_ip_enabled',
        'datacenter_ip_points',
        'score_window_hours',
        'auto_block_enabled',
    ];

    protected $casts = [
        'block_threshold' => 'integer',
        'rapid_clicks_enabled' => 'boolean',
        'rapid_clicks_points' => 'integer',
        'rapid_clicks_count' => 'integer',
        'rapid_clicks_window_seconds' => 'integer',
        'bot_detection_enabled' => 'boolean',
        'bot_detection_points' => 'integer',
        'low_engagement_enabled' => 'boolean',
        'low_engagement_points' => 'integer',
        'low_engagement_min_time_seconds' => 'integer',
        'low_engagement_min_scroll_depth' => 'integer',
        'datacenter_ip_enabled' => 'boolean',
        'datacenter_ip_points' => 'integer',
        'score_window_hours' => 'integer',
        'auto_block_enabled' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function getForTenant(Tenant $tenant): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'block_threshold' => config('fraud.defaults.block_threshold', 100),
                'rapid_clicks_enabled' => true,
                'rapid_clicks_points' => config('fraud.defaults.rapid_clicks_points', 30),
                'rapid_clicks_count' => config('fraud.defaults.rapid_clicks_count', 3),
                'rapid_clicks_window_seconds' => config('fraud.defaults.rapid_clicks_window_seconds', 60),
                'bot_detection_enabled' => true,
                'bot_detection_points' => config('fraud.defaults.bot_detection_points', 50),
                'low_engagement_enabled' => true,
                'low_engagement_points' => config('fraud.defaults.low_engagement_points', 20),
                'low_engagement_min_time_seconds' => config('fraud.defaults.low_engagement_min_time_seconds', 2),
                'low_engagement_min_scroll_depth' => config('fraud.defaults.low_engagement_min_scroll_depth', 1),
                'datacenter_ip_enabled' => true,
                'datacenter_ip_points' => config('fraud.defaults.datacenter_ip_points', 40),
                'score_window_hours' => config('fraud.defaults.score_window_hours', 24),
                'auto_block_enabled' => true,
            ]
        );
    }
}
