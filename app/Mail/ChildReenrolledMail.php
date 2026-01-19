<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChildReenrolledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $parentName;
    public $childName;
    public $className;
    public $year;

    public function __construct($parentName, $childName, $className, $year)
    {
        $this->parentName = $parentName;
        $this->childName = $childName;
        $this->className = $className;
        $this->year = $year;
    }

    public function build()
    {
        return $this->subject('Réinscription confirmée - SmartKids')
            ->view('emails.child_reenrolled');
    }
}
