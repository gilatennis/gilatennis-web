<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleProduct extends Model
{
    protected $fillable = ['name', 'price_label', 'original_price'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
