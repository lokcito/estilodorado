<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            // 1) nuevo campo para hash (null por ahora para poder llenar)
            $table->string('password')->nullable()->after('contrasena');

            // 2) (opcional pero recomendado) índice único al email si aún no lo tienes
            // if (!Schema::hasColumn('empleados', 'email')) { ... } // si no existiera
            // $table->unique('email'); // descomenta si tu esquema lo permite
        });

        // 3) Hashear contraseñas existentes desde `contrasena` (texto plano)
        //    Si hay NULLs, los salta.
        $rows = DB::table('empleados')->select('id_empleado', 'contrasena')->get();
        foreach ($rows as $r) {
            if (!empty($r->contrasena)) {
                DB::table('empleados')
                  ->where('id_empleado', $r->id_empleado)
                  ->update(['password' => Hash::make($r->contrasena)]);
            }
        }

        // 4) (opcional) poner `contrasena` a NULL para evitar fugas de texto plano
        DB::table('empleados')->update(['contrasena' => null]);

        // 5) volver `password` NOT NULL
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // revertimos a como estaba: eliminamos password
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('password');
            // si agregaste unique en email y no existía, aquí también lo tendrías que dropear
            // $table->dropUnique(['email']);
        });
    }
};
