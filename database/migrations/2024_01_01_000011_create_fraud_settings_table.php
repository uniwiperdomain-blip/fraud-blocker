<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade')->unique();

            // Overall blocking threshold
            $table->unsignedSmallInteger('block_threshold')->default(100);

            // Rapid clicks
            $table->boolean('rapid_clicks_enabled')->default(true);
            $table->unsignedSmallInteger('rapid_clicks_points')->default(30);
            $table->unsignedSmallInteger('rapid_clicks_count')->default(3);
            $table->unsignedSmallInteger('rapid_clicks_window_seconds')->default(60);

            // Bot detection
            $table->boolean('bot_detection_enabled')->default(true);
            $table->unsignedSmallInteger('bot_detection_points')->default(50);

            // Low engagement
            $table->boolean('low_engagement_enabled')->default(true);
            $table->unsignedSmallInteger('low_engagement_points')->default(20);
            $table->unsignedSmallInteger('low_engagement_min_time_seconds')->default(2);
            $table->unsignedSmallInteger('low_engagement_min_scroll_depth')->default(1);

            // Datacenter/VPN IPs
            $table->boolean('datacenter_ip_enabled')->default(true);
            $table->unsignedSmallInteger('datacenter_ip_points')->default(40);

            // Score window
            $table->unsignedSmallInteger('score_window_hours')->default(24);

            // Auto-block toggle
            $table->boolean('auto_block_enabled')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_settings');
    }
};
