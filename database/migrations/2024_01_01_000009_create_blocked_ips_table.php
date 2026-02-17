<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            $table->string('ip_address', 45);
            $table->unsignedSmallInteger('fraud_score')->default(0);

            // auto or manual
            $table->string('block_reason', 32)->default('auto');

            $table->boolean('synced_to_google_ads')->default(false);
            $table->timestamp('synced_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'ip_address']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'synced_to_google_ads']);
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
    }
};
