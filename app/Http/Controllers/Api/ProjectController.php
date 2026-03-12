<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        // Obtenemos al usuario autenticado (vía Sanctum)
        $user = $request->user();

        // Traemos sus proyectos con sus soluciones cargadas (Eager Loading)
        $projects = $user->projects()->with('solutions')->get();

        return response()->json([
            'status' => 'success',
            'data' => $projects
        ]);
    }

    public function getSolutions($projectId)
    {
        // Opcional: Validar que el usuario tenga acceso a este proyecto
        $project = auth()->user()->projects()->findOrFail($projectId);
        
        return response()->json($project->solutions);
    }

    public function destroy($id)
{
    $project = Project::findOrFail($id);
    // Eliminar las relaciones en la tabla pivote antes de borrar el proyecto
    $project->users()->detach(); 
    $project->delete();
    return response()->json(['message' => 'Proyecto eliminado']);
}
}