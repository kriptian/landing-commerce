<?php

namespace App\Http\Requests\Auth;

use App\Models\Store;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Buscar la tienda por nombre (case-insensitive) o por slug
        $storeName = trim($this->input('store_name'));
        $storeSlug = Str::slug($storeName);
        
        $store = Store::where(function($query) use ($storeName, $storeSlug) {
            $query->whereRaw('LOWER(name) = LOWER(?)', [$storeName])
                  ->orWhere('slug', $storeSlug);
        })->first();

        if (!$store) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'store_name' => 'La tienda especificada no existe.',
            ]);
        }

        // Buscar el usuario dentro del contexto de la tienda específica
        $credentials = $this->only('email', 'password');
        
        // Buscar el usuario que pertenece a esta tienda específica
        $user = \App\Models\User::where('email', $credentials['email'])
            ->where('store_id', $store->id)
            ->first();

        if (!$user) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
            ]);
        }

        // Validar la contraseña del usuario encontrado
        if (!Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Verificación final: asegurar que el usuario pertenece a la tienda correcta
        // (doble verificación por seguridad)
        if ($user->store_id !== $store->id) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con esta tienda.',
            ]);
        }

        // Autenticar al usuario
        Auth::guard('web')->login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     * Incluye el nombre de la tienda para que el rate limiting sea por tienda y email.
     */
    public function throttleKey(): string
    {
        $storeName = Str::lower(trim($this->string('store_name')));
        $email = Str::lower($this->string('email'));
        return Str::transliterate($storeName.'|'.$email.'|'.$this->ip());
    }
}
