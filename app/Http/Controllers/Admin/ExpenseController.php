<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function store(Request $request)
    {
        // Validar que el usuario tenga permiso para crear gastos si es necesario
        // Por ahora asumimos que si puede acceder al POS, puede crear gastos
        // o se manejará por middleware en la ruta

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $store = $user->store;

        if (!$store) {
            return response()->json(['message' => 'Usuario no asociado a una tienda.'], 403);
        }

        $expense = Expense::create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'amount' => $request->amount,
            'description' => $request->description,
            'expense_date' => now(),
        ]);

        return redirect()->back()->with('success', 'Gasto registrado correctamente.');
    }
}
