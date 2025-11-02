<?php
// app/Mail/InscriptionReceivedMail.php
namespace App\Mail;

use App\Models\Inscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InscriptionReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Inscription $inscription) {}

    public function build()
    {
        return $this->subject('Votre demande d’inscription a bien été reçue')
            ->markdown('emails.inscriptions.received', [
                'i' => $this->inscription,
            ]);
    }
}
