<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InscriptionWaitingMail extends Mailable
{
    use Queueable, SerializesModels;

    public $parentName;
    public $childName;
    public $position;
    public $niveau;

    public function __construct($parentName, $childName, $position, $niveau)
    {
        $this->parentName = $parentName;
        $this->childName = $childName;
        $this->position = $position;
        $this->niveau = $niveau;
    }

    public function build()
    {
        return $this->subject('Demande d\'inscription - Liste d\'attente')
            ->view('emails.inscriptions.waiting');
    }
}
