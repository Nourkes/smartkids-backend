<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Enfant;
use Carbon\Carbon;

class PresenceMarquee extends Notification
{
    use Queueable;

    protected $enfant;
    protected $statut;
    protected $date;

    public function __construct(Enfant $enfant, string $statut, Carbon $date)
    {
        $this->enfant = $enfant;
        $this->statut = $statut;
        $this->date = $date;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $statutLibelle = $this->statut === 'present' ? 'présent(e)' : 'absent(e)';
        
        return (new MailMessage)
            ->subject('Présence de ' . $this->enfant->prenom)
            ->greeting('Bonjour,')
            ->line("Votre enfant {$this->enfant->prenom} {$this->enfant->nom} a été marqué(e) {$statutLibelle} le {$this->date->format('d/m/Y')}.")
            ->line('Vous pouvez consulter l\'historique des présences depuis votre espace parent.')
            ->salutation('Cordialement, L\'équipe éducative');
    }

    public function toArray($notifiable): array
    {
        return [
            'enfant_id' => $this->enfant->id,
            'enfant_nom' => $this->enfant->prenom . ' ' . $this->enfant->nom,
            'statut' => $this->statut,
            'date' => $this->date->format('Y-m-d'),
            'message' => "Présence de {$this->enfant->prenom} marquée : {$this->statut}"
        ];
    }
}