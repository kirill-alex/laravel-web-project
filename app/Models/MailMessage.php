<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailMessage extends Model
{
    protected $fillable = ['sender', 'recipients', 'subject', 'html_body', 'text_body'];

    protected function casts(): array
    {
        return ['sender' => 'array', 'recipients' => 'array'];
    }
}
