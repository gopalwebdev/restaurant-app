<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a user the one-time code that signs them in.
 *
 * It goes onto the mail queue, which Horizon works ahead of everything else: a
 * code that arrives after it has expired is no use to anyone.
 */
class SignInCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $expiresInMinutes,
    ) {
        $this->onQueue('mail');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in code')
            ->greeting('Your sign-in code')
            ->line('Enter this code to finish signing in:')
            ->line("**{$this->code}**")
            ->line("It expires in {$this->expiresInMinutes} minutes and can only be used once.")
            ->line('If you did not ask to sign in, you can ignore this email.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'expires_in_minutes' => $this->expiresInMinutes,
        ];
    }
}
