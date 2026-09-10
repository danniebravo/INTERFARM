<?php

namespace Tests\Unit;

use App\Models\Animal;
use PHPUnit\Framework\TestCase;

class AnimalProductionStatusTest extends TestCase
{
    public function test_deceased_female_cannot_register_any_production_even_after_calving(): void
    {
        $animal = new Animal([
            'sex' => 'hembra',
            'status' => Animal::STATUS_DECEASED,
            'has_calved_before' => 'si',
            'calving_count' => 1,
        ]);

        $this->assertFalse($animal->canRegisterMilkProduction());
        $this->assertFalse($animal->canRegisterProduction());
        $this->assertSame(
            'No se puede registrar producción en un animal fallecido.',
            $animal->milkProductionBlockedReason()
        );
        $this->assertSame(
            'No se puede registrar producción en un animal fallecido.',
            $animal->productionBlockedReason()
        );
    }

    public function test_deceased_male_cannot_register_production(): void
    {
        $animal = new Animal([
            'sex' => 'macho',
            'status' => Animal::STATUS_DECEASED,
        ]);

        $this->assertFalse($animal->canRegisterProduction());
        $this->assertSame(
            'No se puede registrar producción en un animal fallecido.',
            $animal->productionBlockedReason()
        );
    }

    public function test_sold_male_cannot_register_weight_production(): void
    {
        $animal = new Animal([
            'sex' => 'macho',
            'status' => Animal::STATUS_SOLD,
        ]);

        $this->assertFalse($animal->canRegisterProduction());
        $this->assertSame(
            'No se puede registrar producción en un animal vendido.',
            $animal->productionBlockedReason()
        );
    }

    public function test_male_with_deceased_alias_cannot_register_weight_production(): void
    {
        $animal = new Animal([
            'sex' => 'macho',
            'status' => 'muerto',
        ]);

        $this->assertFalse($animal->canRegisterProduction());
        $this->assertSame(
            'No se puede registrar producción en un animal fallecido.',
            $animal->productionBlockedReason()
        );
    }

    public function test_non_active_animal_cannot_register_weight_production(): void
    {
        $animal = new Animal([
            'sex' => 'macho',
            'status' => 'inactivo',
        ]);

        $this->assertFalse($animal->canRegisterProduction());
        $this->assertSame(
            'Solo se puede registrar producción en animales activos dentro de la finca.',
            $animal->productionBlockedReason()
        );
    }

    public function test_sold_female_cannot_register_milk_production_even_after_calving(): void
    {
        $animal = new Animal([
            'sex' => 'hembra',
            'status' => Animal::STATUS_SOLD,
            'has_calved_before' => 'si',
            'calving_count' => 1,
        ]);

        $this->assertFalse($animal->canRegisterMilkProduction());
        $this->assertFalse($animal->canRegisterProduction());
        $this->assertSame(
            'No se puede registrar producción de leche en un animal vendido.',
            $animal->milkProductionBlockedReason()
        );
        $this->assertSame(
            'No se puede registrar producción en un animal vendido.',
            $animal->productionBlockedReason()
        );
    }
}
