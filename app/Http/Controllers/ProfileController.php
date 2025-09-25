<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule; // <-- 1. IMPORTAMOS LA REGLA DE VALIDACIÓN
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // Esta parte actualiza el Nombre y Email del usuario
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        // ===== LÓGICA AJUSTADA PARA LA TIENDA =====
        if ($request->user()->store) {
            $store = $request->user()->store;
            
            // 2. Validamos TODOS los campos de la tienda (logo, redes y ahora el NOMBRE)
            $storeData = $request->validate([
                'store_name' => ['required', 'string', 'max:255', Rule::unique('stores', 'name')->ignore($store->id)],
                'logo' => 'nullable|image|max:1024',
                'facebook_url' => 'nullable|url|max:255',
                'instagram_url' => 'nullable|url|max:255',
                'tiktok_url' => 'nullable|url|max:255',
                'custom_domain' => ['nullable','string','max:255', Rule::unique('stores','custom_domain')->ignore($store->id)],
            ]);

            // Renombramos 'store_name' a 'name' para que coincida con la columna de la base de datos
            $storeData['name'] = $storeData['store_name'];
            unset($storeData['store_name']);

            if ($request->hasFile('logo')) {
                // Borramos el logo anterior si existe
                if ($store->logo_url) {
                    \Storage::disk('public')->delete(str_replace('/storage/', '', $store->logo_url));
                }
                // Guardamos el nuevo logo y añadimos la URL a los datos a guardar
                $path = $request->file('logo')->store('logos', 'public');
                $storeData['logo_url'] = '/storage/' . $path;
            }

            // 3. Actualizamos la tienda con todos los datos validados de una vez
            $store->update($storeData);
        }
        // ====================================================

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}