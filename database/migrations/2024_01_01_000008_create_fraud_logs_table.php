<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('visitor_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('pageview_id')->nullable()->constrained()->onDelete('set null');

            $table->string('ip_address', 45);

            // Signal type: rapid_clicks, bot_detected, low_engagement, datacenter_ip
            $table->string('signal_type', 32);

            $table->unsignedSmallInteger('score_points');
            $table->string('reason');
            $table->json('evidence')->nullable();
            $table->string('gclid')->nullable();

            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'ip_address']);
            $table->index(['tenant_id', 'signal_type']);
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_logs');
    }
};
