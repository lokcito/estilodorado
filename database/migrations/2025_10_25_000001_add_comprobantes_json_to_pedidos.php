<?php

// database/migrations/2025_10_25_000001_add_comprobantes_json_to_pedidos.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->longText('comprobantes_json')->nullable(); // guardamos FA y/o BO
        });
    }
    public function down(): void {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->dropColumn('comprobantes_json');
        });
    }
};
