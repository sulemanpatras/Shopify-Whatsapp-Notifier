<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->string('order_name')->nullable();     // #1001
            $table->string('customer_phone')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->string('status')->default('pending'); // pending | sent | failed | skipped
            $table->string('wa_message_id')->nullable();
            $table->text('error')->nullable();
            $table->json('order_payload')->nullable();    // trimmed snapshot used for resend
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'order_id']);      // one confirmation per order (webhooks can be delivered twice)
            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
