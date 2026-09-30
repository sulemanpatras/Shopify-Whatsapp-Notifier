<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // label shown in the admin panel
            $table->string('mode')->default('template');  // template = approved Meta template, text = free text
            $table->string('wa_template_name')->nullable(); // name of the approved template in WhatsApp Manager
            $table->string('language', 12)->default('en_US');
            $table->text('body');                         // preview / free-text body with {{variables}}
            $table->json('variables')->nullable();        // ordered variable keys mapped to {{1}}, {{2}}...
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
