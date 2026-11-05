<?php

namespace Tests\Feature;

use App\Models\LocationAssignment;
use App\Models\LocationPlace;
use App\Models\LocationTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesAttendanceData;
use Tests\TestCase;

class LocationTrackingTest extends TestCase
{
    use CreatesAttendanceData;
    use RefreshDatabase;

    public function test_tracking_page_uses_the_shared_layout_and_is_scoped_to_the_signed_in_employee(): void
    {
        $employee = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Dina Karyawan']);
        $another = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Rudi Karyawan']);

        $this->actingAs($employee->user)
            ->get(route('tracking.index'))
            ->assertOk()
            ->assertSee('Tracking Lokasi')
            ->assertSee('poppins')
            ->assertSee('images/lokasi2.png')
            ->assertSee('recenter-map')
            ->assertSee('fullscreen-map')
            ->assertSee('employee-search')
            ->assertSee('Dina Karyawan')
            ->assertDontSee('Rudi Karyawan');
    }

    public function test_employee_can_share_their_own_location_and_admin_can_read_visible_locations(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $employee = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Nia Lapangan']);

        $this->actingAs($employee->user)
            ->postJson(route('tracking.position'), [
                'latitude' => -7.4243,
                'longitude' => 109.2302,
                'accuracy' => 18,
            ])
            ->assertCreated()
            ->assertJsonPath('point.isSimulated', false);

        $this->assertDatabaseHas('tbl_tracking_lokasi', [
            'id_karyawan' => $employee->id_karyawan,
            'latitude' => -7.4243,
            'longitude' => 109.2302,
            'is_simulated' => false,
        ]);

        $this->actingAs($hrd->user)
            ->getJson(route('tracking.data'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Nia Lapangan']);
    }

    public function test_employees_cannot_write_other_employees_locations_or_manage_destinations(): void
    {
        $employee = $this->makeEmployee('karyawan');

        $this->actingAs($employee->user)
            ->postJson(route('tracking.position'), ['latitude' => 91, 'longitude' => 109])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $this->actingAs($employee->user)
            ->postJson(route('tracking.assignment'), [
                'employee_id' => $employee->id_karyawan,
                'name' => 'Kantor Pusat',
                'latitude' => -7.4,
                'longitude' => 109.2,
            ])
            ->assertForbidden();

        $this->assertSame(0, LocationTracking::count());
        $this->assertSame(0, LocationAssignment::count());
    }

    public function test_manager_can_save_a_custom_place_assign_a_destination_and_simulate_a_location(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $employee = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Budi Lapangan']);

        $this->actingAs($hrd->user)
            ->get(route('tracking.index'))
            ->assertOk()
            ->assertSee('saved-place-search')
            ->assertSee('search-results')
            ->assertSee('Simulasi lokasi');

        $placeResponse = $this->actingAs($hrd->user)
            ->postJson(route('tracking.places.store'), [
                'name' => 'Gudang Selatan',
                'description' => 'Pintu masuk belakang',
                'latitude' => -7.425,
                'longitude' => 109.231,
            ])
            ->assertCreated()
            ->assertJsonPath('place.name', 'Gudang Selatan');

        $place = LocationPlace::firstOrFail();
        $this->assertSame('Gudang Selatan', $placeResponse->json('place.name'));
        $this->assertSame($hrd->id_pengguna, $place->id_pembuat);

        $this->postJson(route('tracking.assignment'), [
            'employee_id' => $employee->id_karyawan,
            'name' => $place->nama_lokasi,
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
        ])->assertOk()->assertJsonPath('assignment.name', 'Gudang Selatan');

        $this->postJson(route('tracking.simulate', $employee), [
            'latitude' => -7.424,
            'longitude' => 109.232,
        ])->assertCreated()->assertJsonPath('point.isSimulated', true);

        $this->assertDatabaseHas('tbl_penugasan_lokasi', [
            'id_karyawan' => $employee->id_karyawan,
            'nama_lokasi' => 'Gudang Selatan',
        ]);
        $this->assertDatabaseHas('tbl_tracking_lokasi', [
            'id_karyawan' => $employee->id_karyawan,
            'is_simulated' => true,
        ]);
    }

    public function test_employee_cannot_read_the_location_history_of_another_employee(): void
    {
        $employee = $this->makeEmployee('karyawan');
        $other = $this->makeEmployee('karyawan');

        $this->actingAs($employee->user)
            ->getJson(route('tracking.history', $other))
            ->assertForbidden();
    }

    public function test_open_source_geocoding_and_routing_apis_are_used(): void
    {
        $hrd = $this->makeEmployee('hrd');
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                ['name' => 'Alun-alun', 'lat' => '-7.4', 'lon' => '109.2', 'display_name' => 'Alun-alun, Purwokerto'],
            ]),
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'name' => 'Purwokerto', 'display_name' => 'Purwokerto, Indonesia',
            ]),
            'https://router.project-osrm.org/route/v1/driving/*' => Http::response([
                'routes' => [[
                    'distance' => 1000,
                    'duration' => 300,
                    'geometry' => ['coordinates' => [[109.2, -7.4], [109.21, -7.41]]],
                ]],
            ]),
        ]);

        $this->actingAs($hrd->user)
            ->getJson(route('tracking.search', ['q' => 'Purwokerto']))
            ->assertOk()
            ->assertJsonPath('0.name', 'Alun-alun');
        $this->getJson(route('tracking.reverse', ['latitude' => -7.4, 'longitude' => 109.2]))
            ->assertOk()
            ->assertJsonPath('name', 'Purwokerto');
        $this->getJson(route('tracking.route', [
            'from_latitude' => -7.4,
            'from_longitude' => 109.2,
            'to_latitude' => -7.41,
            'to_longitude' => 109.21,
        ]))
            ->assertOk()
            ->assertJsonPath('routes.0.distance', 1000);

        Http::assertSentCount(3);
    }
}
