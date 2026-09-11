<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorePaymentReminderController extends Controller
{
    public function update(Request $request, Store $store)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'mode' => ['required', Rule::in(['repeatable', 'persistent'])],
            'repeat_interval' => ['nullable', 'required_if:mode,repeatable', 'integer', 'min:1', 'max:10080'],
            'repeat_unit' => ['nullable', 'required_if:mode,repeatable', Rule::in(['minutes', 'hours', 'days'])],
            'pause_catalog' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        $reminder = $store->paymentReminder()->firstOrNew();

        if ($request->boolean('remove_image') && $reminder->image_path) {
            Storage::disk('local')->delete($reminder->image_path);
            $reminder->image_path = null;
        }

        if ($request->hasFile('image')) {
            if ($reminder->image_path) {
                Storage::disk('local')->delete($reminder->image_path);
            }
            $reminder->image_path = $request->file('image')->store("payment-reminders/{$store->id}", 'local');
        }

        $reminder->fill([
            'message' => $validated['message'],
            'mode' => $validated['mode'],
            'repeat_interval' => $validated['mode'] === 'repeatable' ? $validated['repeat_interval'] : null,
            'repeat_unit' => $validated['mode'] === 'repeatable' ? $validated['repeat_unit'] : null,
            'pause_catalog' => $validated['pause_catalog'],
            'active' => $validated['active'],
            'activated_at' => $validated['active'] ? now() : null,
        ])->save();

        return back()->with('success', $validated['active']
            ? 'Recordatorio actualizado y activado.'
            : 'La configuracion se guardo sin activar el recordatorio.');
    }

    public function destroy(Store $store)
    {
        $store->paymentReminder()->update([
            'active' => false,
            'activated_at' => null,
        ]);

        return back()->with('success', 'Pago confirmado. La alerta fue retirada y el catalogo esta disponible nuevamente.');
    }

    public function superImage(Request $request, Store $store): StreamedResponse
    {
        return $this->imageResponse($store);
    }

    public function adminImage(Request $request): StreamedResponse
    {
        $store = $request->user()?->store;
        abort_unless($store?->paymentReminder?->active, 404);

        return $this->imageResponse($store);
    }

    public function adminStatus(Request $request)
    {
        $reminder = $request->user()?->store?->paymentReminder;

        return response()->json([
            'active' => (bool) $reminder?->active,
            'id' => $reminder?->active ? $reminder->id : null,
            'version' => $reminder?->active ? $reminder->updated_at->getTimestamp() : null,
        ]);
    }

    private function imageResponse(Store $store): StreamedResponse
    {
        $path = $store->paymentReminder?->image_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
