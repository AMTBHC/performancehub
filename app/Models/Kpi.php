<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kpi extends Model
{
    protected $fillable = ['solution_id', 'metric_name', 'target_value', 'unit'];

    public function solution()
    {
        return $this->belongsTo(Solution::class);
    }
}
