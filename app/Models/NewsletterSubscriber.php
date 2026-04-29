<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = ['email', 'subscribed_at', 'unsubscribed_at'];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('unsubscribed_at');
    }

    public function isSubscribed(): bool
    {
        return $this->unsubscribed_at === null;
    }

    public function unsubscribe(): void
    {
        $this->update(['unsubscribed_at' => now()]);
    }
}
