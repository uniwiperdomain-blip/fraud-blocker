<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('visitor_id')->constrained()->onDelete('cascade');
            $table->foreignId('pageview_id')->nullable()->constrained()->onDelete('set null');

            // Form identification
            $table->string('form_id')->nullable();
            $table->text('form_action')->nullable();
            $table->string('trigger_type', 64)->nullable(); // standard_submit, button_click, ajax_auto_submit, etc.

            // Form data
            $table->json('fields')->nullable();

            // Extracted contact info (for quick access)
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('company')->nullable();

            // Multi-step form tracking
            $table->unsignedTinyInteger('step_number')->nullable();
            $table->unsignedTinyInteger('total_steps')->nullable();
            $table->string('step_label')->nullable();
            $table->string('step_id')->nullable();

            // Page URL at time of submission
            $table->text('page_url')->nullable();

            // Request info
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'visitor_id']);
            $table->index(['tenant_id', 'form_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
