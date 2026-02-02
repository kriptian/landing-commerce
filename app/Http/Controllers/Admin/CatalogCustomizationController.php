<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class CatalogCustomizationController extends Controller
{
    public function __construct()
    {
        $this->middleware('plan:negociante');
    }

    /**
     * Muestra la página de personalización del catálogo.
     */
    public function index(Request $request)
    {
        $store = $request->user()->store;

        return Inertia::render('Admin/CatalogCustomization/Index', [
            'store' => [
                'id' => $store->id,
                'gallery_type' => $store->gallery_type ?? 'products',
                'gallery_show_buy_button' => $store->gallery_show_buy_button ?? true,
                'catalog_use_default' => $store->catalog_use_default ?? true,
                'catalog_button_color' => $store->catalog_button_color ?? '#1F2937',
                'catalog_promo_banner_color' => $store->catalog_promo_banner_color ?? '#DC2626',
                'catalog_promo_banner_text_color' => $store->catalog_promo_banner_text_color ?? '#FFFFFF',
                'catalog_variant_button_color' => $store->catalog_variant_button_color ?? '#2563EB',
                'catalog_purchase_button_color' => $store->catalog_purchase_button_color ?? '#2563EB',
                'catalog_cart_bubble_color' => $store->catalog_cart_bubble_color ?? '#2563EB',
                'catalog_social_button_color' => $store->catalog_social_button_color ?? '#2563EB',
                'catalog_product_template' => $store->catalog_product_template ?? 'default',
                'catalog_show_buy_button' => $store->catalog_show_buy_button ?? false,
                'catalog_header_style' => $store->catalog_header_style ?? 'default',
                'catalog_header_bg_color' => $store->catalog_header_bg_color ?? '#FFFFFF',
                'catalog_header_text_color' => $store->catalog_header_text_color ?? '#1F2937',
                'catalog_button_bg_color' => $store->catalog_button_bg_color ?? '#2563EB',
                'catalog_button_text_color' => $store->catalog_button_text_color ?? '#FFFFFF',
                'catalog_body_bg_color' => $store->catalog_body_bg_color ?? '#FFFFFF',
                'catalog_body_text_color' => $store->catalog_body_text_color ?? '#1F2937',
                'catalog_input_bg_color' => $store->catalog_input_bg_color ?? '#FFFFFF',
                'catalog_input_text_color' => $store->catalog_input_text_color ?? '#1F2937',
                'delivery_cost' => $store->delivery_cost ?? 0,
                'delivery_cost_active' => $store->delivery_cost_active ?? false,
                'cookie_consent_active' => $store->cookie_consent_active ?? false,
                'privacy_policy_text' => $store->privacy_policy_text ?? null,
            ],
        ]);
    }

    /**
     * Actualiza la personalización del catálogo.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'gallery_type' => 'required|in:products,custom',
            'gallery_show_buy_button' => 'required|boolean',
            'catalog_use_default' => 'required|boolean',
            'catalog_button_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_promo_banner_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_promo_banner_text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_variant_button_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_purchase_button_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_cart_bubble_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_social_button_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_product_template' => 'required|in:big,default,full_text',
            'catalog_show_buy_button' => 'required|boolean',
            'catalog_header_style' => 'required|in:default,fit,banner_logo',
            'catalog_header_bg_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_header_text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_button_bg_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_button_text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_body_bg_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_body_text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_input_bg_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'catalog_input_text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'delivery_cost' => 'nullable|numeric|min:0',
            'delivery_cost_active' => 'required|boolean',
            'cookie_consent_active' => 'boolean',
            'privacy_policy_text' => 'nullable|string',
        ]);

        $store = $request->user()->store;
        
        \Illuminate\Support\Facades\Log::info('CatalogCustomization update payload:', $validated);
        
        // Si usa modo por defecto, limpiamos los colores personalizados pero mantenemos layout
        if ($validated['catalog_use_default']) {
            $validated['catalog_button_color'] = null;
            $validated['catalog_promo_banner_color'] = null;
            $validated['catalog_promo_banner_text_color'] = null;
            $validated['catalog_variant_button_color'] = null;
            $validated['catalog_purchase_button_color'] = null;
            $validated['catalog_cart_bubble_color'] = null;
            $validated['catalog_social_button_color'] = null;
            $validated['catalog_header_bg_color'] = null;
            $validated['catalog_header_text_color'] = null;
            $validated['catalog_button_bg_color'] = null;
            $validated['catalog_button_text_color'] = null;
            $validated['catalog_body_bg_color'] = null;
            $validated['catalog_body_text_color'] = null;
            $validated['catalog_input_bg_color'] = null;
            $validated['catalog_input_text_color'] = null;
        }

        $store->update($validated);

        return Redirect::route('admin.catalog-customization.index')
            ->with('success', 'Personalización del catálogo actualizada correctamente.');
    }
}

