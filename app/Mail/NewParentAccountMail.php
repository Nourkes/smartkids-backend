<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewParentAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public $parentName;
    public $childName;
    public $email;
    public $password;
    public $className;
    public $year;

    public function __construct($parentName, $childName, $email, $password, $className, $year)
    {
        $this->parentName = $parentName;
        $this->childName = $childName;
        $this->email = $email;
        $this->password = $password;
        $this->className = $className;
        $this->year = $year;
    }

    public function build()
    {
        return $this->subject('Bienvenue à SmartKids - Votre compte a été créé')
            ->view('emails.new_parent_account');
    }
}
