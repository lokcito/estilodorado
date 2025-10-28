<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuditoriaAdminController extends Controller
{
    public function index(Request $request)
    {
        // TODO: reemplazar por tu consulta real a la tabla auditoria
        // filtros: recurso, actor, fecha_desde, fecha_hasta, page, per_page
        return response()->json([
            'data' => [],
            'meta' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 0],
        ]);
    }
}
