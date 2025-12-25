<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:customer');
    }

    /**
     * Mostrar área de cliente (Mi Cuenta)
     */
    public function index(Store $store): Response
    {
        $customer = Auth::guard('customer')->user();
        
        // Cargar órdenes con items y productos
        $orders = $customer->orders()
            ->with(['items.product', 'items.variant', 'address', 'coupon'])
            ->latest()
            ->paginate(10);

        // Cargar direcciones
        $addresses = $customer->addresses()->latest()->get();

        // Estadísticas
        $stats = [
            'total_orders' => $customer->orders()->count(),
            'total_spent' => $customer->orders()->sum('total_price'),
            'default_address' => $customer->defaultAddress,
        ];

        return Inertia::render('Customer/Account', [
            'store' => $store,
            'customer' => $customer,
            'orders' => $orders,
            'addresses' => $addresses,
            'stats' => $stats,
        ]);
    }

    /**
     * Actualizar perfil del cliente
     */
    public function updateProfile(Request $request, Store $store)
    {
        $customer = Auth::guard('customer')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $customer->update($validated);

        return back()->with('success', 'Perfil actualizado exitosamente');
    }

    /**
     * Cambiar contraseña
     */
    public function updatePassword(Request $request, Store $store)
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $customer = Auth::guard('customer')->user();

        if (!Hash::check($validated['current_password'], $customer->password)) {
            return back()->withErrors(['current_password' => 'La contraseña actual es incorrecta']);
        }

        $customer->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Contraseña actualizada exitosamente');
    }

    /**
     * Mostrar pedidos del cliente
     */
    public function orders(Store $store): Response
    {
        $customer = Auth::guard('customer')->user();
        
        // Cargar órdenes con items y productos
        $orders = $customer->orders()
            ->with(['items.product', 'items.variant', 'address', 'coupon'])
            ->latest()
            ->paginate(10);

        return Inertia::render('Customer/Orders', [
            'store' => $store,
            'customer' => $customer,
            'orders' => $orders,
        ]);
    }
}

