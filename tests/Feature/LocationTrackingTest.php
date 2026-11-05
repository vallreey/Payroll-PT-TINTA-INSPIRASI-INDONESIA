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
            ->assertSee('journey-dialog')
            ->assertSee('journey-day')
            ->assertSee('journey-month')
            ->assertSee('journey-year')
            ->assertSee('journey-map')
            ->assertSee('destination-dialog')
            ->assertSee('employee-destinations')
            ->assertSee('destination-preview-employee')
            ->assertSee('Pilih titik baru di peta')
            ->assertDontSee('for="destination-latitude"', false)
            ->assertDontSee('for="destination-longitude"', false)
            ->assertSee('saved-place-search')
            ->assertSee('search-results')
            ->assertSee('Simulasi lokasi')
            ->assertSee('Reset Simulasi');

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
            'description' => 'Pintu utama',
            'icon' => 'warehouse',
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
        ])->assertCreated()
            ->assertJsonPath('assignment.name', 'Gudang Selatan')
            ->assertJsonPath('assignment.description', 'Pintu utama')
            ->assertJsonPath('assignment.icon', 'warehouse');

        $trackingData = $this->getJson(route('tracking.data'))->assertOk()->json('employees');
        $employeePayload = collect($trackingData)->firstWhere('id', $employee->id_karyawan);
        $this->assertCount(1, $employeePayload['assignments']);

        $this->postJson(route('tracking.simulate', $employee), [
            'latitude' => -7.424,
            'longitude' => 109.232,
        ])->assertCreated()->assertJsonPath('point.isSimulated', true);

        $this->assertDatabaseHas('tbl_penugasan_lokasi_tujuan', [
            'id_karyawan' => $employee->id_karyawan,
            'nama_lokasi' => 'Gudang Selatan',
            'ikon' => 'warehouse',
            'keterangan' => 'Pintu utama',
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

    public function test_manager_can_read_location_history_for_one_selected_date_in_chronological_order(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $employee = $this->makeEmployee('karyawan');

        foreach ([
            ['2026-11-05', '09:15:00', -7.4245, 109.2305],
            ['2026-11-05', '08:10:00', -7.4243, 109.2302],
            ['2026-11-04', '17:00:00', -7.4300, 109.2400],
        ] as [$date, $time, $latitude, $longitude]) {
            LocationTracking::create([
                'id_karyawan' => $employee->id_karyawan,
                'tanggal' => $date,
                'waktu' => $time,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'keterangan' => null,
                'is_simulated' => false,
            ]);
        }

        $this->actingAs($hrd->user)
            ->getJson(route('tracking.history', ['employee' => $employee, 'date' => '2026-11-05']))
            ->assertOk()
            ->assertJsonPath('date', '2026-11-05')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('points.0.time', '08:10:00')
            ->assertJsonPath('points.1.time', '09:15:00')
            ->assertJsonPath('points.0.isSimulated', false)
            ->assertJsonPath('truncated', false);

        $this->actingAs($hrd->user)
            ->getJson(route('tracking.history', ['employee' => $employee, 'date' => '2026-02-31']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    public function test_manager_can_manage_multiple_destinations_and_history_reports_geofence_visits(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $employee = $this->makeEmployee('karyawan');

        $first = $this->actingAs($hrd->user)
            ->postJson(route('tracking.assignment'), [
                'employee_id' => $employee->id_karyawan,
                'name' => 'Gudang Selatan',
                'description' => 'Pintu belakang',
                'icon' => 'warehouse',
                'latitude' => -7.4243,
                'longitude' => 109.2302,
            ])
            ->assertCreated()
            ->assertJsonPath('assignment.icon', 'warehouse')
            ->json('assignment');
        $second = $this->postJson(route('tracking.assignment'), [
            'employee_id' => $employee->id_karyawan,
            'name' => 'Kantor Cabang',
            'icon' => 'office',
            'latitude' => -7.4300,
            'longitude' => 109.2400,
        ])->assertCreated()->json('assignment');

        $this->putJson(route('tracking.assignment.update', $first['id']), [
            'name' => 'Gudang Utama',
            'description' => 'Pintu belakang',
            'icon' => 'store',
            'latitude' => -7.4243,
            'longitude' => 109.2302,
        ])->assertOk()
            ->assertJsonPath('assignment.name', 'Gudang Utama')
            ->assertJsonPath('assignment.icon', 'store');

        LocationTracking::create([
            'id_karyawan' => $employee->id_karyawan,
            'tanggal' => '2026-11-05',
            'waktu' => '08:30:00',
            'latitude' => -7.42431,
            'longitude' => 109.23021,
            'keterangan' => 'Lokasi simulasi',
            'is_simulated' => true,
        ]);

        $this->getJson(route('tracking.history', ['employee' => $employee, 'date' => '2026-11-05']))
            ->assertOk()
            ->assertJsonCount(2, 'assignments')
            ->assertJsonPath('assignments.0.name', 'Gudang Utama')
            ->assertJsonPath('assignments.0.icon', 'store')
            ->assertJsonPath('assignments.0.description', 'Pintu belakang')
            ->assertJsonPath('assignments.0.geofenceRadiusMeters', 100)
            ->assertJsonPath('assignments.0.visited', true)
            ->assertJsonPath('assignments.0.visitedAt', '2026-11-05T08:30:00+07:00')
            ->assertJsonPath('assignments.0.visitIsSimulated', true)
            ->assertJsonPath('assignments.1.visited', false);

        $this->deleteJson(route('tracking.assignment.delete', $second['id']))
            ->assertOk()
            ->assertJsonPath('deleted', true);
        $this->assertDatabaseMissing('tbl_penugasan_lokasi_tujuan', ['id_tujuan' => $second['id']]);
        $this->assertDatabaseHas('tbl_penugasan_lokasi_tujuan', ['id_tujuan' => $first['id']]);
    }

    public function test_simulation_reset_only_deletes_simulated_tracking_points(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $employee = $this->makeEmployee('karyawan');
        $destination = LocationAssignment::create([
            'id_karyawan' => $employee->id_karyawan,
            'nama_lokasi' => 'Kantor',
            'ikon' => 'office',
            'latitude' => -7.4243,
            'longitude' => 109.2302,
            'id_pemberi_tugas' => $hrd->id_pengguna,
        ]);

        $realPoint = LocationTracking::create([
            'id_karyawan' => $employee->id_karyawan,
            'tanggal' => '2026-11-05',
            'waktu' => '08:00:00',
            'latitude' => -7.4243,
            'longitude' => 109.2302,
            'is_simulated' => false,
        ]);
        $simulatedPoint = LocationTracking::create([
            'id_karyawan' => $employee->id_karyawan,
            'tanggal' => '2026-11-05',
            'waktu' => '08:05:00',
            'latitude' => -7.4250,
            'longitude' => 109.2310,
            'is_simulated' => true,
        ]);

        $this->actingAs($hrd->user)
            ->deleteJson(route('tracking.simulation.reset'))
            ->assertOk()
            ->assertJsonPath('deleted', 1);

        $this->assertDatabaseHas('tbl_tracking_lokasi', ['id_tracking' => $realPoint->id_tracking, 'is_simulated' => false]);
        $this->assertDatabaseMissing('tbl_tracking_lokasi', ['id_tracking' => $simulatedPoint->id_tracking]);
        $this->assertDatabaseHas('tbl_penugasan_lokasi_tujuan', ['id_tujuan' => $destination->id_tujuan]);
        $this->assertDatabaseHas('tbl_pengguna', ['id_pengguna' => $hrd->id_pengguna]);

        $this->actingAs($employee->user)
            ->deleteJson(route('tracking.simulation.reset'))
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
