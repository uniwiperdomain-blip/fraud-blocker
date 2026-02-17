<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'domain',
        'pixel_code',
        'settings',
        'allowed_domains',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'allowed_domains' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
            if (empty($tenant->pixel_code)) {
                $tenant->pixel_code = self::generatePixelCode();
            }
        });
    }

    public static function generatePixelCode(): string
    {
        do {
            $code = Str::random(24);
        } while (self::where('pixel_code', $code)->exists());

        return $code;
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
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

    public function getEmbedCodeAttribute(): string
    {
        $baseUrl = config('app.url');
        return "<script src=\"{$baseUrl}/api/pixel/{$this->pixel_code}.js\" async></script>";
    }
}
