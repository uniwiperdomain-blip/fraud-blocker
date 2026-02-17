<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pageviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('visitor_id')->constrained()->onDelete('cascade');

            // Page info
            $table->text('url');
            $table->string('path', 512)->nullable();
            $table->string('title', 512)->nullable();
            $table->text('referrer')->nullable();

            // UTM Parameters
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();

            // Ad Platform Click IDs
            $table->string('fbclid')->nullable();
            $table->string('gclid')->nullable();
            $table->string('ttclid')->nullable();
            $table->string('msclkid')->nullable();

            // Facebook Cookies
            $table->string('fbp')->nullable();
            $table->string('fbc')->nullable();

            // Additional tracking IDs
            $table->string('campaign_id')->nullable();
            $table->string('ad_id')->nullable();
            $table->string('h_ad_id')->nullable(); // Hyros

            // Device info for this pageview
            $table->unsignedSmallInteger('screen_width')->nullable();
            $table->unsignedSmallInteger('screen_height')->nullable();
            $table->string('viewport', 32)->nullable();
            $table->boolean('is_mobile')->default(false);
            $table->boolean('is_ios')->default(false);
            $table->boolean('is_safari')->default(false);

            // Request info
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Contact info from URL params
            $table->string('url_email')->nullable();
            $table->string('url_phone', 32)->nullable();

            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'visitor_id']);
            $table->index(['tenant_id', 'utm_source']);
            $table->index(['tenant_id', 'utm_campaign']);
            $table->index('fbclid');
            $table->index('gclid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pageviews');
    }
};
