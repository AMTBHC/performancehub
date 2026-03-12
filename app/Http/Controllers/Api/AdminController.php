<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminController extends Controller
{
  public function getUsers() {
    return response()->json(User::with('projects')->get());
}

public function storeProject(Request $request) {
    $project = Project::create($request->validate(['name' => 'required|string']));
    return response()->json($project);
}

public function assignProject(Request $request) {
    $user = User::find($request->user_id);
    $user->projects()->syncWithoutDetaching($request->project_id);
    return response()->json(['message' => 'Proyecto asignado']);
}
}
