<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InscriptionRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $parentName;
    public $childName;
    public $remarques;

    public function __construct($parentName, $childName, $remarques = null)
    {
        $this->parentName = $parentName;
        $this->childName = $childName;
        $this->remarques = $remarques;
    }

    public function build()
    {
        return $this->subject('Demande d\'inscription - Décision')
            ->view('emails.inscriptions.rejected');
    }
}
