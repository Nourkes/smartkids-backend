<?php
namespace App\Mail;

use App\Models\Inscription;
use App\Models\Paiement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InscriptionAcceptedMail extends \Illuminate\Mail\Mailable
{
    public function __construct(
        public Inscription $inscription,
        public ?Paiement $paiement = null,
        public ?string $deeplink = null,
        public ?string $webFallback = null
    ) {}

    public function build()
    {
        return $this->subject('Inscription acceptée – SmartKids')
            ->view('emails.inscriptions.accepted')
            ->with([
                'inscription' => $this->inscription,
                'paiement'    => $this->paiement,
                'deeplink'    => $this->deeplink,
                'webFallback' => $this->webFallback,
            ]);
    }
}


