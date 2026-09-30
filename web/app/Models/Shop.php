<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shop extends Model
{
    protected $fillable = ['domain', 'access_token', 'scopes', 'installed_at', 'uninstalled_at'];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(NotificationSetting::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    /** Store handle, e.g. "my-store" for my-store.myshopify.com */
    public function handle(): string
    {
        return str_replace('.myshopify.com', '', $this->domain);
    }
}
