<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'id_producto';
    public $timestamps = true; // tu tabla tiene created_at y updated_at

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio_compra',
        'precio_venta',
        'stock',
        'id_categoria',
        'id_proveedor',
        'imagen_url',
        'estado',   // 'activo' | 'inactivo'
        'slug',
    ];

    protected $casts = [
        'precio_compra' => 'decimal:2',
        'precio_venta'  => 'decimal:2',
        'stock'         => 'integer',
        'id_categoria'  => 'integer',
        'id_proveedor'  => 'integer',
        // 'estado' es enum string, lo dejamos como string
    ];

    public function categoria()
    {
        return $this->belongsTo(\App\Models\Categoria::class, 'id_categoria', 'id_categoria');
    }
}
