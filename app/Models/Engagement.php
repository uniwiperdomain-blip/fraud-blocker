<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Engagement extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'visitor_id',
        'pageview_id',
        'time_on_page',
        'scroll_depth',
        'url',
        'created_at',
    ];

    protected $casts = [
        'time_on_page' => 'integer',
        'scroll_depth' => 'integer',
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

    public function getTimeOnPageFormattedAttribute(): string
    {
        if (!$this->time_on_page) {
            return '0s';
        }

        $minutes = floor($this->time_on_page / 60);
        $seconds = $this->time_on_page % 60;

        if ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        }

        return "{$seconds}s";
    }

    public function getScrollDepthFormattedAttribute(): string
    {
        return ($this->scroll_depth ?? 0) . '%';
    }
}
