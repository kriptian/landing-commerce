<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TourController extends Controller
{
    /**
     * Marcar el tour como completado para el usuario actual
     */
    public function complete(Request $request)
    {
        $user = Auth::user();
        $section = $request->input('section', 'main');
        
        if ($user) {
            $completedTours = $user->completed_tours ?? [];
            
            if ($section === 'main') {
                // Tour principal - marcar first_login como false
                $user->update([
                    'first_login' => false,
                    'tour_completed_at' => now(),
                    'completed_tours' => array_merge($completedTours, ['main'])
                ]);
            } else {
                // Tour de sección específica
                if (!in_array($section, $completedTours)) {
                    $completedTours[] = $section;
                    $user->update([
                        'completed_tours' => $completedTours
                    ]);
                }
            }
        }

        // Redirigir de vuelta al dashboard sin el parámetro show_tour
        return redirect()->route('dashboard');
    }

    /**
     * Programar recordatorio del tour para más tarde
     */
    public function remindLater(Request $request)
    {
        $user = Auth::user();
        $section = $request->input('section', 'main');
        
        if ($user) {
            $remindLaterTours = $user->remind_later_tours ?? [];
            
            // Agregar el tour a la lista de recordatorios
            if (!in_array($section, $remindLaterTours)) {
                $remindLaterTours[] = $section;
            }
            
            // Limpiar la lista de "nunca mostrar" para este tour específico
            $neverShowTours = $user->never_show_tours ?? [];
            $neverShowTours = array_diff($neverShowTours, [$section]);
            
            // NO marcar como completado, solo actualizar preferencias
            $user->update([
                'remind_later_tours' => $remindLaterTours,
                'never_show_tours' => array_values($neverShowTours)
            ]);
        }

        return redirect()->back();
    }

    /**
     * Nunca más mostrar el tour
     */
    public function neverShow(Request $request)
    {
        $user = Auth::user();
        $section = $request->input('section', 'main');
        
        if ($user) {
            $neverShowTours = $user->never_show_tours ?? [];
            
            // Agregar el tour a la lista de "nunca mostrar"
            if (!in_array($section, $neverShowTours)) {
                $neverShowTours[] = $section;
            }
            
            // Limpiar la lista de recordatorios para este tour específico
            $remindLaterTours = $user->remind_later_tours ?? [];
            $remindLaterTours = array_diff($remindLaterTours, [$section]);
            
            // Si es el tour principal, también marcar first_login como false
            $updateData = [
                'never_show_tours' => $neverShowTours,
                'remind_later_tours' => array_values($remindLaterTours)
            ];
            
            if ($section === 'main') {
                $updateData['first_login'] = false;
            }
            
            $user->update($updateData);
            
            // Debug log
            \Log::info('Tour marked as never show', [
                'user_id' => $user->id,
                'section' => $section,
                'never_show_tours' => $neverShowTours,
                'remind_later_tours' => array_values($remindLaterTours),
                'first_login_updated' => $section === 'main'
            ]);
        }

        return redirect()->back();
    }

    /**
     * Obtener el estado del tour para el usuario actual
     */
    public function status()
    {
        $user = Auth::user();
        
        return response()->json([
            'first_login' => $user ? $user->first_login : false,
            'tour_completed' => $user ? !is_null($user->tour_completed_at) : false,
            'completed_tours' => $user ? ($user->completed_tours ?? []) : [],
            'remind_later_tours' => $user ? ($user->remind_later_tours ?? []) : [],
            'never_show_tours' => $user ? ($user->never_show_tours ?? []) : []
        ]);
    }

    /**
     * Debug: Verificar estado actual del usuario
     */
    public function debug()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'No user authenticated']);
        }
        
        return response()->json([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'first_login' => $user->first_login,
            'completed_tours' => $user->completed_tours ?? [],
            'remind_later_tours' => $user->remind_later_tours ?? [],
            'never_show_tours' => $user->never_show_tours ?? [],
            'should_show_main_tour' => !in_array('main', $user->never_show_tours ?? [])
        ]);
    }
}
