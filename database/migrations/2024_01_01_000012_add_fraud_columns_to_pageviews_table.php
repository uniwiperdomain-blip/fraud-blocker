<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pageviews', function (Blueprint $table) {
            $table->json('bot_signals')->nullable()->after('user_agent');
            $table->boolean('is_suspicious')->default(false)->after('bot_signals');
            $table->unsignedSmallInteger('fraud_score')->default(0)->after('is_suspicious');

            $table->index(['tenant_id', 'is_suspicious']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pageviews', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_suspicious']);
            $table->dropIndex(['ip_address', 'created_at']);
            $table->dropColumn(['bot_signals', 'is_suspicious', 'fraud_score']);
        });
    }
};
