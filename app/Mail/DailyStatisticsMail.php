<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class DailyStatisticsMail extends Mailable
{
    public function __construct(
        public string $date,
        public string $timezone,
        public int $viewsCount,
        public int $commentsCount,
    ) {}

    public function build(): static
    {
        return $this->subject('Статистика сайта за '.$this->date)
            ->view('mail.daily-statistics');
    }
}
