<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    public const PENDING = 'pending';
    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';

    protected $fillable = [
        'shop_id', 'order_id', 'order_name', 'customer_phone', 'template_id', 'status',
        'wa_message_id', 'error', 'order_payload', 'attempts', 'sent_at',
    ];

    protected $hidden = ['order_payload'];

    protected function casts(): array
    {
        return ['order_payload' => 'array', 'sent_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }
}
