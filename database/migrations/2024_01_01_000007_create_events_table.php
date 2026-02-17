<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('visitor_id')->constrained()->onDelete('cascade');
            $table->foreignId('pageview_id')->nullable()->constrained()->onDelete('set null');

            // Event info
            $table->string('event_name');
            $table->json('event_data')->nullable();

            // Page URL
            $table->text('url')->nullable();

            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'event_name']);
            $table->index(['tenant_id', 'visitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
