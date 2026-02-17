<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            $table->string('customer_id', 20);
            $table->string('account_name')->nullable();

            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('token_expires_at')->nullable();

            $table->string('manager_customer_id', 20)->nullable();

            $table->boolean('auto_sync_enabled')->default(true);

            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 32)->nullable();
            $table->text('last_sync_error')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_accounts');
    }
};
