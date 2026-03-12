<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    // Define los campos que SÍ pueden ser llenados masivamente
    protected $fillable = [
        'project_id',
        'solution_id',
        'name',
        'file_path',
    ];

    // Tus relaciones siguen aquí...
    public function project() {
        return $this->belongsTo(Project::class);
    }

    public function solution() {
        return $this->belongsTo(Solution::class);
    }
}