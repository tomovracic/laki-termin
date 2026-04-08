<?php

declare(strict_types=1);

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $invitationUrl,
        private readonly CarbonInterface $expiresAt,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Pozivnica za registraciju')
            ->greeting('Pozdrav!')
            ->line('Pozvani ste u aplikaciju. Kliknite na gumb ispod kako biste dovrsili registraciju.')
            ->action('Dovrsi registraciju', $this->invitationUrl)
            ->line(sprintf(
                'Pozivnica vrijedi do %s.',
                $this->expiresAt->format('d.m.Y. H:i'),
            ))
            ->line('Ako je pozivnica istekla, obratite se administratoru.');
    }
}
