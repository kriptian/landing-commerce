<?php

namespace App\Mail;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StorePlanChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Store $store;
    public string $fromPlan;
    public string $toPlan;

    public function __construct(Store $store, string $fromPlan, string $toPlan)
    {
        $this->store = $store;
        $this->fromPlan = $fromPlan;
        $this->toPlan = $toPlan;
    }

    public function build(): self
    {
        return $this->subject('Cambiaste tu plan de tienda')
            ->view('emails.store_plan_changed')
            ->with([
                'storeName' => $this->store->name,
                'from' => $this->fromPlan,
                'to' => $this->toPlan,
            ]);
    }
}


