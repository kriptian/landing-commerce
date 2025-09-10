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
        // Buscamos los usuarios de la tienda
        $users = $request->user()->store->users()->with('roles')->get();

        // Buscamos también todos los roles
        $roles = Role::with('permissions')->get();

        // Pasamos ambas colecciones a la vista
        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles, // <-- Le mandamos también los roles
        ]);
    }

    public function create()
    {
        return Inertia::render('Users/Create', [
            'roles' => Role::all()->pluck('name')
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
        ]);
        
        $user = $request->user()->store->users()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        
        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index');
    }

    /**
     * Muestra el formulario para editar un usuario.
     */
    public function edit(User $user)
    {
        // Nos aseguramos de que solo se puedan editar usuarios de la misma tienda
        if ($user->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        return Inertia::render('Users/Edit', [
            'user' => $user->load('roles'), // Cargamos el rol actual del usuario
            'roles' => Role::all()->pluck('name'),
        ]);
    }

    /**
     * Actualiza un usuario.
     */
    public function update(Request $request, User $user)
    {
        if ($user->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        // Solo actualizamos la contraseña si el campo no viene vacío
        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);
        $user->syncRoles($validated['role']); // Sincroniza el rol (quita los viejos y pone el nuevo)

        return redirect()->route('admin.users.index');
    }

    /**
     * Elimina un usuario.
     */
    public function destroy(User $user)
    {
        // Regla de seguridad: No podés eliminarte a vos mismo
        if ($user->id === auth()->id()) {
            return back()->withErrors(['delete' => 'No puedes eliminar tu propio usuario.']);
        }

        if ($user->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        $user->delete();
        
        return back();
    }
}