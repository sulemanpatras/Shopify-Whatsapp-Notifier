<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    public const MODE_TEMPLATE = 'template';
    public const MODE_TEXT = 'text';

    protected $fillable = ['shop_id', 'name', 'mode', 'wa_template_name', 'language', 'body', 'variables'];

    protected function casts(): array
    {
        return ['variables' => 'array'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
