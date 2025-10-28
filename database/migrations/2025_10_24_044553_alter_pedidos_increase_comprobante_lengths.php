<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
// database/migrations/2025_10_24_044553_alter_pedidos_increase_comprobante_lengths.php

public function up()
{
    // 1) backfill para evitar NOT NULL con NULLs
    DB::table('pedidos')->whereNull('comprobante_tipo')->update(['comprobante_tipo' => 'BO']);
    DB::table('pedidos')->whereNull('comprobante_serie')->update(['comprobante_serie' => 'B0001']);
    DB::table('pedidos')->whereNull('comprobante_numero')->update(['comprobante_numero' => 0]);

    Schema::table('pedidos', function (Blueprint $table) {
        $table->string('comprobante_tipo', 20)->nullable(false)->change();
        $table->string('comprobante_serie', 10)->nullable(false)->change();
        $table->unsignedInteger('comprobante_numero')->nullable(false)->change();
    });
}


};
