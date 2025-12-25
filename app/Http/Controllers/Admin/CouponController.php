<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Listar cupones de la tienda
     */
    public function index(Request $request): Response
    {
        $store = $request->user()->store;
        
        $coupons = $store->coupons()
            ->withCount('usages')
            ->with('products')
            ->latest()
            ->paginate(20);

        // Obtener productos activos para el selector
        $products = $store->products()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price']);

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $coupons,
            'products' => $products,
        ]);
    }

    /**
     * Mostrar formulario de creación
     */
    public function create(Request $request): Response
    {
        $store = $request->user()->store;
        $products = $store->products()->where('is_active', true)->get(['id', 'name']);

        return Inertia::render('Admin/Coupons/Create', [
            'products' => $products,
        ]);
    }

    /**
     * Crear nuevo cupón
     */
    public function store(Request $request)
    {
        $store = $request->user()->store;

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['exists:products,id'],
        ]);

        $coupon = $store->coupons()->create($validated);

        // Asociar productos si se especificaron
        if ($request->has('product_ids') && !empty($request->product_ids)) {
            $coupon->products()->attach($request->product_ids);
        } else {
            // Si está vacío, no asociar productos (aplicable a todo el catálogo)
            $coupon->products()->detach();
        }

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Cupón creado exitosamente');
    }

    /**
     * Mostrar detalles del cupón
     */
    public function show(Request $request, Coupon $coupon): Response
    {
        // Verificar que el cupón pertenece a la tienda del usuario
        if ($coupon->store_id !== $request->user()->store_id) {
            abort(403);
        }

        $coupon->load(['products', 'usages.customer', 'usages.order']);

        return Inertia::render('Admin/Coupons/Show', [
            'coupon' => $coupon,
        ]);
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(Request $request, Coupon $coupon): Response
    {
        // Verificar que el cupón pertenece a la tienda del usuario
        if ($coupon->store_id !== $request->user()->store_id) {
            abort(403);
        }

        $store = $request->user()->store;
        $products = $store->products()->where('is_active', true)->get(['id', 'name']);
        $coupon->load('products');

        return Inertia::render('Admin/Coupons/Edit', [
            'coupon' => $coupon,
            'products' => $products,
        ]);
    }

    /**
     * Actualizar cupón
     */
    public function update(Request $request, Coupon $coupon)
    {
        // Verificar que el cupón pertenece a la tienda del usuario
        if ($coupon->store_id !== $request->user()->store_id) {
            abort(403);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code,' . $coupon->id],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['exists:products,id'],
        ]);

        $coupon->update($validated);

        // Sincronizar productos
        if ($request->has('product_ids')) {
            if (empty($request->product_ids)) {
                // Si está vacío, eliminar todas las asociaciones (aplicable a todo el catálogo)
                $coupon->products()->detach();
            } else {
                $coupon->products()->sync($request->product_ids);
            }
        }

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Cupón actualizado exitosamente');
    }

    /**
     * Activar/Desactivar cupón
     */
    public function toggleActive(Request $request, Coupon $coupon)
    {
        // Verificar que el cupón pertenece a la tienda del usuario
        if ($coupon->store_id !== $request->user()->store_id) {
            abort(403);
        }

        $coupon->update([
            'is_active' => !$coupon->is_active,
        ]);

        return back();
    }

    /**
     * Eliminar cupón
     */
    public function destroy(Request $request, Coupon $coupon)
    {
        // Verificar que el cupón pertenece a la tienda del usuario
        if ($coupon->store_id !== $request->user()->store_id) {
            abort(403);
        }

        $coupon->delete();

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Cupón eliminado exitosamente');
    }
}

