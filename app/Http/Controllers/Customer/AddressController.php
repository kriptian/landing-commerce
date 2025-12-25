<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:customer');
    }

    /**
     * Crear nueva dirección
     */
    public function store(Request $request, Store $store)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:500'],
            'address_line_2' => ['nullable', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = Auth::guard('customer')->user();

        DB::transaction(function () use ($customer, $validated) {
            // Si se marca como predeterminada, quitar la marca de otras direcciones
            if ($validated['is_default'] ?? false) {
                $customer->addresses()->update(['is_default' => false]);
            }

            // Si es la primera dirección, marcarla como predeterminada
            if ($customer->addresses()->count() === 0) {
                $validated['is_default'] = true;
            }

            $customer->addresses()->create($validated);
        });

        return back()->with('success', 'Dirección agregada exitosamente');
    }

    /**
     * Actualizar dirección
     */
    public function update(Request $request, Store $store, Address $address)
    {
        // Verificar que la dirección pertenece al cliente autenticado
        $customer = Auth::guard('customer')->user();
        if ($address->customer_id !== $customer->id) {
            abort(403);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:500'],
            'address_line_2' => ['nullable', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($customer, $address, $validated) {
            // Si se marca como predeterminada, quitar la marca de otras direcciones
            if ($validated['is_default'] ?? false) {
                $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->update($validated);
        });

        return back()->with('success', 'Dirección actualizada exitosamente');
    }

    /**
     * Eliminar dirección
     */
    public function destroy(Store $store, Address $address)
    {
        // Verificar que la dirección pertenece al cliente autenticado
        $customer = Auth::guard('customer')->user();
        if ($address->customer_id !== $customer->id) {
            abort(403);
        }

        // No permitir eliminar si es la única dirección
        if ($customer->addresses()->count() === 1) {
            return back()->withErrors(['address' => 'No puedes eliminar tu única dirección']);
        }

        $address->delete();

        // Si era la predeterminada, marcar otra como predeterminada
        if ($address->is_default) {
            $customer->addresses()->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Dirección eliminada exitosamente');
    }

    /**
     * Establecer dirección como predeterminada
     */
    public function setDefault(Store $store, Address $address)
    {
        $customer = Auth::guard('customer')->user();
        if ($address->customer_id !== $customer->id) {
            abort(403);
        }

        DB::transaction(function () use ($customer, $address) {
            $customer->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return back()->with('success', 'Dirección predeterminada actualizada');
    }
}

