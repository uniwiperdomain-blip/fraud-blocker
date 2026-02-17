<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'visitor_id',
        'pageview_id',
        'form_id',
        'form_action',
        'trigger_type',
        'fields',
        'email',
        'phone',
        'first_name',
        'last_name',
        'full_name',
        'company',
        'step_number',
        'total_steps',
        'step_label',
        'step_id',
        'page_url',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'fields' => 'array',
        'step_number' => 'integer',
        'total_steps' => 'integer',
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

    public function hasContactInfo(): bool
    {
        return !empty($this->email) || !empty($this->phone);
    }

    public function getContactDisplayAttribute(): ?string
    {
        if ($this->email) {
            return $this->email;
        }
        if ($this->phone) {
            return $this->phone;
        }
        return null;
    }

    public function getFullNameDisplayAttribute(): ?string
    {
        if ($this->full_name) {
            return $this->full_name;
        }
        $parts = array_filter([$this->first_name, $this->last_name]);
        return !empty($parts) ? implode(' ', $parts) : null;
    }

    public function isMultiStep(): bool
    {
        return $this->total_steps !== null && $this->total_steps > 1;
    }
}
