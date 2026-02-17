<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pageview extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'visitor_id',
        'url',
        'path',
        'title',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'fbclid',
        'gclid',
        'ttclid',
        'msclkid',
        'fbp',
        'fbc',
        'campaign_id',
        'ad_id',
        'h_ad_id',
        'screen_width',
        'screen_height',
        'viewport',
        'is_mobile',
        'is_ios',
        'is_safari',
        'ip_address',
        'user_agent',
        'url_email',
        'url_phone',
        'bot_signals',
        'is_suspicious',
        'fraud_score',
        'created_at',
    ];

    protected $casts = [
        'screen_width' => 'integer',
        'screen_height' => 'integer',
        'is_mobile' => 'boolean',
        'is_ios' => 'boolean',
        'is_safari' => 'boolean',
        'bot_signals' => 'array',
        'is_suspicious' => 'boolean',
        'fraud_score' => 'integer',
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

    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(Click::class);
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(Engagement::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function hasUtmParams(): bool
    {
        return !empty($this->utm_source) || !empty($this->utm_medium) || !empty($this->utm_campaign);
    }

    public function getUtmSummaryAttribute(): ?string
    {
        $parts = array_filter([
            $this->utm_source,
            $this->utm_medium,
            $this->utm_campaign,
        ]);

        return !empty($parts) ? implode(' / ', $parts) : null;
    }
}
