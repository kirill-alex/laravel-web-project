<?php

namespace App\Mail;

use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ArticleCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Article $article) {}

    public function build(): static
    {
        return $this->subject('Новая статья: '.$this->article->title)
            ->view('mail.article-created');
    }
}
