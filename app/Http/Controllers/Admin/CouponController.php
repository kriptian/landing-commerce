<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:gestionar cupones');
    }

    /**
     * Listar cupones de la tienda
     */
    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        $coupons = $store->coupons()
            ->withCount('usages')
            ->with('products:id,name')
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
            'product_ids.*' => [
                Rule::exists('products', 'id')->where('store_id', $store->id),
            ],
        ]);

        $productIds = $validated['product_ids'] ?? [];
        unset($validated['product_ids']);
        $coupon = $store->coupons()->create($validated);

        // Asociar productos si se especificaron
        if ($productIds !== []) {
            $coupon->products()->attach($productIds);
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
        $coupon = $request->user()->store->coupons()->findOrFail($coupon->id);

        $coupon->load(['products:id,name', 'usages.customer', 'usages.order']);

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
        $coupon = $request->user()->store->coupons()->findOrFail($coupon->id);

        $store = $request->user()->store;
        $products = $store->products()->where('is_active', true)->get(['id', 'name']);
        $coupon->load('products:id,name');

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
        $coupon = $request->user()->store->coupons()->findOrFail($coupon->id);
        $store = $request->user()->store;

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code,'.$coupon->id],
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
            'product_ids.*' => [
                Rule::exists('products', 'id')->where('store_id', $store->id),
            ],
        ]);

        $productIds = $validated['product_ids'] ?? null;
        unset($validated['product_ids']);
        $coupon->update($validated);

        // Sincronizar productos
        if ($productIds !== null) {
            if ($productIds === []) {
                // Si está vacío, eliminar todas las asociaciones (aplicable a todo el catálogo)
                $coupon->products()->detach();
            } else {
                $coupon->products()->sync($productIds);
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
        $coupon = $request->user()->store->coupons()->findOrFail($coupon->id);

        $coupon->update([
            'is_active' => ! $coupon->is_active,
        ]);

        return back();
    }

    /**
     * Eliminar cupón
     */
    public function destroy(Request $request, Coupon $coupon)
    {
        // Verificar que el cupón pertenece a la tienda del usuario
        $coupon = $request->user()->store->coupons()->findOrFail($coupon->id);

        $coupon->delete();

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Cupón eliminado exitosamente');
    }
}
