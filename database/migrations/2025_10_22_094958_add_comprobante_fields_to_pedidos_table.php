<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agrega cada columna solo si NO existe
        if (!Schema::hasColumn('pedidos', 'forma_pago')) {
            Schema::table('pedidos', function (Blueprint $t) {
                $t->string('forma_pago', 30)->nullable();
            });
        }
        if (!Schema::hasColumn('pedidos', 'culqi_id')) {
            Schema::table('pedidos', function (Blueprint $t) {
                $t->string('culqi_id', 80)->nullable();
            });
        }
        if (!Schema::hasColumn('pedidos', 'comprobante_tipo')) {
            Schema::table('pedidos', function (Blueprint $t) {
                $t->string('comprobante_tipo', 3)->nullable();
            });
        }
        if (!Schema::hasColumn('pedidos', 'comprobante_serie')) {
            Schema::table('pedidos', function (Blueprint $t) {
                $t->string('comprobante_serie', 4)->nullable();
            });
        }
        if (!Schema::hasColumn('pedidos', 'comprobante_numero')) {
            Schema::table('pedidos', function (Blueprint $t) {
                $t->string('comprobante_numero', 8)->nullable();
            });
        }
        if (!Schema::hasColumn('pedidos', 'sunat_xml')) {
            Schema::table('pedidos', function (Blueprint $t) {
                $t->string('sunat_xml')->nullable();
            });
        }
        if (!Schema::hasColumn('pedidos', 'sunat_cdr')) {
            Schema::table('pedidos', function (Blueprint $t) {
                $t->string('sunat_cdr')->nullable();
            });
        }
        if (!Schema::hasColumn('pedidos', 'sunat_pdf')) {
            Schema::table('pedidos', function (Blueprint $t) {
                $t->string('sunat_pdf')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Elimina cada columna solo si existe
        if (Schema::hasColumn('pedidos', 'forma_pago')) {
            Schema::table('pedidos', function (Blueprint $t) { $t->dropColumn('forma_pago'); });
        }
        if (Schema::hasColumn('pedidos', 'culqi_id')) {
            Schema::table('pedidos', function (Blueprint $t) { $t->dropColumn('culqi_id'); });
        }
        if (Schema::hasColumn('pedidos', 'comprobante_tipo')) {
            Schema::table('pedidos', function (Blueprint $t) { $t->dropColumn('comprobante_tipo'); });
        }
        if (Schema::hasColumn('pedidos', 'comprobante_serie')) {
            Schema::table('pedidos', function (Blueprint $t) { $t->dropColumn('comprobante_serie'); });
        }
        if (Schema::hasColumn('pedidos', 'comprobante_numero')) {
            Schema::table('pedidos', function (Blueprint $t) { $t->dropColumn('comprobante_numero'); });
        }
        if (Schema::hasColumn('pedidos', 'sunat_xml')) {
            Schema::table('pedidos', function (Blueprint $t) { $t->dropColumn('sunat_xml'); });
        }
        if (Schema::hasColumn('pedidos', 'sunat_cdr')) {
            Schema::table('pedidos', function (Blueprint $t) { $t->dropColumn('sunat_cdr'); });
        }
        if (Schema::hasColumn('pedidos', 'sunat_pdf')) {
            Schema::table('pedidos', function (Blueprint $t) { $t->dropColumn('sunat_pdf'); });
        }
    }
};
