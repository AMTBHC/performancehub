<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserController extends Controller
{
   public function store(Request $request)
{
    // 1. Validar (opcional pero recomendado)
    $request->validate([
        'name' => 'required',
        'email' => 'required|email|unique:users',
        'password' => 'required',
        'project_ids' => 'array',
        'solution_ids' => 'array', // Validamos que llegue el array de soluciones
    ]);

    // 2. Crear el usuario
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => $request->role ?? 'user',
    ]);

    // 3. ASIGNAR PROYECTOS (Tabla project_user)
    if ($request->has('project_ids')) {
        $user->projects()->sync($request->project_ids);
    }

    // 4. ASIGNAR SOLUCIONES (Tabla solution_user) <--- ESTO ES LO QUE FALTA
    if ($request->has('solution_ids')) {
        $user->solutions()->sync($request->solution_ids);
    }

    return response()->json(['message' => 'Usuario creado y permisos asignados'], 201);
}
}