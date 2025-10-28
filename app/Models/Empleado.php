<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Empleado extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'empleados';
    protected $primaryKey = 'id_empleado';
    public $timestamps = false; // tu tabla solo tiene created_at por defecto

    protected $fillable = [
        'nombre','apellido','cargo','email','telefono','password'
    ];

    protected $hidden = [
        'password','remember_token',
    ];

      protected $casts = [
        'password' => 'hashed', // <-- HASH AUTOMÁTICO
    ];

    // Laravel buscará la contraseña aquí
    public function getAuthPassword()
    {
        return $this->password;
    }

    // Roles a través de la tabla pivote empleado_rol
    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'empleado_rol', 'id_empleado', 'id_rol');
    }
}
