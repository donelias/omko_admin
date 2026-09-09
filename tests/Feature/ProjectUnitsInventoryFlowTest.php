<?php

namespace Tests\Feature;

use App\Models\AgentBookingPreference;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ProjectUnitInventoryMovement;
use App\Models\Projects;
use App\Models\Property;
use App\Services\ProjectUnitInventoryService;
use App\Services\ProjectUnitSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProjectUnitsInventoryFlowTest extends TestCase
{
    use DatabaseTransactions;

    private ProjectUnitSyncService $syncService;

    private ProjectUnitInventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncService = app(ProjectUnitSyncService::class);
        $this->inventoryService = app(ProjectUnitInventoryService::class);
    }

    public function test_sync_creates_and_updates_same_unit_without_duplicates(): void
    {
        $project = $this->createProject();

        $this->syncService->syncProjectUnitsFromPlans($project, [
            [
                'title' => 'Tipologia A',
                'unit_code' => 'tipo a',
                'price' => 125000,
                'currency' => 'usd',
                'total_units' => 5,
                'available_units' => 5,
                'unit_status' => 'available',
            ],
        ]);

        $this->assertSame(1, Property::where('project_id', $project->id)->where('unit_code', 'TIPO_A')->count());

        $this->syncService->syncProjectUnitsFromPlans($project, [
            [
                'title' => 'Tipologia A Updated',
                'unit_code' => 'TIPO_A',
                'price' => 130000,
                'currency' => 'USD',
                'total_units' => 5,
                'available_units' => 3,
                'unit_status' => 'low_stock',
            ],
        ]);

        $this->assertSame(1, Property::where('project_id', $project->id)->where('unit_code', 'TIPO_A')->count());

        $unit = Property::where('project_id', $project->id)->where('unit_code', 'TIPO_A')->firstOrFail();

        $this->assertSame('Tipologia A Updated', $unit->title);
        $this->assertSame('130000', (string) $unit->price);
        $this->assertSame(3, (int) $unit->available_units);
        $this->assertSame('low_stock', $unit->unit_status);
    }

    public function test_deactivate_units_by_plan_ids_marks_matching_units_inactive(): void
    {
        $project = $this->createProject();

        $unit = $this->createProjectUnitProperty($project, [
            'unit_code' => 'PLAN_99',
            'unit_status' => 'available',
            'status' => 1,
        ]);

        $this->syncService->deactivateUnitsByPlanIds($project, [99]);

        $unit->refresh();

        $this->assertSame('inactive', $unit->unit_status);
        $this->assertSame(0, (int) $unit->status);
    }

    public function test_reserve_for_appointment_decrements_stock_and_logs_movement(): void
    {
        $project = $this->createProject();
        $user = $this->createCustomer('buyer-reserve');

        $unit = $this->createProjectUnitProperty($project, [
            'total_units' => 2,
            'available_units' => 2,
            'unit_status' => 'available',
        ]);

        $appointment = $this->createAppointment($unit, null, $user->id);

        $result = $this->inventoryService->reserveForAppointment($unit, $appointment->id, 'user', $user->id, 'reserve test');

        $this->assertTrue($result['success']);

        $unit->refresh();
        $this->assertSame(1, (int) $unit->available_units);
        $this->assertSame('low_stock', $unit->unit_status);

        $movement = ProjectUnitInventoryMovement::where('property_id', $unit->id)->latest('id')->firstOrFail();
        $this->assertSame('reserve', $movement->event_type);
        $this->assertSame(-1, (int) $movement->delta_units);
        $this->assertSame(2, (int) $movement->before_units);
        $this->assertSame(1, (int) $movement->after_units);
        $this->assertSame($appointment->id, (int) $movement->appointment_id);
    }

    public function test_release_for_appointment_returns_stock_and_caps_at_total(): void
    {
        $project = $this->createProject();
        $user = $this->createCustomer('buyer-release');

        $unit = $this->createProjectUnitProperty($project, [
            'total_units' => 1,
            'available_units' => 0,
            'unit_status' => 'sold_out',
        ]);

        $appointment = $this->createAppointment($unit, null, $user->id);

        $firstRelease = $this->inventoryService->releaseForAppointment($appointment, 'cancel', 'user', $user->id, 'cancel test');
        $this->assertTrue($firstRelease['success']);

        $unit->refresh();
        $this->assertSame(1, (int) $unit->available_units);
        $this->assertSame('low_stock', $unit->unit_status);

        $movementCountAfterFirstRelease = ProjectUnitInventoryMovement::where('property_id', $unit->id)->count();
        $this->assertSame(1, $movementCountAfterFirstRelease);

        $secondRelease = $this->inventoryService->releaseForAppointment($appointment, 'cancel', 'user', $user->id, 'duplicate cancel test');
        $this->assertTrue($secondRelease['success']);

        $unit->refresh();
        $this->assertSame(1, (int) $unit->available_units);
        $this->assertSame(
            $movementCountAfterFirstRelease,
            ProjectUnitInventoryMovement::where('property_id', $unit->id)->count()
        );
    }

    public function test_auto_cancel_command_sets_status_and_releases_stock(): void
    {
        $project = $this->createProject();
        $agent = $this->createCustomer('agent-auto-cancel');
        $user = $this->createCustomer('user-auto-cancel');

        AgentBookingPreference::create([
            'is_admin_data' => 0,
            'agent_id' => $agent->id,
            'auto_cancel_after_minutes' => 5,
            'auto_cancel_message' => 'Auto cancel test message',
            'timezone' => 'UTC',
            'anti_spam_enabled' => true,
        ]);

        $unit = $this->createProjectUnitProperty($project, [
            'total_units' => 2,
            'available_units' => 0,
            'unit_status' => 'sold_out',
            'added_by' => $agent->id,
        ]);

        $appointment = $this->createAppointment($unit, $agent->id, $user->id, [
            'status' => 'pending',
            'created_at' => Carbon::now('UTC')->subMinutes(20),
            'updated_at' => Carbon::now('UTC')->subMinutes(20),
        ]);

        $exitCode = Artisan::call('appointments:auto-cancel');
        $this->assertSame(0, $exitCode);

        $appointment->refresh();
        $unit->refresh();

        $this->assertSame('auto_cancelled', $appointment->status);
        $this->assertSame('system', $appointment->last_status_updated_by);
        $this->assertSame(1, (int) $unit->available_units);

        $this->assertDatabaseHas('appointment_cancellations', [
            'appointment_id' => $appointment->id,
            'cancelled_by' => 'system',
        ]);

        $movement = ProjectUnitInventoryMovement::where('property_id', $unit->id)
            ->where('appointment_id', $appointment->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('expire', $movement->event_type);
        $this->assertSame(1, (int) $movement->delta_units);
    }

    private function createProject(): Projects
    {
        $category = Category::query()->first();
        if (! $category) {
            $category = Category::create([
                'category' => 'Residential',
                'parameter_types' => '',
                'image' => 'category-test.jpg',
                'status' => 1,
                'sequence' => 1,
            ]);
        }

        $owner = $this->createCustomer('project-owner');

        return Projects::create([
            'title' => 'Project '.uniqid(),
            'slug_id' => 'project-'.uniqid(),
            'description' => 'Test project description',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
            'meta_keywords' => 'meta,keywords',
            'meta_image' => 'meta.jpg',
            'image' => 'project.jpg',
            'video_link' => 'https://example.com/video',
            'video_type' => 1,
            'location' => 'Test Address',
            'latitude' => '18.4861',
            'longitude' => '-69.9312',
            'city' => 'Santo Domingo',
            'state' => 'Distrito Nacional',
            'country' => 'DO',
            'type' => 'upcoming',
            'added_by' => $owner->id,
            'category_id' => $category->id,
            'status' => 1,
            'request_status' => 'approved',
            'is_premium' => 0,
            'role_context' => 'user',
        ]);
    }

    private function createProjectUnitProperty(Projects $project, array $overrides = []): Property
    {
        $defaults = [
            'project_id' => $project->id,
            'is_project_unit' => 1,
            'unit_code' => 'TIPO_'.strtoupper(substr(md5((string) microtime(true)), 0, 5)),
            'total_units' => 5,
            'available_units' => 5,
            'unit_status' => 'available',
            'category_id' => (string) $project->category_id,
            'title' => 'Test Unit',
            'slug_id' => 'test-unit-'.uniqid(),
            'description' => 'Test unit description',
            'address' => 'Test unit address',
            'client_address' => 'Test unit address',
            'propery_type' => 0,
            'rentduration' => null,
            'price' => '100000',
            'currency' => 'USD',
            'title_image' => 'unit.jpg',
            'three_d_image' => '',
            'video_link' => '',
            'video_type' => 1,
            'state' => $project->state,
            'country' => $project->country,
            'city' => $project->city,
            'status' => 1,
            'request_status' => 'approved',
            'total_click' => 0,
            'latitude' => (string) $project->latitude,
            'longitude' => (string) $project->longitude,
            'is_premium' => 0,
            'expiry_date' => Carbon::now()->addMonth()->toDateString(),
            'is_demo' => 0,
            'edit_reason' => null,
            'added_by' => $project->added_by,
            'role_context' => 'user',
        ];

        return Property::create(array_merge($defaults, $overrides));
    }

    private function createAppointment(Property $property, ?int $agentId, int $userId, array $overrides = []): Appointment
    {
        $defaults = [
            'is_admin_appointment' => 0,
            'admin_id' => null,
            'agent_id' => $agentId,
            'user_id' => $userId,
            'property_id' => $property->id,
            'meeting_type' => 'virtual',
            'start_at' => Carbon::now('UTC')->addDays(1)->toDateTimeString(),
            'end_at' => Carbon::now('UTC')->addDays(1)->addMinutes(30)->toDateTimeString(),
            'status' => 'pending',
            'is_auto_confirmed' => 0,
            'last_status_updated_by' => null,
            'notes' => null,
        ];

        $timestampOverrides = Arr::only($overrides, ['created_at', 'updated_at']);
        $appointment = Appointment::create(array_merge($defaults, Arr::except($overrides, ['created_at', 'updated_at'])));

        if (! empty($timestampOverrides)) {
            Appointment::where('id', $appointment->id)->update($timestampOverrides);
            $appointment->refresh();
        }

        return $appointment;
    }

    private function createCustomer(string $prefix): Customer
    {
        $random = str_replace('.', '', (string) microtime(true));

        return Customer::create([
            'name' => $prefix.'-'.$random,
            'email' => $prefix.'-'.$random.'@example.test',
            'password' => bcrypt('secret'),
            'auth_id' => 'auth-'.$prefix.'-'.$random,
            'mobile' => '+1809'.$random,
            'country_code' => '+1',
            'default_language' => 1,
            'profile' => 'profile.jpg',
            'address' => 'Test address',
            'fcm_id' => null,
            'logintype' => 'email',
            'is_admin_added' => 0,
            'is_email_verified' => 1,
            'isActive' => 1,
            'slug_id' => 'customer-'.$prefix.'-'.$random,
            'notification' => 1,
            'latitude' => '18.4861',
            'longitude' => '-69.9312',
            'city' => 'Santo Domingo',
            'state' => 'Distrito Nacional',
            'country' => 'DO',
            'is_agent' => $prefix === 'agent-auto-cancel' ? 1 : 0,
            'is_agent_verified' => 1,
        ]);
    }
}
