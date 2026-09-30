<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('store_name')->nullable();
            $table->string('wa_phone_number_id')->nullable();
            $table->text('wa_access_token')->nullable();   // encrypted
            $table->string('default_country_code', 6)->nullable();
            $table->foreignId('active_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
