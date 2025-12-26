<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:gestionar usuarios');
    }

    public function index(Request $request)
    {
        $store = $request->user()->store;
        
        return Inertia::render('Users/Index', [
            'users' => $store->users()->with('roles')->get(),
            'roles' => $store->roles()->with('permissions')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Users/Create', [
            // AJUSTE: Buscamos solo los roles de la tienda actual
            'roles' => auth()->user()->store->roles()->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $store = $request->user()->store;
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Email único solo para esta tienda
                Rule::unique('users')->where(function ($query) use ($store) {
                    return $query->where('store_id', $store->id);
                }),
            ],
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
        ]);
        
        // Limitar por cupo de usuarios de la tienda
        $currentUsers = $store->users()->count();
        $maxUsers = $store->max_users ?? 0;
        if ($maxUsers > 0 && $currentUsers >= $maxUsers) {
            return back()->withErrors(['limit' => 'Has alcanzado el límite de usuarios permitidos para tu plan.']);
        }

        $user = $store->users()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        
        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')->with('success', '¡Usuario creado con éxito!');
    }

    public function edit(User $user)
    {
        if ($user->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        return Inertia::render('Users/Edit', [
            'user' => $user->load('roles'),
            // AJUSTE: Hacemos lo mismo aquí
            'roles' => auth()->user()->store->roles()->pluck('name'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        if ($user->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        $store = $request->user()->store;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                // Email único solo para esta tienda, ignorando el usuario actual
                Rule::unique('users')->where(function ($query) use ($store) {
                    return $query->where('store_id', $store->id);
                })->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);
        $user->syncRoles($validated['role']);

        return redirect()->route('admin.users.index');
    }

    public function destroy(User $user, Request $request)
    {
        // Regla de seguridad: No podés eliminarte a vos mismo
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'No puedes eliminar tu propio usuario.']);
        }
        // Regla de seguridad: Solo podés borrar usuarios de tu propia tienda
        if ($user->store_id !== $request->user()->store_id) {
            abort(403);
        }

        $user->delete();
        
        return back();
    }
}