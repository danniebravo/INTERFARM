<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnimalIndexProductionTodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_animal_with_only_previous_day_production_is_not_marked_as_registered_today(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $farm = Farm::create([
            'name' => 'Finca de prueba',
            'production_type' => 'leche',
        ]);
        $user->farms()->attach($farm->id, ['role' => 'owner']);

        $animal = Animal::create([
            'farm_id' => $farm->id,
            'name' => 'Luna',
            'sex' => 'hembra',
            'purpose' => 'leche',
            'status' => Animal::STATUS_ACTIVE,
            'has_calved_before' => 'si',
            'calving_count' => 1,
        ]);

        DB::table('milk_productions')->insert([
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'production_date' => now()->subDay()->toDateString(),
            'period' => 'mañana',
            'liters' => 8,
            'price_per_liter' => 0,
            'total_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['current_farm_id' => $farm->id])
            ->get(route('animals.index'));

        $response->assertOk();
        $response->assertSee('Luna');
        $response->assertDontSee('Producción registrada hoy');
        $response->assertSee('Sin producción');
    }

    public function test_animal_with_today_production_is_marked_as_registered_today(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $farm = Farm::create([
            'name' => 'Finca de prueba',
            'production_type' => 'leche',
        ]);
        $user->farms()->attach($farm->id, ['role' => 'owner']);

        $animal = Animal::create([
            'farm_id' => $farm->id,
            'name' => 'Estrella',
            'sex' => 'hembra',
            'purpose' => 'leche',
            'status' => Animal::STATUS_ACTIVE,
            'has_calved_before' => 'si',
            'calving_count' => 1,
        ]);

        DB::table('milk_productions')->insert([
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'production_date' => now()->toDateString(),
            'period' => 'mañana',
            'liters' => 8,
            'price_per_liter' => 0,
            'total_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['current_farm_id' => $farm->id])
            ->get(route('animals.index'));

        $response->assertOk();
        $response->assertSee('Estrella');
        $response->assertSee('Producción registrada hoy');
        $response->assertSee('En leche');
    }

    public function test_weight_only_production_can_be_registered_without_period(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $farm = Farm::create([
            'name' => 'Finca de prueba',
            'production_type' => 'carne',
        ]);
        $user->farms()->attach($farm->id, ['role' => 'owner']);

        $animal = Animal::create([
            'farm_id' => $farm->id,
            'name' => 'Toro',
            'sex' => 'macho',
            'purpose' => 'carne',
            'status' => Animal::STATUS_ACTIVE,
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['current_farm_id' => $farm->id])
            ->from(route('animals.show', $animal))
            ->post(route('animals.production.store', $animal), [
                'date' => now()->toDateString(),
                'weight' => 430,
            ]);

        $response->assertRedirect(route('animals.show', $animal));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('animals', [
            'id' => $animal->id,
            'weight_current' => 430,
        ]);
    }
}
