<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class StoreSetupController extends Controller
{
    /**
     * Muestra el formulario de configuración de la tienda.
     */
    public function create(Request $request)
    {
        return Inertia::render('Store/Setup', [
            'store' => $request->user()->store,
        ]);
    }

    /**
     * Guarda la información de la tienda.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|max:1024', // 1MB Max
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'tiktok_url' => 'nullable|url|max:255',
            'plan' => 'required|in:emprendedor,negociante',
            'plan_cycle' => 'nullable|in:mensual,anual',
        ]);

        $store = $request->user()->store;

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $validated['logo_url'] = '/storage/' . $path;
        }

        // Si se cambia plan, marcamos fechas básicas
        if (isset($validated['plan']) && $validated['plan'] !== $store->plan) {
            $validated['plan_started_at'] = now();
        }
        $store->update($validated);

        return Redirect::route('dashboard');
    }
}