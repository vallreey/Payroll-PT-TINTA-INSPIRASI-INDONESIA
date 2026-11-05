<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LocationAssignment;
use App\Models\LocationPlace;
use App\Models\LocationTracking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class LocationTrackingController extends Controller
{
    private const GEOFENCE_RADIUS_METERS = 100;

    public function index(Request $request): View
    {
        $user = $request->user();
        $employees = $this->employeeQuery($user)
            ->with([
                'user:id_pengguna,username,foto_profil',
                'department:id_departemen,nama_departemen',
                'latestLocationTracking',
            ])
            ->orderBy('nama_lengkap')
            ->get();

        $employeeIds = $employees->modelKeys();
        $assignments = LocationAssignment::query()
            ->whereIn('id_karyawan', $employeeIds)
            ->get()
            ->groupBy('id_karyawan');

        $places = $user->isApprover()
            ? LocationPlace::query()->orderBy('nama_lokasi')->get()
            : collect();

        return view('tracking.index', [
            'user' => $user,
            'isManager' => $user->isApprover(),
            'employees' => $employees->map(fn (Employee $employee) => $this->employeePayload(
                $employee,
                $assignments->get($employee->id_karyawan, collect()),
            ))->values(),
            'places' => $places->map(fn (LocationPlace $place) => $this->placePayload($place))->values(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        $employees = $this->employeeQuery($user)
            ->with([
                'user:id_pengguna,username,foto_profil',
                'department:id_departemen,nama_departemen',
                'latestLocationTracking',
            ])
            ->orderBy('nama_lengkap')
            ->get();

        $assignments = LocationAssignment::query()
            ->whereIn('id_karyawan', $employees->modelKeys())
            ->get()
            ->groupBy('id_karyawan');

        return response()->json([
            'employees' => $employees->map(fn (Employee $employee) => $this->employeePayload(
                $employee,
                $assignments->get($employee->id_karyawan, collect()),
            ))->values(),
        ]);
    }

    public function history(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeEmployee($request->user(), $employee);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $date = $validated['date'] ?? now()->toDateString();
        $query = LocationTracking::query()
            ->where('id_karyawan', $employee->id_karyawan)
            ->whereDate('tanggal', $date)
            ->orderBy('waktu')
            ->orderBy('id_tracking');
        $total = (clone $query)->count();
        $points = $query
            ->limit(5000)
            ->get(['id_tracking', 'tanggal', 'waktu', 'latitude', 'longitude', 'created_at', 'is_simulated', 'keterangan'])
            ->map(fn (LocationTracking $point) => $this->pointPayload($point));
        $assignments = LocationAssignment::query()
            ->where('id_karyawan', $employee->id_karyawan)
            ->orderBy('id_tujuan')
            ->get()
            ->map(function (LocationAssignment $assignment) use ($date): array {
                $visit = $this->assignmentVisit($assignment, $date);

                return $this->assignmentPayload($assignment) + [
                    'geofenceRadiusMeters' => self::GEOFENCE_RADIUS_METERS,
                    'visited' => $visit !== null,
                    'visitedAt' => $visit['recordedAt'] ?? null,
                    'visitIsSimulated' => $visit['isSimulated'] ?? false,
                ];
            });

        return response()->json([
            'date' => $date,
            'points' => $points,
            'assignments' => $assignments,
            'truncated' => $total > $points->count(),
            'total' => $total,
        ]);
    }

    public function storePosition(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Akun ini tidak terhubung ke data karyawan.');

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        $point = LocationTracking::create([
            'id_karyawan' => $employee->id_karyawan,
            'tanggal' => now()->toDateString(),
            'waktu' => now()->format('H:i:s'),
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'keterangan' => isset($data['accuracy']) ? 'Akurasi GPS ±'.round($data['accuracy']).' m' : null,
            'is_simulated' => false,
        ]);

        return response()->json(['point' => $this->pointPayload($point)], 201);
    }

    public function assign(Request $request): JsonResponse
    {
        $this->authorizeManager($request->user());

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['sometimes', 'nullable', 'string', 'in:pin,office,warehouse,store,meeting,target'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $employee = $this->findVisibleEmployee($request->user(), (int) $data['employee_id']);
        $assignment = LocationAssignment::create([
            'id_karyawan' => $employee->id_karyawan,
            'nama_lokasi' => trim($data['name']),
            'keterangan' => isset($data['description']) ? trim($data['description']) : null,
            'ikon' => $data['icon'] ?? 'pin',
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'id_pemberi_tugas' => $request->user()->id_pengguna,
        ]);

        return response()->json(['assignment' => $this->assignmentPayload($assignment)], 201);
    }

    public function updateAssignment(Request $request, LocationAssignment $assignment): JsonResponse
    {
        $this->authorizeManager($request->user());
        $employee = $this->findVisibleEmployee($request->user(), (int) $assignment->id_karyawan);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['sometimes', 'nullable', 'string', 'in:pin,office,warehouse,store,meeting,target'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        abort_unless((int) $assignment->id_karyawan === (int) $employee->id_karyawan, 404);
        $assignment->update([
            'nama_lokasi' => trim($data['name']),
            'keterangan' => isset($data['description']) ? trim($data['description']) : null,
            'ikon' => $data['icon'] ?? $assignment->ikon,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);

        return response()->json(['assignment' => $this->assignmentPayload($assignment->refresh())]);
    }

    public function deleteAssignment(Request $request, LocationAssignment $assignment): JsonResponse
    {
        $this->authorizeManager($request->user());
        $this->findVisibleEmployee($request->user(), (int) $assignment->id_karyawan);
        $assignment->delete();

        return response()->json(['deleted' => true]);
    }

    public function resetSimulation(Request $request): JsonResponse
    {
        $this->authorizeManager($request->user());
        $deleted = LocationTracking::query()->where('is_simulated', true)->delete();

        return response()->json(['deleted' => $deleted]);
    }

    public function storePlace(Request $request): JsonResponse
    {
        $this->authorizeManager($request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $place = LocationPlace::create([
            'nama_lokasi' => trim($data['name']),
            'keterangan' => $data['description'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'id_pembuat' => $request->user()->id_pengguna,
        ]);

        return response()->json(['place' => $this->placePayload($place)], 201);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);
        $response = Http::acceptJson()
            ->withUserAgent('PayrollPTTintaInspirasi/1.0 ('.config('app.url').')')
            ->timeout(8)
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $data['q'],
                'format' => 'jsonv2',
                'limit' => 6,
                'countrycodes' => 'id',
            ]);

        return response()->json($response->throw()->json());
    }

    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $response = Http::acceptJson()
            ->withUserAgent('PayrollPTTintaInspirasi/1.0 ('.config('app.url').')')
            ->timeout(8)
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $data['latitude'],
                'lon' => $data['longitude'],
                'format' => 'jsonv2',
            ]);

        return response()->json($response->throw()->json());
    }

    public function route(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_latitude' => ['required', 'numeric', 'between:-90,90'],
            'from_longitude' => ['required', 'numeric', 'between:-180,180'],
            'to_latitude' => ['required', 'numeric', 'between:-90,90'],
            'to_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $coordinates = implode(',', [
            $data['from_longitude'], $data['from_latitude'],
            $data['to_longitude'], $data['to_latitude'],
        ]);

        $response = Http::acceptJson()
            ->withUserAgent('PayrollPTTintaInspirasi/1.0 ('.config('app.url').')')
            ->timeout(12)
            ->get("https://router.project-osrm.org/route/v1/driving/{$coordinates}", [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'false',
            ]);

        return response()->json($response->throw()->json());
    }

    public function simulate(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeManager($request->user());
        $this->authorizeEmployee($request->user(), $employee);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $point = LocationTracking::create([
            'id_karyawan' => $employee->id_karyawan,
            'tanggal' => now()->toDateString(),
            'waktu' => now()->format('H:i:s'),
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'keterangan' => 'Lokasi simulasi',
            'is_simulated' => true,
        ]);

        return response()->json(['point' => $this->pointPayload($point)], 201);
    }

    private function employeeQuery(User $user): Builder
    {
        return Employee::query()
            ->visibleTo($user)
            ->where('tbl_karyawan.status', 'aktif');
    }

    private function findVisibleEmployee(User $user, int $id): Employee
    {
        return $this->employeeQuery($user)->whereKey($id)->firstOrFail();
    }

    private function authorizeEmployee(User $user, Employee $employee): void
    {
        abort_unless(
            $this->employeeQuery($user)->whereKey($employee->id_karyawan)->exists(),
            403,
            'Anda tidak memiliki akses ke lokasi karyawan ini.',
        );
    }

    private function authorizeManager(User $user): void
    {
        abort_unless($user->isApprover(), 403, 'Hanya admin atau atasan yang dapat mengelola lokasi.');
    }

    private function employeePayload(Employee $employee, Collection $assignments): array
    {
        $latest = $employee->latestLocationTracking;
        $updatedAt = $latest?->created_at;

        return [
            'id' => $employee->id_karyawan,
            'name' => $employee->nama_lengkap,
            'department' => $employee->department?->nama_departemen ?? '-',
            'photo' => $employee->photo_url,
            'location' => $latest ? [
                'latitude' => (float) $latest->latitude,
                'longitude' => (float) $latest->longitude,
                'updatedAt' => $updatedAt?->toIso8601String(),
                'isSimulated' => (bool) $latest->is_simulated,
            ] : null,
            'status' => $updatedAt && $updatedAt->greaterThanOrEqualTo(now()->subMinutes(5))
                ? 'live'
                : 'offline',
            'assignments' => $assignments->map(fn (LocationAssignment $assignment) => $this->assignmentPayload($assignment))->values(),
            'assignment' => $assignments->first() ? $this->assignmentPayload($assignments->first()) : null,
        ];
    }

    private function assignmentVisit(LocationAssignment $assignment, string $date): ?array
    {
        $latitude = (float) $assignment->latitude;
        $longitude = (float) $assignment->longitude;
        $latitudeDelta = self::GEOFENCE_RADIUS_METERS / 111_320;
        $longitudeDelta = self::GEOFENCE_RADIUS_METERS / (111_320 * max(abs(cos(deg2rad($latitude))), 0.01));

        $point = LocationTracking::query()
            ->where('id_karyawan', $assignment->id_karyawan)
            ->whereDate('tanggal', $date)
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
            ->orderBy('waktu')
            ->orderBy('id_tracking')
            ->get(['id_tracking', 'tanggal', 'waktu', 'latitude', 'longitude', 'is_simulated'])
            ->first(fn (LocationTracking $point): bool => $this->distanceMeters(
                $latitude,
                $longitude,
                (float) $point->latitude,
                (float) $point->longitude,
            ) <= self::GEOFENCE_RADIUS_METERS);

        return $point ? $this->pointPayload($point) : null;
    }

    private function assignmentPayload(LocationAssignment $assignment): array
    {
        return [
            'id' => $assignment->id_tujuan,
            'name' => $assignment->nama_lokasi,
            'description' => $assignment->keterangan,
            'icon' => $assignment->ikon,
            'latitude' => (float) $assignment->latitude,
            'longitude' => (float) $assignment->longitude,
        ];
    }

    private function distanceMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $toRadians = static fn (float $degrees): float => $degrees * M_PI / 180;
        $latitudeDelta = $toRadians($latitudeB - $latitudeA);
        $longitudeDelta = $toRadians($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos($toRadians($latitudeA)) * cos($toRadians($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return 6_371_000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function placePayload(LocationPlace $place): array
    {
        return [
            'id' => $place->id_lokasi,
            'name' => $place->nama_lokasi,
            'description' => $place->keterangan,
            'latitude' => (float) $place->latitude,
            'longitude' => (float) $place->longitude,
        ];
    }

    private function pointPayload(LocationTracking $point): array
    {
        return [
            'id' => $point->id_tracking,
            'latitude' => (float) $point->latitude,
            'longitude' => (float) $point->longitude,
            'date' => $point->tanggal,
            'time' => $point->waktu,
            'recordedAt' => CarbonImmutable::parse($point->tanggal.' '.$point->waktu)->toIso8601String(),
            'createdAt' => $point->created_at?->toIso8601String(),
            'isSimulated' => (bool) $point->is_simulated,
            'note' => $point->keterangan,
        ];
    }
}
