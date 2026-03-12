<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void {
    Schema::create('executions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('solution_id')->constrained()->onDelete('cascade');
        $table->foreignId('user_id')->constrained(); // Quién la hizo
        $table->string('test_type'); // k6, JMeter, etc.
        $table->json('metrics'); // Aquí guardaremos los resultados (p95, tps, etc)
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('executions');
    }
};
