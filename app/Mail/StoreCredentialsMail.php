<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $storeName,
        public string $email,
        public string $plainPassword,
    ) {}

    public function build(): self
    {
        return $this->subject('¡Tu tienda fue creada con éxito!')
            ->view('emails.store_credentials')
            ->with([
                'storeName' => $this->storeName,
                'email' => $this->email,
                'plainPassword' => $this->plainPassword,
                'loginUrl' => route('login'),
            ]);
    }
}


