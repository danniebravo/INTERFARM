<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\Event;
use App\Models\Farm;
use App\Models\FarmNotification;
use App\Models\MilkProduction;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class FarmNotificationService
{
    public function syncForFarmAndUser(Farm $farm, User $user): void
    {
        $this->purgeNotificationsForInactiveAnimals($farm, $user);
        $this->syncEventNotificationsForFarmAndUser($farm, $user);
        $this->syncDailyProductionReminderForFarmAndUser($farm, $user);
        $this->syncBillingNotificationsForUser($user);
    }

    public function notifyInvoiceGenerated(SubscriptionInvoice $invoice): void
    {
        if (! Schema::hasTable('farm_notifications')) {
            return;
        }

        $invoice->loadMissing(['user.farms', 'plan']);
        $user = $invoice->user;

        if (! $user) {
            return;
        }

        FarmNotification::updateOrCreate(
            [
                'user_id' => $user->id,
                'source_type' => 'subscription_invoice',
                'source_key' => (string) $invoice->id,
            ],
            [
                'farm_id' => null,
                'event_id' => null,
                'level' => $invoice->status === SubscriptionInvoice::STATUS_OVERDUE ? 'high' : 'medium',
                'title' => 'Nueva factura disponible',
                'message' => 'Se generó la factura ' . $invoice->invoice_number . ' por ' . $invoice->currency . ' $' . number_format((float) $invoice->amount, 0, ',', '.') . '.',
                'event_date' => $invoice->due_date,
                'lot_name' => null,
                'meta' => [
                    'automatic' => true,
                    'type_label' => 'Facturación',
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => (float) $invoice->amount,
                    'currency' => $invoice->currency,
                    'due_date' => $invoice->due_date?->format('Y-m-d'),
                ],
                'scheduled_for' => now(),
            ]
        );
    }

    public function notifyAdminPaymentReceived(SubscriptionPayment $payment): void
    {
        if (! Schema::hasTable('farm_notifications')) {
            return;
        }

        $payment->loadMissing(['user', 'plan', 'invoice']);
        $client = $payment->user;

        if (! $client) {
            return;
        }

        $admins = User::query()
            ->whereIn('role', ['admin', 'super_admin', 'superadmin'])
            ->when(User::hiddenAdminUserIds() !== [], fn ($query) => $query->whereNotIn('id', User::hiddenAdminUserIds()))
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $clientName = $client->full_name ?: $client->email;
        $paidAt = $payment->paid_at ?: now();

        foreach ($admins as $admin) {
            FarmNotification::updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'source_type' => 'admin_payment_received',
                    'source_key' => (string) $payment->id,
                ],
                [
                    'farm_id' => null,
                    'event_id' => null,
                    'level' => 'high',
                    'title' => 'Pago recibido',
                    'message' => $clientName . ' pagó ' . $payment->currency . ' $' . number_format((float) $payment->amount, 0, ',', '.') . '.',
                    'event_date' => $paidAt->toDateString(),
                    'lot_name' => null,
                    'meta' => [
                        'automatic' => true,
                        'type_label' => 'Pago SaaS',
                        'event_type' => 'admin_payment_received',
                        'payment_id' => $payment->id,
                        'client_id' => $client->id,
                        'client_name' => $clientName,
                        'invoice_id' => $payment->subscription_invoice_id,
                        'reference' => $payment->reference,
                        'provider' => $payment->provider,
                    ],
                    'scheduled_for' => now(),
                ]
            );
        }
    }

    protected function syncBillingNotificationsForUser(User $user): void
    {
        if (! $this->billingNotificationsAreReady()) {
            return;
        }

        SubscriptionInvoice::with(['user', 'plan'])
            ->where('user_id', $user->id)
            ->whereIn('status', [
                SubscriptionInvoice::STATUS_PENDING,
                SubscriptionInvoice::STATUS_OVERDUE,
            ])
            ->latest('due_date')
            ->limit(10)
            ->get()
            ->each(fn (SubscriptionInvoice $invoice) => $this->notifyInvoiceGenerated($invoice));
    }

    protected function syncEventNotificationsForFarmAndUser(Farm $farm, User $user): void
    {
        if (! $this->eventNotificationsAreReady()) {
            return;
        }

        $start = now()->startOfDay();
        $end = now()->addMonthNoOverflow()->endOfDay();

        Event::with('animal')
            ->where('farm_id', $farm->id)
            ->where(function ($query) {
                $query->whereNull('animal_id')
                    ->orWhereHas('animal', fn ($animalQuery) => $this->whereAnimalIsActive($animalQuery));
            })
            ->where(function ($query) {
                $query->where('status', 'pending')
                    ->orWhereNull('status');
            })
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('start_datetime', [$start, $end]);
            })
            ->orderByRaw('COALESCE(start_datetime, event_date) asc')
            ->limit(50)
            ->get()
            ->each(fn (Event $event) => $this->notifyCalendarEvent($event, $user));

        $automaticEvents = Animal::where('farm_id', $farm->id)
            ->get()
            ->filter(fn (Animal $animal) => $animal->isActive())
            ->flatMap(fn (Animal $animal) => $this->buildAutomaticEventsForAnimal($animal))
            ->filter(function (object $event) use ($start, $end) {
                $eventDate = $this->parseAnimalDate($event->event_date ?? null);

                return $eventDate && $eventDate->betweenIncluded($start, $end);
            })
            ->sortBy(fn (object $event) => $this->parseAnimalDate($event->event_date ?? null)?->timestamp ?? PHP_INT_MAX)
            ->take(50)
            ->values();

        $this->purgeObsoleteAutomaticNotifications($farm, $user, $automaticEvents->pluck('id')->map(fn ($id) => (string) $id)->all());

        $automaticEvents->each(fn (object $event) => $this->notifyAutomaticEvent($event, $farm, $user));
    }

    protected function notifyCalendarEvent(Event $event, User $user): void
    {
        $eventDate = $this->resolveEventDate($event);

        if (! $eventDate) {
            return;
        }

        $typeLabel = $this->resolveTypeLabel((string) $event->type);
        $animalName = $event->animal
            ? ($event->animal->name ?: ($event->animal->ear_tag ?: null))
            : null;
        $animalText = $animalName ? ' Animal: ' . $animalName . '.' : '';

        $source = [
            'user_id' => $user->id,
            'source_type' => 'calendar_event',
            'source_key' => (string) $event->id,
        ];

        $payload = [
            'farm_id' => $event->farm_id,
            'event_id' => $event->id,
            'level' => $this->notificationLevelForDate($eventDate, (string) $event->priority),
            'title' => 'Próximo evento: ' . $event->title,
            'message' => $typeLabel . ' programado para el ' . $eventDate->format('d/m/Y') . '.' . $animalText,
            'event_date' => $eventDate,
            'lot_name' => $event->lot_name,
            'meta' => [
                'automatic' => false,
                'type_label' => $typeLabel,
                'event_type' => $event->type,
                'priority' => $event->priority,
                'status' => $event->status,
                'animal_id' => $event->animal_id,
                'animal_name' => $animalName,
            ],
            'scheduled_for' => now(),
        ];

        FarmNotification::updateOrCreate($source, $this->withDailyReminderState($source, $payload));
    }

    protected function notifyAutomaticEvent(object $event, Farm $farm, User $user): void
    {
        $eventDate = $this->parseAnimalDate($event->event_date ?? null);

        if (! $eventDate) {
            return;
        }

        $animal = $event->animal ?? null;
        $animalName = $animal
            ? ($animal->name ?: ($animal->ear_tag ?: null))
            : null;

        $source = [
            'user_id' => $user->id,
            'source_type' => 'automatic_event',
            'source_key' => (string) $event->id,
        ];

        $payload = [
            'farm_id' => $farm->id,
            'event_id' => null,
            'level' => $this->notificationLevelForDate($eventDate, (string) ($event->priority ?? 'medium')),
            'title' => $event->title,
            'message' => trim(($event->description ?: 'Evento automático de la finca.') . ' Fecha: ' . $eventDate->format('d/m/Y') . '.'),
            'event_date' => $eventDate,
            'lot_name' => $event->lot_name ?? null,
            'meta' => [
                'automatic' => true,
                'type_label' => $event->type_label ?? 'Evento',
                'event_type' => $event->type ?? 'general',
                'priority' => $event->priority ?? 'medium',
                'animal_id' => $event->animal_id ?? null,
                'animal_name' => $animalName,
            ],
            'scheduled_for' => now(),
        ];

        FarmNotification::updateOrCreate($source, $this->withDailyReminderState($source, $payload));
    }

    protected function withDailyReminderState(array $source, array $payload): array
    {
        $today = now()->toDateString();
        $notification = FarmNotification::where($source)->first();
        $previousMeta = $notification?->meta ?? [];
        $previousReminderDate = $previousMeta['last_reminded_on'] ?? null;
        $lastInteractionDate = $notification?->dismissed_at?->toDateString()
            ?: $notification?->read_at?->toDateString();

        $payload['meta'] = array_merge($payload['meta'] ?? [], [
            'last_reminded_on' => $today,
        ]);

        if (! $notification
            || $previousReminderDate === $today
            || (! $previousReminderDate && $lastInteractionDate === $today)
        ) {
            return $payload;
        }

        return array_merge($payload, [
            'read_at' => null,
            'dismissed_at' => null,
            'dismissed_until' => null,
        ]);
    }

    protected function syncDailyProductionReminderForFarmAndUser(Farm $farm, User $user): void
    {
        if (! $this->productionReminderNotificationsAreReady()) {
            return;
        }

        $today = now()->toDateString();
        $sourceKey = 'farm-' . $farm->id . '-' . $today;

        FarmNotification::where('user_id', $user->id)
            ->where('farm_id', $farm->id)
            ->where('source_type', 'daily_production_reminder')
            ->whereDate('event_date', '<', $today)
            ->delete();

        $hasCalvedBeforeColumn = Schema::hasColumn('animals', 'has_calved_before');
        $hasCalvingCountColumn = Schema::hasColumn('animals', 'calving_count');
        $hasLastCalvingDateColumn = Schema::hasColumn('animals', 'last_calving_date');

        $milkProducingFemales = Animal::where('farm_id', $farm->id)
            ->where('sex', 'hembra')
            ->whereIn('purpose', ['leche', 'doble_proposito'])
            ->where(function ($query) {
                $query->where('status', Animal::STATUS_ACTIVE)
                    ->orWhereNull('status');
            })
            ->where(function ($query) use ($hasCalvedBeforeColumn, $hasCalvingCountColumn, $hasLastCalvingDateColumn) {
                if ($hasCalvedBeforeColumn) {
                    $query->orWhere('has_calved_before', 'si');
                }

                if ($hasCalvingCountColumn) {
                    $query->orWhere('calving_count', '>', 0);
                }

                if ($hasLastCalvingDateColumn) {
                    $query->orWhereNotNull('last_calving_date');
                }

                if (! $hasCalvedBeforeColumn && ! $hasCalvingCountColumn && ! $hasLastCalvingDateColumn) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->count();

        if ($milkProducingFemales < 1) {
            FarmNotification::where('user_id', $user->id)
                ->where('farm_id', $farm->id)
                ->where('source_type', 'daily_production_reminder')
                ->delete();

            return;
        }

        $hasMilkProductionToday = MilkProduction::where('farm_id', $farm->id)
            ->whereDate('production_date', $today)
            ->exists();

        if ($hasMilkProductionToday) {
            FarmNotification::where('user_id', $user->id)
                ->where('farm_id', $farm->id)
                ->where('source_type', 'daily_production_reminder')
                ->where('source_key', $sourceKey)
                ->delete();

            return;
        }

        FarmNotification::updateOrCreate(
            [
                'user_id' => $user->id,
                'source_type' => 'daily_production_reminder',
                'source_key' => $sourceKey,
            ],
            [
                'farm_id' => $farm->id,
                'event_id' => null,
                'level' => 'medium',
                'title' => 'Recuerda registrar la producción',
                'message' => 'Tienes ' . $milkProducingFemales . ' hembra' . ($milkProducingFemales === 1 ? '' : 's') . ' en producción de leche. Registra la producción de leche de hoy para mantener tus datos al día.',
                'event_date' => now(),
                'lot_name' => null,
                'meta' => [
                    'automatic' => true,
                    'type_label' => 'Producción',
                    'event_type' => 'daily_production_reminder',
                    'milk_producing_females' => $milkProducingFemales,
                ],
                'scheduled_for' => now(),
            ]
        );
    }

    protected function purgeNotificationsForInactiveAnimals(Farm $farm, User $user): void
    {
        if (! Schema::hasTable('farm_notifications') || ! Schema::hasTable('animals')) {
            return;
        }

        $inactiveAnimalIds = Animal::query()
            ->get()
            ->reject(fn (Animal $animal) => $animal->isActive())
            ->pluck('id')
            ->values();

        if ($inactiveAnimalIds->isEmpty()) {
            return;
        }

        FarmNotification::where('user_id', $user->id)
            ->where('source_type', 'automatic_event')
            ->where(function ($query) use ($inactiveAnimalIds) {
                foreach ($inactiveAnimalIds as $animalId) {
                    $query->orWhere('source_key', 'like', 'auto-%-' . $animalId . '-%');
                }
            })
            ->delete();

        if (Schema::hasTable('events') && Schema::hasColumn('events', 'animal_id')) {
            $eventIds = Event::whereIn('animal_id', $inactiveAnimalIds->all())
                ->pluck('id');

            if ($eventIds->isNotEmpty()) {
                FarmNotification::where('user_id', $user->id)
                    ->where('source_type', 'calendar_event')
                    ->whereIn('event_id', $eventIds->all())
                    ->delete();
            }
        }
    }

    protected function purgeObsoleteAutomaticNotifications(Farm $farm, User $user, array $validSourceKeys): void
    {
        if (! Schema::hasTable('farm_notifications')) {
            return;
        }

        $query = FarmNotification::where('user_id', $user->id)
            ->where('farm_id', $farm->id)
            ->where('source_type', 'automatic_event');

        if ($validSourceKeys === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('source_key', $validSourceKeys)->delete();
    }

    protected function billingNotificationsAreReady(): bool
    {
        if (! Schema::hasTable('farm_notifications')
            || ! Schema::hasTable('subscription_invoices')) {
            return false;
        }

        foreach (['user_id', 'status', 'due_date'] as $column) {
            if (! Schema::hasColumn('subscription_invoices', $column)) {
                return false;
            }
        }

        return true;
    }

    protected function productionReminderNotificationsAreReady(): bool
    {
        if (! Schema::hasTable('farm_notifications')
            || ! Schema::hasTable('animals')
            || ! Schema::hasTable('milk_productions')) {
            return false;
        }

        foreach (['farm_id', 'sex', 'purpose', 'status'] as $column) {
            if (! Schema::hasColumn('animals', $column)) {
                return false;
            }
        }

        foreach (['farm_id', 'production_date'] as $column) {
            if (! Schema::hasColumn('milk_productions', $column)) {
                return false;
            }
        }

        return true;
    }

    protected function eventNotificationsAreReady(): bool
    {
        if (! Schema::hasTable('farm_notifications')
            || ! Schema::hasTable('events')
            || ! Schema::hasTable('animals')) {
            return false;
        }

        foreach (['farm_id', 'title', 'type', 'status', 'event_date', 'start_datetime'] as $column) {
            if (! Schema::hasColumn('events', $column)) {
                return false;
            }
        }

        foreach (['farm_id', 'sex', 'is_pregnant', 'pregnancy_date', 'last_calving_date'] as $column) {
            if (! Schema::hasColumn('animals', $column)) {
                return false;
            }
        }

        return true;
    }

    protected function whereAnimalIsActive($query)
    {
        return $query->where(function ($statusQuery) {
            $statusQuery->whereNull('status')
                ->orWhereNotIn('status', [
                    Animal::STATUS_SOLD,
                    Animal::STATUS_DECEASED,
                    'sold',
                    'deceased',
                    'dead',
                ]);
        });
    }

    protected function buildAutomaticEventsForAnimal(Animal $animal): array
    {
        if (! $animal->isActive()) {
            return [];
        }

        $events = $this->buildAutomaticHealthEventsForAnimal($animal);

        if ($animal->sex !== 'hembra') {
            return $events;
        }

        $pregnancyDate = $this->parseAnimalDate($animal->pregnancy_date ?? null);
        $lastCalvingDate = $this->parseAnimalDate($animal->last_calving_date ?? null);
        $animalName = $this->automaticAnimalName($animal);
        $isPregnant = ($animal->is_pregnant ?? null) === 'si';

        if ($isPregnant && $pregnancyDate) {
            $probableCalvingDate = $pregnancyDate->copy()->addDays(283);
            $prepartumDate = $probableCalvingDate->copy()->subDays(30);
            $dryingDate = $probableCalvingDate->copy()->subDays(60);

            $events[] = $this->makeAutomaticEventObject([
                'id' => 'auto-inseminacion-' . $animal->id . '-' . $pregnancyDate->format('Ymd'),
                'title' => 'Servicio / inseminación de ' . $animalName,
                'description' => 'Evento generado automáticamente desde la fecha de preñez o servicio.',
                'type' => 'inseminacion',
                'type_label' => 'Inseminación',
                'priority' => 'medium',
                'event_date' => $pregnancyDate,
                'animal' => $animal,
                'animal_id' => $animal->id,
                'lot_name' => $animal->location ?? null,
            ]);

            $events[] = $this->makeAutomaticEventObject([
                'id' => 'auto-parto-' . $animal->id . '-' . $probableCalvingDate->format('Ymd'),
                'title' => 'Parto probable de ' . $animalName,
                'description' => 'Evento generado automáticamente desde la fecha de preñez.',
                'type' => 'parto',
                'type_label' => 'Próximo parto',
                'priority' => 'high',
                'event_date' => $probableCalvingDate,
                'animal' => $animal,
                'animal_id' => $animal->id,
                'lot_name' => $animal->location ?? null,
            ]);

            $events[] = $this->makeAutomaticEventObject([
                'id' => 'auto-preparto-' . $animal->id . '-' . $prepartumDate->format('Ymd'),
                'title' => 'Preparto de ' . $animalName,
                'description' => 'Preparación estimada 30 días antes del parto probable.',
                'type' => 'revision',
                'type_label' => 'Preparto',
                'priority' => 'high',
                'event_date' => $prepartumDate,
                'animal' => $animal,
                'animal_id' => $animal->id,
                'lot_name' => $animal->location ?? null,
            ]);

            if (in_array($animal->purpose, ['leche', 'doble_proposito'], true)) {
                $events[] = $this->makeAutomaticEventObject([
                    'id' => 'auto-secado-' . $animal->id . '-' . $dryingDate->format('Ymd'),
                    'title' => 'Secado de ' . $animalName,
                    'description' => 'Secado estimado 60 días antes del parto probable.',
                    'type' => 'revision',
                    'type_label' => 'Secado',
                    'priority' => 'medium',
                    'event_date' => $dryingDate,
                    'animal' => $animal,
                    'animal_id' => $animal->id,
                    'lot_name' => $animal->location ?? null,
                ]);
            }
        }

        if ($lastCalvingDate) {
            $postpartumReviewDate = $lastCalvingDate->copy()->addDays(7);
            $estimatedFertilityDate = $lastCalvingDate->copy()->addDays(45);
            $weaningDate = $lastCalvingDate->copy()->addDays(90);

            $events[] = $this->makeAutomaticEventObject([
                'id' => 'auto-revision-posparto-' . $animal->id . '-' . $postpartumReviewDate->format('Ymd'),
                'title' => 'Revisión posparto de ' . $animalName,
                'description' => 'Control automático 7 días después del último parto.',
                'type' => 'revision',
                'type_label' => 'Revisión posparto',
                'priority' => 'medium',
                'event_date' => $postpartumReviewDate,
                'animal' => $animal,
                'animal_id' => $animal->id,
                'lot_name' => $animal->location ?? null,
            ]);

            if (! $isPregnant) {
                $events[] = $this->makeAutomaticEventObject([
                    'id' => 'auto-fertilidad-' . $animal->id . '-' . $estimatedFertilityDate->format('Ymd'),
                    'title' => 'Nueva fertilidad estimada de ' . $animalName,
                    'description' => 'Fecha estimada para volver a fertilidad después del parto.',
                    'type' => 'celo',
                    'type_label' => 'Nueva fertilidad estimada',
                    'priority' => 'medium',
                    'event_date' => $estimatedFertilityDate,
                    'animal' => $animal,
                    'animal_id' => $animal->id,
                    'lot_name' => $animal->location ?? null,
                ]);
            }

            $events[] = $this->makeAutomaticEventObject([
                'id' => 'auto-destete-' . $animal->id . '-' . $weaningDate->format('Ymd'),
                'title' => 'Destete estimado de cría de ' . $animalName,
                'description' => 'Evento generado automáticamente 90 días después del último parto.',
                'type' => 'revision',
                'type_label' => 'Destete',
                'priority' => 'medium',
                'event_date' => $weaningDate,
                'animal' => $animal,
                'animal_id' => $animal->id,
                'lot_name' => $animal->location ?? null,
            ]);
        }

        return $events;
    }

    protected function buildAutomaticHealthEventsForAnimal(Animal $animal): array
    {
        return collect($animal->healthRecords())
            ->flatMap(function (array $record, int $index) use ($animal) {
                $eventDate = $this->parseAnimalDate($record['date'] ?? null);

                if (! $eventDate) {
                    return [];
                }

                $treatmentType = trim((string) ($record['treatment_type'] ?? ''));
                $isVaccine = mb_strtolower($treatmentType) === 'vacuna';
                $type = $isVaccine ? 'vacuna' : 'tratamiento';
                $typeLabel = $isVaccine ? 'Vacunación' : 'Tratamiento';
                $titleBase = $treatmentType !== '' ? $treatmentType : $typeLabel;
                $animalName = $this->automaticAnimalName($animal);
                $recordKey = substr(md5(json_encode($record)), 0, 10);

                $description = collect([
                    $record['disease'] ?? null ? 'Enfermedad: ' . $record['disease'] : null,
                    $record['diagnosis'] ?? null ? 'Diagnóstico: ' . $record['diagnosis'] : null,
                    $record['medication'] ?? null ? 'Medicamento: ' . $record['medication'] : null,
                    $record['days'] ?? null ? 'Duración: ' . (int) $record['days'] . ' día(s)' : null,
                    $record['notes'] ?? null,
                ])->filter()->implode('. ');

                $events = [
                    $this->makeAutomaticEventObject([
                        'id' => 'auto-health-' . $animal->id . '-' . $eventDate->format('Ymd') . '-' . $recordKey,
                        'title' => $titleBase . ' de ' . $animalName,
                        'description' => $description ?: 'Registro de salud generado desde la ficha del animal.',
                        'type' => $type,
                        'type_label' => $typeLabel,
                        'priority' => $isVaccine ? 'medium' : 'high',
                        'event_date' => $eventDate,
                        'animal' => $animal,
                        'animal_id' => $animal->id,
                        'lot_name' => $animal->location ?? null,
                    ]),
                ];

                $days = isset($record['days']) ? (int) $record['days'] : 0;

                if (! $isVaccine && $days > 1) {
                    $reviewDate = $eventDate->copy()->addDays($days);

                    $events[] = $this->makeAutomaticEventObject([
                        'id' => 'auto-health-review-' . $animal->id . '-' . $reviewDate->format('Ymd') . '-' . $recordKey,
                        'title' => 'Revisión de tratamiento de ' . $animalName,
                        'description' => 'Seguimiento automático al finalizar el tratamiento. ' . ($description ?: ''),
                        'type' => 'revision',
                        'type_label' => 'Revisión',
                        'priority' => 'medium',
                        'event_date' => $reviewDate,
                        'animal' => $animal,
                        'animal_id' => $animal->id,
                        'lot_name' => $animal->location ?? null,
                    ]);
                }

                return $events;
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function makeAutomaticEventObject(array $data): object
    {
        $eventDate = $data['event_date'] instanceof Carbon
            ? $data['event_date']->copy()->startOfDay()
            : Carbon::parse($data['event_date'])->startOfDay();

        return (object) [
            'id' => $data['id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'general',
            'type_label' => $data['type_label'] ?? 'Evento',
            'status' => 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'event_date' => $eventDate,
            'animal' => $data['animal'] ?? null,
            'animal_id' => $data['animal_id'] ?? null,
            'lot_name' => $data['lot_name'] ?? null,
        ];
    }

    protected function resolveEventDate(Event $event): ?Carbon
    {
        if ($event->start_datetime) {
            return $this->parseAnimalDate($event->start_datetime);
        }

        return $this->parseAnimalDate($event->event_date);
    }

    protected function parseAnimalDate($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function automaticAnimalName(Animal $animal): string
    {
        return $animal->name ?: ($animal->ear_tag ?: 'animal');
    }

    protected function resolveTypeLabel(string $type): string
    {
        return match ($type) {
            'parto' => 'Próximo parto',
            'vacuna' => 'Vacunación',
            'tratamiento' => 'Tratamiento',
            'inseminacion' => 'Inseminación',
            'celo' => 'Celo',
            'revision' => 'Revisión',
            default => 'Evento',
        };
    }

    protected function notificationLevelForDate(Carbon $date, string $priority = 'medium'): string
    {
        $daysUntil = now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        if ($priority === 'high' || $daysUntil <= 3) {
            return 'high';
        }

        if ($priority === 'medium' || $daysUntil <= 10) {
            return 'medium';
        }

        return 'low';
    }
}
