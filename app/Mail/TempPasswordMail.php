<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TempPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $email;
    public string $tempPassword;

    public function __construct(string $name, string $email, string $tempPassword)
    {
        $this->name         = $name;
        $this->email        = $email;
        $this->tempPassword = $tempPassword;
    }

    public function build()
    {
        return $this->subject('Votre mot de passe provisoire - SmartKids')
                    ->view('emails.temp_password')
                    ->with([
                        'name'         => $this->name,
                        'email'        => $this->email,
                        'tempPassword' => $this->tempPassword,
                    ]);
    }
}
