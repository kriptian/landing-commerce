<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GalleryImageController extends Controller
{
    public function index()
    {
        $store = auth()->user()->store;
        $galleryImages = GalleryImage::where('store_id', $store->id)
            ->orderBy('order')
            ->get();
        
        $products = $store->products()
            ->where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/GalleryImages/Index', [
            'galleryImages' => $galleryImages,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $store = auth()->user()->store;

        $validated = $request->validate([
            'media_type' => 'required|in:image,video',
            'image' => 'required_if:media_type,image|nullable|image|max:10240',
            'video' => 'required_if:media_type,video|nullable|mimes:mp4,webm,ogg|max:51200',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'product_id' => [
                'nullable',
                'exists:products,id',
                function ($attribute, $value, $fail) use ($store) {
                    if ($value && !\App\Models\Product::where('id', $value)->where('store_id', $store->id)->exists()) {
                        $fail('El producto seleccionado no pertenece a tu tienda.');
                    }
                },
            ],
            'show_buy_button' => 'nullable',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
        ]);

        // Procesar show_buy_button - puede venir como string '1'/'0' o boolean
        $showBuyButton = $request->input('show_buy_button');
        $showBuyButtonValue = true; // default
        if ($showBuyButton !== null && $showBuyButton !== '') {
            if (is_bool($showBuyButton)) {
                $showBuyButtonValue = $showBuyButton;
            } elseif (is_string($showBuyButton)) {
                $showBuyButtonValue = in_array(strtolower($showBuyButton), ['1', 'true', 'on', 'yes'], true);
            } else {
                $showBuyButtonValue = (bool) $showBuyButton;
            }
        }

        // Procesar is_active - puede venir como string '1'/'0' o boolean
        $isActive = $request->input('is_active');
        $isActiveValue = true; // default
        if ($isActive !== null && $isActive !== '') {
            if (is_bool($isActive)) {
                $isActiveValue = $isActive;
            } elseif (is_string($isActive)) {
                $isActiveValue = in_array(strtolower($isActive), ['1', 'true', 'on', 'yes'], true);
            } else {
                $isActiveValue = (bool) $isActive;
            }
        }

        $data = [
            'store_id' => $store->id,
            'media_type' => $validated['media_type'],
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
            'show_buy_button' => $showBuyButtonValue,
            'order' => $validated['order'] ?? 0,
            'is_active' => $isActiveValue,
        ];

        if ($validated['media_type'] === 'image' && $request->hasFile('image')) {
            $imagePath = $request->file('image')->store('gallery-images', 'public');
            $data['image_url'] = Storage::url($imagePath);
            $data['video_url'] = null;
        } elseif ($validated['media_type'] === 'video' && $request->hasFile('video')) {
            $videoPath = $request->file('video')->store('gallery-videos', 'public');
            $data['video_url'] = Storage::url($videoPath);
            $data['image_url'] = null;
        }

        GalleryImage::create($data);

        return redirect()->route('admin.gallery-images.index')
            ->with('success', 'Elemento agregado correctamente.');
    }

    public function update(Request $request, GalleryImage $galleryImage)
    {
        // Verificar que la imagen pertenece a la tienda del usuario
        if ($galleryImage->store_id !== $request->user()->store_id) {
            abort(403);
        }

        // Obtener media_type del request o del modelo actual ANTES de validar
        $mediaType = $request->input('media_type');
        if (!$mediaType || $mediaType === '') {
            $mediaType = $galleryImage->media_type ?? 'image';
        }
        
        // Asegurar que media_type esté presente en el request para la validación
        $request->merge(['media_type' => $mediaType]);

        // Validar
        $store = $request->user()->store;
        $validated = $request->validate([
            'media_type' => 'required|in:image,video',
            'image' => 'nullable|image|max:10240',
            'video' => 'nullable|mimes:mp4,webm,ogg|max:51200',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'product_id' => [
                'nullable',
                'exists:products,id',
                function ($attribute, $value, $fail) use ($store) {
                    if ($value && !\App\Models\Product::where('id', $value)->where('store_id', $store->id)->exists()) {
                        $fail('El producto seleccionado no pertenece a tu tienda.');
                    }
                },
            ],
            'show_buy_button' => 'nullable',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
        ]);

        // Obtener product_id validado
        $productId = $validated['product_id'] ?? null;

        // Obtener todos los valores del request - SIMPLIFICADO
        $data = [
            'media_type' => $mediaType,
            'title' => $request->input('title') ? trim($request->input('title')) : null,
            'description' => $request->input('description') ? trim($request->input('description')) : null,
            'product_id' => $productId,
            'order' => $request->input('order') ? (int) $request->input('order') : ($galleryImage->order ?? 0),
        ];

        // Procesar show_buy_button - puede venir como boolean, string 'true'/'false', '1'/'0', etc.
        $showBuyButton = $request->input('show_buy_button');
        if ($showBuyButton !== null) {
            if (is_bool($showBuyButton)) {
                $data['show_buy_button'] = $showBuyButton;
            } elseif (is_string($showBuyButton)) {
                $data['show_buy_button'] = in_array(strtolower($showBuyButton), ['1', 'true', 'on', 'yes'], true);
            } else {
                $data['show_buy_button'] = (bool) $showBuyButton;
            }
        } else {
            $data['show_buy_button'] = $galleryImage->show_buy_button ?? true;
        }

        // Procesar is_active - puede venir como boolean, string 'true'/'false', '1'/'0', etc.
        $isActive = $request->input('is_active');
        if ($isActive !== null) {
            if (is_bool($isActive)) {
                $data['is_active'] = $isActive;
            } elseif (is_string($isActive)) {
                $data['is_active'] = in_array(strtolower($isActive), ['1', 'true', 'on', 'yes'], true);
            } else {
                $data['is_active'] = (bool) $isActive;
            }
        } else {
            $data['is_active'] = $galleryImage->is_active ?? true;
        }

        // Manejar archivos
        if ($data['media_type'] === 'image' && $request->hasFile('image')) {
            // Eliminar archivos anteriores
            if ($galleryImage->image_url) {
                $oldPath = str_replace('/storage/', '', $galleryImage->image_url);
                Storage::disk('public')->delete($oldPath);
            }
            if ($galleryImage->video_url) {
                $oldVideoPath = str_replace('/storage/', '', $galleryImage->video_url);
                Storage::disk('public')->delete($oldVideoPath);
            }
            // Subir nueva imagen
            $imagePath = $request->file('image')->store('gallery-images', 'public');
            $data['image_url'] = Storage::url($imagePath);
            $data['video_url'] = null;
        } elseif ($data['media_type'] === 'video' && $request->hasFile('video')) {
            // Eliminar archivos anteriores
            if ($galleryImage->image_url) {
                $oldPath = str_replace('/storage/', '', $galleryImage->image_url);
                Storage::disk('public')->delete($oldPath);
            }
            if ($galleryImage->video_url) {
                $oldVideoPath = str_replace('/storage/', '', $galleryImage->video_url);
                Storage::disk('public')->delete($oldVideoPath);
            }
            // Subir nuevo video
            $videoPath = $request->file('video')->store('gallery-videos', 'public');
            $data['video_url'] = Storage::url($videoPath);
            $data['image_url'] = null;
        }
        // Si no hay archivo nuevo, no modificar image_url ni video_url

        // Actualizar directamente
        $galleryImage->update($data);

        return redirect()->route('admin.gallery-images.index')
            ->with('success', 'Elemento actualizado correctamente.');
    }

    public function destroy(GalleryImage $galleryImage)
    {
        // Verificar que la imagen pertenece a la tienda del usuario
        if ($galleryImage->store_id !== request()->user()->store_id) {
            abort(403);
        }

        // Eliminar imagen del storage
        if ($galleryImage->image_url) {
            $oldPath = str_replace('/storage/', '', $galleryImage->image_url);
            Storage::disk('public')->delete($oldPath);
        }

        // Eliminar video del storage
        if ($galleryImage->video_url) {
            $oldVideoPath = str_replace('/storage/', '', $galleryImage->video_url);
            Storage::disk('public')->delete($oldVideoPath);
        }

        $galleryImage->delete();

        return redirect()->route('admin.gallery-images.index')
            ->with('success', 'Elemento eliminado correctamente.');
    }
}
