<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            // Relación con finca (multi-tenant clave 🔥)
            $table->foreignId('farm_id')
                ->constrained()
                ->cascadeOnDelete();

            // Relación opcional con animal
            $table->foreignId('animal_id')
                ->nullable()
                ->constrained('animals')
                ->nullOnDelete();

            // Información principal
            $table->string('title');
            $table->text('description')->nullable();

            // Tipo de evento (parto, vacuna, recordatorio, etc)
            $table->string('type', 50)->default('general')->index();

            // Fecha simple (para eventos tipo calendario básico)
            $table->date('event_date')->nullable()->index();

            // Rango de tiempo (para calendario avanzado tipo Google Calendar)
            $table->dateTime('start_datetime')->nullable()->index();
            $table->dateTime('end_datetime')->nullable();

            // Todo el día o no
            $table->boolean('all_day')->default(true);

            // Estado del evento
            $table->string('status', 30)->default('pending')->index(); // pending, completed, cancelled

            // Prioridad
            $table->string('priority', 30)->default('medium')->index(); // low, medium, high

            // Relación con lote (texto por ahora)
            $table->string('lot_name')->nullable();

            // Color para UI (calendar)
            $table->string('color', 20)->nullable();

            // Datos extra flexibles
            $table->json('meta')->nullable();

            $table->timestamps();

            // Índices compuestos (rendimiento 🔥)
            $table->index(['farm_id', 'event_date']);
            $table->index(['farm_id', 'start_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};