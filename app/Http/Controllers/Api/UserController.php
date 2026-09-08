<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->store->users()->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'area' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'area' => $request->area,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'store_id' => $request->user()->store_id,
        ]);

        return response()->json($user, 201);
    }

    public function show(Request $request, User $user)
    {
        return $request->user()->store->users()->findOrFail($user->id);
    }

    public function update(Request $request, User $user)
    {
        $user = $request->user()->store->users()->findOrFail($user->id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'area' => ['nullable', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return response()->json($user);
    }

    public function destroy(Request $request, User $user)
    {
        $user = $request->user()->store->users()->findOrFail($user->id);

        if ($user->id === $request->user()->store->user_id) {
            return response()->json(['message' => 'No se puede eliminar al propietario de la tienda.'], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
