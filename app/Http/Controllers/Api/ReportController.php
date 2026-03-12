<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
  public function index(Request $request) 
{
    $query = Report::with(['project', 'solution'])->latest();

    // Si llega un project_id, filtramos la consulta
    if ($request->has('project_id')) {
        $query->where('project_id', $request->project_id);
    }

    return $query->get();
}

    public function store(Request $request) {
        $request->validate([
            'file' => 'required|file|mimes:zip',
            'project_id' => 'required|exists:projects,id',
            'solution_id' => 'required|exists:solutions,id'
        ]);

        $file = $request->file('file');
        // Guardar en /storage/app/public/reports/
        $path = $file->store('reports', 'public');
        
        return Report::create([
            'project_id' => $request->project_id,
            'solution_id' => $request->solution_id,
            'name' => $file->getClientOriginalName(),
            'file_path' => $path
        ]);
    }
}