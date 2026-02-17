<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'cookie_id',
        'fingerprint_hash',
        'device_type',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'country',
        'country_code',
        'city',
        'region',
        'timezone',
        'identified_email',
        'identified_phone',
        'identified_name',
        'identified_data',
        'first_utm_source',
        'first_utm_medium',
        'first_utm_campaign',
        'first_utm_content',
        'first_utm_term',
        'first_referrer',
        'visit_count',
        'pageview_count',
        'form_submission_count',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'identified_data' => 'array',
        'visit_count' => 'integer',
        'pageview_count' => 'integer',
        'form_submission_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pageviews(): HasMany
    {
        return $this->hasMany(Pageview::class);
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

    public function isIdentified(): bool
    {
        return !empty($this->identified_email) || !empty($this->identified_phone);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->identified_name) {
            return $this->identified_name;
        }
        if ($this->identified_email) {
            return $this->identified_email;
        }
        if ($this->identified_phone) {
            return $this->identified_phone;
        }
        return 'Visitor #' . $this->id;
    }

    public function incrementVisitCount(): void
    {
        $this->increment('visit_count');
        $this->update(['last_seen_at' => now()]);
    }

    public function incrementPageviewCount(): void
    {
        $this->increment('pageview_count');
    }

    public function incrementFormSubmissionCount(): void
    {
        $this->increment('form_submission_count');
    }
}
