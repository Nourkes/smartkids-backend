<?php
// app/Notifications/FirstLoginCodeMail.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class FirstLoginCodeMail extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    public function via($notifiable) { return ['mail']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Code de vérification SmartKids')
            ->line("Votre code est : {$this->code}")
            ->line('Il expire dans 10 minutes.');
    }
}
