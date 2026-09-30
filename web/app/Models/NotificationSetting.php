<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    protected $fillable = [
        'shop_id', 'enabled', 'store_name', 'wa_phone_number_id', 'wa_access_token',
        'default_country_code', 'active_template_id',
    ];

    protected $hidden = ['wa_access_token'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'wa_access_token' => 'encrypted',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function activeTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'active_template_id');
    }

    public function hasCredentials(): bool
    {
        return filled($this->wa_phone_number_id) && filled($this->wa_access_token);
    }

    public function countryCode(): string
    {
        return preg_replace('/\D+/', '', $this->default_country_code ?: config('whatsapp.default_country_code'));
    }
}
