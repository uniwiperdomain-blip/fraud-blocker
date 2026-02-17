<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('visitor_id')->constrained()->onDelete('cascade');
            $table->foreignId('pageview_id')->nullable()->constrained()->onDelete('set null');

            // Engagement metrics
            $table->unsignedInteger('time_on_page')->nullable(); // seconds
            $table->unsignedTinyInteger('scroll_depth')->nullable(); // percentage 0-100

            // Page URL
            $table->text('url')->nullable();

            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'visitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagements');
    }
};
