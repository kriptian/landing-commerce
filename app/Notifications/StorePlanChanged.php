<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class StorePlanChanged extends Notification
{
    use Queueable;

    public function __construct(public Store $store, public string $from, public string $to)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cambio de plan de tienda')
            ->line("La tienda {$this->store->name} cambió de plan {$this->from} a {$this->to}.")
            ->line('Fecha: ' . now()->format('Y-m-d H:i'))
            ->action('Ver tiendas', route('super.stores.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'from' => $this->from,
            'to' => $this->to,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}


