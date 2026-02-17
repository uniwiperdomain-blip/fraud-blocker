<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'account_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'manager_customer_id',
        'auto_sync_enabled',
        'last_synced_at',
        'last_sync_status',
        'last_sync_error',
        'is_active',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'auto_sync_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }

    public function getFormattedCustomerIdAttribute(): string
    {
        $id = preg_replace('/\D/', '', $this->customer_id);

        if (strlen($id) === 10) {
            return substr($id, 0, 3) . '-' . substr($id, 3, 3) . '-' . substr($id, 6, 4);
        }

        return $this->customer_id;
    }
}
