<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'visitor_id',
        'pageview_id',
        'event_name',
        'event_data',
        'url',
        'created_at',
    ];

    protected $casts = [
        'event_data' => 'array',
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

    public function getDataSummaryAttribute(): ?string
    {
        if (empty($this->event_data)) {
            return null;
        }

        $summary = [];
        foreach ($this->event_data as $key => $value) {
            if (is_scalar($value)) {
                $summary[] = "{$key}: {$value}";
            }
        }

        return implode(', ', array_slice($summary, 0, 3));
    }
}
