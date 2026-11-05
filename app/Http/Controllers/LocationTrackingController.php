<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LocationAssignment;
use App\Models\LocationPlace;
use App\Models\LocationTracking;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class LocationTrackingController extends Controller
{
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
            ->keyBy('id_karyawan');

        $places = $user->isApprover()
            ? LocationPlace::query()->orderBy('nama_lokasi')->get()
            : collect();

        return view('tracking.index', [
            'user' => $user,
            'isManager' => $user->isApprover(),
            'employees' => $employees->map(fn (Employee $employee) => $this->employeePayload(
                $employee,
                $assignments->get($employee->id_karyawan),
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
            ->keyBy('id_karyawan');

        return response()->json([
            'employees' => $employees->map(fn (Employee $employee) => $this->employeePayload(
                $employee,
                $assignments->get($employee->id_karyawan),
            ))->values(),
        ]);
    }

    public function history(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeEmployee($request->user(), $employee);

        $points = LocationTracking::query()
            ->where('id_karyawan', $employee->id_karyawan)
            ->latest('id_tracking')
            ->limit(300)
            ->get(['id_tracking', 'latitude', 'longitude', 'created_at', 'is_simulated'])
            ->reverse()
            ->values()
            ->map(fn (LocationTracking $point) => $this->pointPayload($point));

        return response()->json(['points' => $points]);
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $employee = $this->findVisibleEmployee($request->user(), (int) $data['employee_id']);
        $assignment = LocationAssignment::updateOrCreate(
            ['id_karyawan' => $employee->id_karyawan],
            [
                'nama_lokasi' => $data['name'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'id_pemberi_tugas' => $request->user()->id_pengguna,
            ],
        );

        return response()->json(['assignment' => [
            'name' => $assignment->nama_lokasi,
            'latitude' => (float) $assignment->latitude,
            'longitude' => (float) $assignment->longitude,
        ]]);
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

    private function employeePayload(Employee $employee, ?LocationAssignment $assignment): array
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
            'assignment' => $assignment ? [
                'name' => $assignment->nama_lokasi,
                'latitude' => (float) $assignment->latitude,
                'longitude' => (float) $assignment->longitude,
            ] : null,
        ];
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
            'latitude' => (float) $point->latitude,
            'longitude' => (float) $point->longitude,
            'createdAt' => $point->created_at?->toIso8601String(),
            'isSimulated' => (bool) $point->is_simulated,
        ];
    }
}
