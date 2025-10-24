<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class NewStoreCreated extends Notification
{
    use Queueable;

    public function __construct(public Store $store)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $store = $this->store;
        return (new MailMessage)
            ->subject('Nueva tienda creada')
            ->greeting('¡Nueva tienda creada!')
            ->line('Nombre: ' . ($store->name ?? 'N/D'))
            ->line('ID: ' . ($store->id ?? 'N/D'))
            ->action('Abrir SuperStores', url(route('super.stores.index')))
            ->line('Contacta al cliente para el onboarding.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
        ];
    }
}


