<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StorePlanController extends Controller
{
    public function upgrade(Request $request)
    {
        $user = $request->user();
        $store = $user?->store;
        if (! $store) {
            abort(403);
        }

        if ($store->plan === 'negociante') {
            return back()->with('success', 'Ya estás en el plan Negociante');
        }

        return back()->withErrors([
            'plan' => 'El cambio a un plan pagado requiere completar el proceso de pago.',
        ]);
    }
}
