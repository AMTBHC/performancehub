<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['name']; // <--- Asegúrate de que esto exista

  public function users()
{
    return $this->belongsToMany(User::class);
}

public function solutions()
{
    return $this->hasMany(Solution::class);
}
}