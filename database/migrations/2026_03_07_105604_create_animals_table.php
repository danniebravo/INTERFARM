<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animals', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relación con finca
            |--------------------------------------------------------------------------
            */

            $table->foreignId('farm_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Identificación
            |--------------------------------------------------------------------------
            */

            $table->string('internal_code')->nullable();
            $table->string('ear_tag')->nullable();
            $table->string('name')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Clasificación
            |--------------------------------------------------------------------------
            */

            $table->string('species')->default('bovino');
            $table->string('breed')->nullable();

            $table->enum('sex', ['macho','hembra']);

            $table->string('category')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Propósito productivo
            |--------------------------------------------------------------------------
            */

            $table->enum('purpose', [
                'carne',
                'leche',
                'doble_proposito',
                'crianza'
            ])->nullable();

            /*
            |--------------------------------------------------------------------------
            | Nacimiento
            |--------------------------------------------------------------------------
            */

            $table->date('birth_date')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Genealogía
            |--------------------------------------------------------------------------
            */

            $table->foreignId('dam_id')
                ->nullable()
                ->constrained('animals')
                ->nullOnDelete();

            $table->foreignId('sire_id')
                ->nullable()
                ->constrained('animals')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Producción
            |--------------------------------------------------------------------------
            */

            $table->decimal('weight_birth', 8, 2)->nullable();
            $table->decimal('weight_current', 8, 2)->nullable();
            $table->date('last_weight_date')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            */

            $table->string('status')->default('activo');

            /*
            |--------------------------------------------------------------------------
            | Manejo
            |--------------------------------------------------------------------------
            */

            $table->string('location')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Extra
            |--------------------------------------------------------------------------
            */

            $table->text('notes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Imagen principal
            |--------------------------------------------------------------------------
            */

            $table->string('photo')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animals');
    }
};