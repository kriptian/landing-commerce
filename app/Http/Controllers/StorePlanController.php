<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Models\Store;
use App\Mail\StorePlanChangedMail;
use App\Notifications\StorePlanChanged;

class StorePlanController extends Controller
{
    public function upgrade(Request $request)
    {
        $user = $request->user();
        $store = $user?->store;
        if (! $store) {
            abort(403);
        }

        // Solo permitir upgrade a 'negociante' por ahora
        if ($store->plan === 'negociante') {
            return back()->with('success', 'Ya estás en el plan Negociante');
        }

        DB::transaction(function () use ($store) {
            $store->update([
                'plan' => 'negociante',
                'plan_cycle' => $store->plan_cycle ?: 'mensual',
                'plan_started_at' => $store->plan_started_at ?: now(),
                'plan_renews_at' => now()->addMonth(),
            ]);
        });

        // Notificar a súper admins
        try {
            $single = (string) config('app.super_admin_email', env('SUPER_ADMIN_EMAIL'));
            $list = (array) config('app.super_admin_emails', []);
            $emails = collect([$single])->filter()->merge($list)->map(fn($e) => strtolower(trim($e)))->unique();
            if ($emails->isNotEmpty()) {
                $admins = \App\Models\User::whereIn(\DB::raw('LOWER(email)'), $emails->all())->get();
                Notification::send($admins, new StorePlanChanged($store, 'emprendedor', 'negociante'));
            }
        } catch (\Throwable $e) {}

        // Enviar correo al dueño
        try {
            $owner = $store->owner;
            if ($owner) {
                Mail::to($owner->email)->send(new StorePlanChangedMail($store, 'emprendedor', 'negociante'));
            }
        } catch (\Throwable $e) {}

        return back()->with('success', 'Tu plan fue actualizado a Negociante. Un experto te contactará.');
    }
}


