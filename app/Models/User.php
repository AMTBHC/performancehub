<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // <--- IMPORTANTE para el token del login

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable; // <--- Añade HasApiTokens aquí

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
protected $fillable = [
    'name',
    'email',
    'password',
    'role', // <--- DEBES AGREGAR ESTO
];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relación Muchos a Muchos con Proyectos.
     * Un usuario puede pertenecer a Terpel, Alisson, etc.
     */
public function projects()
{
    return $this->belongsToMany(Project::class, 'project_user');
}

public function solutions()
{
    // Asegúrate que 'solution_user' sea el nombre exacto de tu tabla pivote
    return $this->belongsToMany(Solution::class, 'solution_user');
}

}