<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

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
            'address_two' => 'nullable|string|max:255',
            'address_three' => 'nullable|string|max:255',
            'address_four' => 'nullable|string|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'tiktok_url' => 'nullable|url|max:255',
        ]);

        $store = $request->user()->store;

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $validated['logo_url'] = '/storage/'.$path;
        }

        $store->update($validated);

        return Redirect::route('dashboard');
    }
}
