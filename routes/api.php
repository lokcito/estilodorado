<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Broadcast;

use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\AuthClienteController;
// ==== AUTH EMPLEADOS (PANEL) ====
use App\Http\Controllers\Api\Admin\AuthEmpleadoController;

use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\PedidoClienteController;
use App\Http\Controllers\Api\PedidoClienteStoreController;

// ==== ADMIN: =====
use App\Http\Controllers\Api\Admin\ProductoAdminController;
use App\Http\Controllers\Api\Admin\CategoriaAdminController;
use App\Http\Controllers\Api\Admin\ProveedorAdminController;
use App\Http\Controllers\Api\Admin\PedidoAdminController;
use App\Http\Controllers\Api\Admin\PedidoEstadoController;
use App\Http\Controllers\Api\Admin\InventarioAdminController;
use App\Http\Controllers\Api\Admin\ClienteAdminController;
use App\Http\Controllers\Api\Admin\ReporteAdminController;
use App\Http\Controllers\Api\Admin\AuditoriaAdminController;

use App\Http\Controllers\Api\PagoCulqiController;
use App\Http\Controllers\Api\FeController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\PedidoPagoController;
use App\Http\Controllers\Api\GeoController;

use App\Http\Controllers\Api\Admin\AdminEventsController;


// ---------------------------------------------------------
// Misceláneo
// ---------------------------------------------------------
Route::get('geo/search', [GeoController::class, 'search']);
Route::get('geo/reverse', [GeoController::class, 'reverse']);
Route::get('ping', fn() => 'pong');

// ---------------------------------------------------------
// PÚBLICO (tienda)
// ---------------------------------------------------------
Route::apiResource('productos', ProductoController::class);
Route::get('categorias', [CategoriaController::class,'index']);
Route::get('categorias/{id}/productos', [CategoriaController::class,'productos']);

// ---------------------------------------------------------
// AUTH CLIENTE
// ---------------------------------------------------------
Route::post('auth/register', [AuthClienteController::class, 'register']);
Route::post('auth/login',    [AuthClienteController::class, 'login']);
Route::post('auth/check-email',            [AuthClienteController::class, 'checkEmail']);
Route::post('auth/password/reset-simple',  [AuthClienteController::class, 'resetSimple']);

Route::middleware(['auth:sanctum','abilities:client'])->group(function () {
    Route::get('auth/me',       [AuthClienteController::class, 'me']);
    Route::put('auth/me',       [AuthClienteController::class, 'update']);
    Route::post('auth/logout',  [AuthClienteController::class, 'logout']);
    Route::post('auth/password',[AuthClienteController::class, 'updatePassword']);
});

// ---------------------------------------------------------
// ÁREA CLIENTE (autenticado)
// ---------------------------------------------------------
Route::middleware(['auth:sanctum','abilities:client'])->group(function () {
    Route::get('mis-pedidos',        [PedidoClienteController::class,'index']);
    Route::get('mis-pedidos/{id}',   [PedidoClienteController::class,'show']);
    Route::post('pedidos',           [PedidoClienteStoreController::class,'store']);
    Route::get('pedidos/mios',       [PedidoClienteController::class, 'mios']);
});

Route::middleware(['auth:sanctum','abilities:client'])->get('/pedidos', [PedidoPagoController::class, 'index']);

// ---------------------------------------------------------
// PAGOS / CHECKOUT
// ---------------------------------------------------------
Route::post('/pagar-con-culqi', [PagoCulqiController::class, 'pagar']);

Route::middleware(['auth:sanctum','abilities:client'])->group(function () {
    Route::post('fe/emitir', [FeController::class, 'emitir']);
    Route::post('checkout/confirmar', [CheckoutController::class, 'confirmar']);
    Route::post('pedidos/confirmar', [PedidoPagoController::class, 'confirmar']);
    Route::get('pedidos/{id}',       [PedidoPagoController::class, 'show']);
});

// ===================================================================================
// PANEL ADMIN: AUTH EMPLEADOS
// ===================================================================================
Route::prefix('admin/auth')->group(function () {
    Route::post('login',  [AuthEmpleadoController::class, 'login']);
    Route::middleware(['auth:sanctum','abilities:admin'])->group(function () {
        Route::get('me',     [AuthEmpleadoController::class, 'me']);
        Route::post('logout',[AuthEmpleadoController::class, 'logout']);
    });
});

// ===============================================
// PANEL ADMIN: RUTAS PROTEGIDAS (auth + role)
// ===============================================
Route::prefix('admin')->middleware(['auth:sanctum','abilities:admin'])->group(function () {

    // PRODUCTOS / CATEGORÍAS / PROVEEDORES
    Route::middleware(['role:ADMIN,SOPORTE,VENTAS,STOCK'])->group(function () {
        Route::get('productos',          [ProductoAdminController::class, 'index']);
        Route::get('productos/{id}',     [ProductoAdminController::class, 'show']);
        Route::get('categorias',         [CategoriaAdminController::class, 'index']);
        Route::get('proveedores',        [ProveedorAdminController::class, 'index']);
        Route::get('alertas/stock-bajo', [ProductoAdminController::class, 'stockBajo']);
    });

    // 🔓 SOLO LECTURA DE CLIENTES PARA ADMIN/SOPORTE/VENTAS
    Route::middleware(['role:ADMIN,SOPORTE,VENTAS'])->group(function () {
        Route::get('clientes',      [ClienteAdminController::class, 'index']);
        Route::get('clientes/{id}', [ClienteAdminController::class, 'show']);
    });

    // CRUD restringido a ADMIN
    Route::middleware(['role:ADMIN'])->group(function () {
        Route::post('productos',                 [ProductoAdminController::class, 'store']);
        Route::put('productos/{id}',             [ProductoAdminController::class, 'update']);
        Route::delete('productos/{id}',          [ProductoAdminController::class, 'destroy']);
        Route::post('categorias',                [CategoriaAdminController::class, 'store']);
        Route::put('categorias/{id}',            [CategoriaAdminController::class, 'update']);
        Route::delete('categorias/{id}',         [CategoriaAdminController::class, 'destroy']);
        Route::post('proveedores',               [ProveedorAdminController::class, 'store']);
        Route::put('proveedores/{id}',           [ProveedorAdminController::class, 'update']);
        Route::delete('proveedores/{id}',        [ProveedorAdminController::class, 'destroy']);

        // edición de clientes solo ADMIN
        Route::put('clientes/{id}',              [ClienteAdminController::class, 'update']);
        Route::patch('clientes/{id}',            [ClienteAdminController::class, 'update']);

        // Eliminar pedidos (solo ADMIN)
        Route::delete('pedidos/{id}',            [PedidoAdminController::class, 'destroy']);
    });

    // PEDIDOS + ESTADOS + COMPROBANTES
    Route::middleware(['role:ADMIN,SOPORTE,VENTAS,STOCK'])->group(function () {
        Route::get('pedidos',                                [PedidoAdminController::class, 'index']);
        Route::get('pedidos/{id}',                           [PedidoAdminController::class, 'show']);
        Route::get('pedidos/{id}/estado-historial',          [PedidoEstadoController::class, 'historial']);
    });

    // Cambio de estado (ahora también VENTAS)
    Route::middleware(['role:ADMIN,SOPORTE,VENTAS'])->group(function () {
        Route::post('pedidos/{id}/estado',                   [PedidoEstadoController::class, 'cambiarEstado']);
    });

    Route::middleware(['role:ADMIN,SOPORTE,VENTAS'])->group(function () {
        Route::get('pedidos/{id}/comprobantes',              [PedidoAdminController::class, 'comprobantes']);
        Route::get('pedidos/{id}/comprobantes/download',     [PedidoAdminController::class, 'descargarComprobante']);

        // Crear / Editar pedidos (ADMIN | VENTAS)
        Route::post('pedidos',                                [PedidoAdminController::class, 'store']);
        Route::put('pedidos/{id}',                            [PedidoAdminController::class, 'update']);
        Route::patch('pedidos/{id}',                          [PedidoAdminController::class, 'update']);
    });

    // INVENTARIO
    Route::middleware(['role:ADMIN,SOPORTE,VENTAS,STOCK'])->group(function () {
        Route::get('inventario',                   [InventarioAdminController::class, 'index']);
    });
    Route::middleware(['role:ADMIN,STOCK'])->group(function () {
        Route::post('inventario/entrada',          [InventarioAdminController::class, 'entrada']);
        Route::post('inventario/salida',           [InventarioAdminController::class, 'salida']);
        Route::post('inventario/ajuste',           [InventarioAdminController::class, 'ajuste']);
    });

    // REPORTES / AUDITORÍA (ADMIN)
    Route::middleware(['role:ADMIN'])->group(function () {
        Route::get('reportes/clientes.{ext}', [ReporteAdminController::class, 'clientes'])->where('ext', 'xlsx|csv|pdf');   
        Route::get('reportes/productos.{ext}',   [ReporteAdminController::class, 'productos'])->where('ext', 'xlsx|csv|pdf');
        Route::get('reportes/pedidos.{ext}',     [ReporteAdminController::class, 'pedidos'])->where('ext', 'xlsx|csv|pdf');
        Route::get('reportes/inventario.{ext}',  [ReporteAdminController::class, 'inventario'])->where('ext', 'xlsx|csv|pdf');
        Route::get('auditoria',                  [AuditoriaAdminController::class, 'index']);
    });

        // Route::get('events/stream', [AdminEventsController::class, 'stream']);
});

// ===================================================================================
// BROADCASTING (canales privados, p/ realtime en panel)
// ===================================================================================
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// ---------------------------------------------------------
// COMPROBANTES (archivos estáticos)
// ---------------------------------------------------------
Route::match(['GET','HEAD'],'fe/xml/{tipo}/{serie}/{name}', function ($tipo, $serie, $name) {
    if (!in_array($tipo, ['FA','BO'])) {
        return response()->json(['message' => 'Tipo inválido.'], 404)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET,HEAD,OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }
    $path = "comprobantes/xml/{$tipo}/{$serie}/{$name}";
    if (!Storage::disk('public')->exists($path)) {
        return response()->json(['message' => 'XML no disponible.'], 404)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET,HEAD,OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }
    return response(Storage::disk('public')->get($path), 200, [
        'Content-Type' => 'application/xml',
        'Content-Disposition' => 'inline; filename="'.$name.'"',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods', 'GET,HEAD,OPTIONS',
        'Access-Control-Allow-Headers', '*',
    ]);
})->name('fe.xml');

Route::match(['GET','HEAD'],'fe/cdr/{tipo}/{name}', function ($tipo, $name) {
    if (!in_array($tipo, ['FA','BO'])) {
        return response()->json(['message' => 'Tipo inválido.'], 404)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET,HEAD,OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }
    $path = "comprobantes/cdr/{$tipo}/{$name}";
    if (!Storage::disk('public')->exists($path)) {
        return response()->json(['message' => 'CDR no disponible.'], 404)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET,HEAD,OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }
    return response(Storage::disk('public')->get($path), 200, [
        'Content-Type' => 'application/zip',
        'Content-Disposition' => 'inline; filename="'.$name.'"',
        'Access-Control-Allow-Origin', '*',
        'Access-Control-Allow-Methods', 'GET,HEAD,OPTIONS',
        'Access-Control-Allow-Headers', '*',
    ]);
})->name('fe.cdr');

Route::match(['GET','HEAD'],'fe/pdf/{tipo}/{serie}/{name}', function ($tipo, $serie, $name) {
    if (!in_array($tipo, ['FA','BO'])) {
        return response()->json(['message' => 'Tipo inválido.'], 404)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET,HEAD,OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }
    $path = "comprobantes/pdf/{$tipo}/{$serie}/{$name}";
    if (!Storage::disk('public')->exists($path)) {
        return response()->json(['message' => 'PDF no disponible.'], 404)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET,HEAD,OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }
    return response(Storage::disk('public')->get($path), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="'.$name.'"',
        'Access-Control-Allow-Origin', '*',
        'Access-Control-Allow-Methods', 'GET,HEAD,OPTIONS',
        'Access-Control-Allow-Headers', '*',
    ]);
})->name('fe.pdf');

// Preflight genérico
Route::options('{any}', fn() => response()->noContent()
    ->header('Access-Control-Allow-Origin','*')
    ->header('Access-Control-Allow-Methods','GET,HEAD,OPTIONS')
    ->header('Access-Control-Allow-Headers','*')
)->where('any','.*');
