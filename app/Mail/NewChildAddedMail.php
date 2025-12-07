<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewChildAddedMail extends Mailable
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
        return $this->subject('Nouvel enfant ajouté - SmartKids')
            ->view('emails.new_child_added');
    }
}
