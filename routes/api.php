<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;

use App\Models\Project;
use App\Models\User;
use App\Models\Solution;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\AIReportController;


/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

Route::post('/ai/refine-text', [AIReportController::class, 'refineText']);

Route::post('/ai/generar-script', [AIReportController::class, 'generarScript']);


/*
|--------------------------------------------------------------------------
| PERFORMANCE ENGINE
|--------------------------------------------------------------------------
*/

Route::prefix('performance')->group(function () {

    Route::post('/save', [PerformanceController::class, 'saveScript']);

    Route::post('/run', [PerformanceController::class, 'runJenkins']);

    Route::get('/history/{project}', [PerformanceController::class, 'getHistory']);

    Route::get('/analizar', [PerformanceController::class, 'analizarReporte']);

    Route::get('/logs', [PerformanceController::class, 'getBuilds']);

});


/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | USUARIO AUTENTICADO
    |--------------------------------------------------------------------------
    */

    Route::get('/user-data', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'projects' => $request->user()->projects
        ]);
    });

    Route::post('/logout', [AuthController::class, 'logout']);


    /*
    |--------------------------------------------------------------------------
    | DASHBOARD DE PROYECTOS
    |--------------------------------------------------------------------------
    */

    Route::get('/projects/{project}/dashboard', function (Request $request, Project $project) {

        $user = $request->user();

        $query = $project->solutions()->with('kpis');

        if ($user->role !== 'admin') {
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return response()->json($query->get());

    });


    /*
    |--------------------------------------------------------------------------
    | ADMIN PANEL
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | REPORTES
        |--------------------------------------------------------------------------
        */

        Route::get('/reports', [ReportController::class, 'index']);
        Route::post('/reports', [ReportController::class, 'store']);


        /*
        |--------------------------------------------------------------------------
        | PROYECTOS
        |--------------------------------------------------------------------------
        */

        Route::get('/projects', function () {
            return Project::withCount('users')->get();
        });

        Route::post('/projects', function (Request $request) {

            $validated = $request->validate([
                'name' => 'required|string|unique:projects,name|max:255',
            ]);

            return response()->json(Project::create($validated), 201);

        });

        Route::put('/projects/{id}', function (Request $request, $id) {

            $validated = $request->validate([
                'name' => 'required|string|unique:projects,name,' . $id . '|max:255',
            ]);

            $project = Project::findOrFail($id);

            $project->update($validated);

            return response()->json($project);

        });

        Route::delete('/projects/{id}', function ($id) {

            $project = Project::findOrFail($id);

            $project->users()->detach();

            $project->solutions()->each(function ($solution) {

                $solution->kpis()->delete();

                $solution->delete();

            });

            $project->delete();

            return response()->json([
                'message' => 'Proyecto y sus dependencias eliminados'
            ]);

        });


        /*
        |--------------------------------------------------------------------------
        | SOLUCIONES
        |--------------------------------------------------------------------------
        */

        Route::get('/solutions', function () {
            return Solution::all();
        });

        Route::get('/projects/{project}/solutions', function (Project $project) {
            return $project->solutions;
        });

        Route::get('/projects/{project}/full-details', function (Project $project) {

            return response()->json([
                'project' => $project,
                'solutions' => $project->solutions()->with('kpis')->get()
            ]);

        });

        Route::post('/projects/{project}/solutions', function (Request $request, Project $project) {

            $validated = $request->validate([
                'name' => 'required|string|max:255'
            ]);

            return response()->json(
                $project->solutions()->create($validated),
                201
            );

        });


        /*
        |--------------------------------------------------------------------------
        | KPIs
        |--------------------------------------------------------------------------
        */

        Route::post('/solutions/{solution}/kpis', function (Request $request, Solution $solution) {

            $validated = $request->validate([
                'metric_name'  => 'required|string',
                'target_value' => 'required|string',
                'unit'         => 'required|string',
            ]);

            return response()->json(
                $solution->kpis()->create($validated),
                201
            );

        });


        /*
        |--------------------------------------------------------------------------
        | USUARIOS
        |--------------------------------------------------------------------------
        */

        Route::get('/users', function () {
            return User::with(['projects', 'solutions'])->get();
        });

        Route::post('/users', function (Request $request) {

            $validated = $request->validate([
                'name'         => 'required|string|max:255',
                'email'        => 'required|email|unique:users,email',
                'password'     => 'required|min:6',
                'role'         => 'required|in:admin,user',
                'project_ids'  => 'array',
                'solution_ids' => 'array'
            ]);

            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role'     => $validated['role'],
            ]);

            if (!empty($validated['project_ids'])) {
                $user->projects()->sync($validated['project_ids']);
            }

            if (!empty($validated['solution_ids'])) {
                $user->solutions()->sync($validated['solution_ids']);
            }

            return response()->json(
                $user->load(['projects', 'solutions']),
                201
            );

        });

        Route::put('/users/{id}', function (Request $request, $id) {

            $user = User::findOrFail($id);

            $validated = $request->validate([
                'name'         => 'required|string|max:255',
                'email'        => 'required|email|unique:users,email,' . $id,
                'role'         => 'required|in:admin,user',
                'project_ids'  => 'array',
                'solution_ids' => 'array',
                'password'     => 'nullable|min:6',
            ]);

            $userData = [
                'name'  => $validated['name'],
                'email' => $validated['email'],
                'role'  => $validated['role'],
            ];

            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            $user->projects()->sync($validated['project_ids'] ?? []);
            $user->solutions()->sync($validated['solution_ids'] ?? []);

            return response()->json(
                $user->load(['projects', 'solutions'])
            );

        });

        Route::delete('/users/{id}', function ($id) {

            $user = User::findOrFail($id);

            $user->projects()->detach();
            $user->solutions()->detach();

            $user->delete();

            return response()->json([
                'message' => 'Usuario eliminado correctamente'
            ]);

        });

    });

});