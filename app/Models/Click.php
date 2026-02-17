<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Click extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'visitor_id',
        'pageview_id',
        'element_type',
        'element_text',
        'element_id',
        'element_class',
        'element_href',
        'is_form_button',
        'url',
        'created_at',
    ];

    protected $casts = [
        'is_form_button' => 'boolean',
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

    public function getDisplayTextAttribute(): string
    {
        if ($this->element_text) {
            return \Str::limit($this->element_text, 50);
        }
        if ($this->element_id) {
            return '#' . $this->element_id;
        }
        return $this->element_type ?? 'Unknown';
    }
}
