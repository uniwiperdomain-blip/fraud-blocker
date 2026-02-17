<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('cookie_id', 64)->index();
            $table->string('fingerprint_hash', 64)->nullable()->index();

            // Device & Browser Info
            $table->string('device_type', 32)->nullable(); // desktop, mobile, tablet
            $table->string('browser', 64)->nullable();
            $table->string('browser_version', 32)->nullable();
            $table->string('os', 64)->nullable();
            $table->string('os_version', 32)->nullable();

            // Location (from IP geolocation)
            $table->string('country', 64)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('region', 128)->nullable();
            $table->string('timezone', 64)->nullable();

            // Identification
            $table->string('identified_email')->nullable()->index();
            $table->string('identified_phone', 32)->nullable()->index();
            $table->string('identified_name')->nullable();
            $table->json('identified_data')->nullable();

            // First touch attribution
            $table->string('first_utm_source')->nullable();
            $table->string('first_utm_medium')->nullable();
            $table->string('first_utm_campaign')->nullable();
            $table->string('first_utm_content')->nullable();
            $table->string('first_utm_term')->nullable();
            $table->string('first_referrer')->nullable();

            // Visit stats
            $table->unsignedInteger('visit_count')->default(1);
            $table->unsignedInteger('pageview_count')->default(0);
            $table->unsignedInteger('form_submission_count')->default(0);

            // Timestamps
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'cookie_id']);
            $table->index(['tenant_id', 'fingerprint_hash']);
            $table->index(['tenant_id', 'first_seen_at']);
            $table->index(['tenant_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
