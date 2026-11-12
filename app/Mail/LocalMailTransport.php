<?php

namespace App\Mail;

use App\Models\MailMessage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

class LocalMailTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        MailMessage::create([
            'sender' => array_map(fn (Address $address): string => $address->toString(), $email->getFrom()),
            'recipients' => array_map(fn (Address $address): string => $address->getAddress(), $message->getEnvelope()->getRecipients()),
            'subject' => $email->getSubject() ?? '',
            'html_body' => $email->getHtmlBody(),
            'text_body' => $email->getTextBody(),
        ]);
    }

    public function __toString(): string
    {
        return 'preview';
    }
}
