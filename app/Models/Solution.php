<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Solution extends Model
{
    // IMPORTANTE: Permite que guardemos el nombre y el ID del proyecto
    protected $fillable = ['name', 'project_id'];

    /**
     * Relación con el Proyecto (Padre)
     */
    public function project(): BelongsTo 
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relación con los KPIs (NFRs del dashboard)
     */
    public function kpis(): HasMany
    {
        return $this->hasMany(Kpi::class);
    }

    /**
     * Relación con ejecuciones (pruebas de performance realizadas)
     */
    public function executions(): HasMany 
    {
        return $this->hasMany(Execution::class);
    }

    public function users() {
    return $this->belongsToMany(User::class);
}
}