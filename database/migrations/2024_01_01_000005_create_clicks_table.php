<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('visitor_id')->constrained()->onDelete('cascade');
            $table->foreignId('pageview_id')->nullable()->constrained()->onDelete('set null');

            // Element info
            $table->string('element_type', 32)->nullable(); // button, a, input, etc.
            $table->string('element_text', 512)->nullable();
            $table->string('element_id')->nullable();
            $table->string('element_class', 512)->nullable();
            $table->text('element_href')->nullable();
            $table->boolean('is_form_button')->default(false);

            // Page URL
            $table->text('url')->nullable();

            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'visitor_id']);
            $table->index(['tenant_id', 'element_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clicks');
    }
};
