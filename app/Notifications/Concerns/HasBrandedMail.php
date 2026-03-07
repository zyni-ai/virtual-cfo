<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

trait HasBrandedMail
{
    protected function brandedGreeting(MailMessage $message, object $notifiable): MailMessage
    {
        return $message->greeting('Hi '.$notifiable->name.',');
    }

    protected function brandedSalutation(MailMessage $message): MailMessage
    {
        return $message->salutation(new HtmlString('Cheers,<br>The '.config('app.name').' Team'));
    }
}
