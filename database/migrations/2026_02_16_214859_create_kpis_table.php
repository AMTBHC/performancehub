<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            // Esta línea es la que une el KPI con una Solución específica
            $table->foreignId('solution_id')->constrained()->onDelete('cascade');
            $table->string('metric_name'); // Ejemplo: TPS, Latencia
            $table->string('target_value'); // Ejemplo: 500
            $table->string('unit'); // Ejemplo: ms, req/s
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpis');
    }
};