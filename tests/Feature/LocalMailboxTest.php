<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalMailboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_article_delivers_a_rendered_message_to_the_local_inbox(): void
    {
        config([
            'mail.default' => 'preview',
            'mail.moderator_address' => 'moderator@example.test',
            'mail.from.address' => 'noreply@example.test',
        ]);
        $this->actingAs(User::factory()->moderator()->create());

        $this->post('/articles', ['title' => 'Local mail test', 'description' => 'Text for the moderator'])
            ->assertRedirect('/articles')->assertSessionHas('status');

        $this->assertDatabaseCount('mail_messages', 1);
        $message = MailMessage::firstOrFail();
        $article = Article::where('title', 'Local mail test')->firstOrFail();
        $this->assertSame(['moderator@example.test'], $message->recipients);
        $this->assertSame('Новая статья: Local mail test', $message->subject);
        $this->assertStringContainsString('Text for the moderator', $message->html_body);
        $this->assertStringContainsString(route('articles.show', $article), $message->html_body);
        $this->get(route('mailbox.index'))->assertOk()->assertSee($message->subject)
            ->assertSee('moderator@example.test');
        $this->get(route('mailbox.show', $message))->assertOk()->assertSee($message->subject)
            ->assertSee('sandbox', false)->assertSee('Text for the moderator');
    }

    public function test_guests_and_readers_cannot_access_the_test_mailbox(): void
    {
        $message = MailMessage::create([
            'sender' => ['noreply@example.test'],
            'recipients' => ['moderator@example.test'],
            'subject' => 'Private notification',
            'text_body' => 'Mail body',
        ]);

        $this->get(route('mailbox.index'))->assertRedirect(route('login'));
        $this->get(route('mailbox.show', $message))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('mailbox.index'))->assertForbidden();
        $this->get(route('mailbox.show', $message))->assertForbidden();
        $this->get('/')->assertOk()->assertDontSee('Тестовая почта');
    }

    public function test_an_empty_inbox_explains_how_to_receive_a_test_message(): void
    {
        $this->actingAs(User::factory()->moderator()->create());

        $this->get(route('mailbox.index'))->assertOk()
            ->assertSee('Писем пока нет.')->assertSee('Создайте статью');
        $this->get('/')->assertOk()->assertSee('Тестовая почта');
    }

    public function test_the_test_mailbox_is_paginated_newest_first(): void
    {
        $this->actingAs(User::factory()->moderator()->create());
        foreach (range(1, 11) as $number) {
            MailMessage::create([
                'sender' => ['noreply@example.test'],
                'recipients' => ['moderator@example.test'],
                'subject' => 'Notification '.$number,
                'text_body' => 'Body',
            ]);
        }

        $this->get(route('mailbox.index'))->assertOk()->assertViewHas('messages', function ($messages): bool {
            return $messages->count() === 10 && $messages->first()->subject === 'Notification 11';
        });
        $this->get(route('mailbox.index').'?page=2')->assertOk()
            ->assertViewHas('messages', function ($messages): bool {
                return $messages->count() === 1 && $messages->first()->subject === 'Notification 1';
            });
    }
}
