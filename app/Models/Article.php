<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'preview_image', 'full_image'];

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
