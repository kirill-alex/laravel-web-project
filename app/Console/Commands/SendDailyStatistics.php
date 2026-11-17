<?php

namespace App\Console\Commands;

use App\Mail\DailyStatisticsMail;
use App\Models\ArticleView;
use App\Models\Comment;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendDailyStatistics extends Command
{
    protected $signature = 'statistics:send-daily {--date= : Report date in YYYY-MM-DD format}';

    protected $description = 'Send moderators the number of article views and new comments for a day';

    public function handle(): int
    {
        $timezone = config('statistics.timezone', 'Europe/Moscow');
        $date = $this->option('date') ?? CarbonImmutable::now($timezone)->toDateString();
        if (Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->fails()) {
            $this->error('Укажите корректную дату в формате YYYY-MM-DD.');

            return self::FAILURE;
        }

        $day = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $start = $day->utc();
        $end = $day->addDay()->utc();
        $viewsCount = ArticleView::where('created_at', '>=', $start)->where('created_at', '<', $end)->count();
        $commentsCount = Comment::where('created_at', '>=', $start)->where('created_at', '<', $end)->count();
        $recipients = User::whereHas('role', fn (Builder $query) => $query->where('name', Role::MODERATOR))
            ->pluck('email')->unique();

        if ($recipients->isEmpty()) {
            $this->warn('Модераторы не найдены. Отчёт не отправлен.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send(new DailyStatisticsMail($date, $timezone, $viewsCount, $commentsCount));
            } catch (TransportExceptionInterface $exception) {
                report($exception);
                $failed = true;
            }
        }

        if ($failed) {
            $this->error('Не все отчёты отправлены. Проверьте почтовый транспорт.');

            return self::FAILURE;
        }

        $this->info('Отчёт за '.$date.' отправлен. Просмотры: '.$viewsCount.', новые комментарии: '.$commentsCount.'.');

        return self::SUCCESS;
    }
}
