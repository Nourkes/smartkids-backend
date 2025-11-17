<?php
// app/Mail/FirstMonthPaymentMail.php
namespace App\Mail;

use App\Models\Inscription;
use App\Models\Paiement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FirstMonthPaymentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Inscription $inscription,
        public Paiement $paiement,
        public string $deeplink,
        public string $webFallback
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SmartKids – Paiement du 1er mois'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.first_month_payment',
            with: [
                'i' => $this->inscription,
                'p' => $this->paiement,
                'deeplink' => $this->deeplink,
                'webFallback' => $this->webFallback,
            ]
        );
    }
}
