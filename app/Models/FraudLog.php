<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    const SIGNAL_RAPID_CLICKS = 'rapid_clicks';
    const SIGNAL_BOT_DETECTED = 'bot_detected';
    const SIGNAL_LOW_ENGAGEMENT = 'low_engagement';
    const SIGNAL_DATACENTER_IP = 'datacenter_ip';

    const SIGNAL_LABELS = [
        self::SIGNAL_RAPID_CLICKS => 'Rapid Clicks',
        self::SIGNAL_BOT_DETECTED => 'Bot Detected',
        self::SIGNAL_LOW_ENGAGEMENT => 'Low Engagement',
        self::SIGNAL_DATACENTER_IP => 'Datacenter IP',
    ];

    protected $fillable = [
        'tenant_id',
        'visitor_id',
        'pageview_id',
        'ip_address',
        'signal_type',
        'score_points',
        'reason',
        'evidence',
        'gclid',
        'created_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'score_points' => 'integer',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function pageview(): BelongsTo
    {
        return $this->belongsTo(Pageview::class);
    }

    public function getSignalLabelAttribute(): string
    {
        return self::SIGNAL_LABELS[$this->signal_type] ?? $this->signal_type;
    }
}
