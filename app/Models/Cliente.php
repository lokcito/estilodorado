<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable; // Importante: autenticable
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Cliente extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'clientes';
    protected $primaryKey = 'id_cliente';
    public $timestamps = false; // tu tabla solo tiene created_at por defecto; si quisieras, puedes mapearlo

    protected $fillable = [
        'nombre',
        'apellido',
        'telefono',
        'email',
        'direccion',
        'contrasena', // hash
    ];

    protected $hidden = [
        'contrasena',
        'remember_token',
    ];

    // Sobrescribimos para que Laravel use 'contrasena' en lugar de 'password'
    public function getAuthPassword()
    {
        return $this->contrasena;
    }
}
