<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $date = $request->query('date', '2026-06-21');
        $division = $request->query('division', 'all');
        $status = $request->query('status', 'all');

        $employees = [
            [
                'name' => 'Ageng Saputro',
                'division' => 'Divisi A',
                'check_in' => '09.00',
                'check_out' => '16.00',
                'evidence' => 'Img 1',
                'status' => 'On-Time',
                'status_key' => 'on_time',
            ],
            [
                'name' => 'Eka Bayu Wiguna',
                'division' => 'Divisi A',
                'check_in' => '09.10',
                'check_out' => '16.00',
                'evidence' => 'Img 2',
                'status' => 'Late',
                'status_key' => 'late',
            ],
            [
                'name' => 'Joko Mulia',
                'division' => 'Divisi A',
                'check_in' => '09.00',
                'check_out' => '16.00',
                'evidence' => 'Img 3',
                'status' => 'On-Time',
                'status_key' => 'on_time',
            ],
            [
                'name' => 'Maya Lestari',
                'division' => 'Divisi B',
                'check_in' => '08.50',
                'check_out' => '15.50',
                'evidence' => 'Img 4',
                'status' => 'On-Time',
                'status_key' => 'on_time',
            ],
            [
                'name' => 'Rizki Pratama',
                'division' => 'Divisi B',
                'check_in' => '09.35',
                'check_out' => '17.00',
                'evidence' => 'Img 5',
                'status' => 'Late',
                'status_key' => 'late',
            ],
        ];

        $filtered = array_values(array_filter($employees, function (array $employee) use ($search, $division, $status) {
            if ($search !== '' && stripos($employee['name'], $search) === false) {
                return false;
            }

            if ($division !== 'all' && $employee['division'] !== $division) {
                return false;
            }

            if ($status !== 'all' && $employee['status'] !== $status) {
                return false;
            }

            return true;
        }));

        $present = [
            'on_time' => count(array_filter($filtered, fn (array $employee) => $employee['status_key'] === 'on_time')),
            'early_clock_in' => 0,
            'late_clock_in' => count(array_filter($filtered, fn (array $employee) => $employee['status_key'] === 'late')),
        ];

        $absent = [
            'leaved' => 0,
            'no_clock_in' => 0,
            'no_clock_out' => 0,
            'day_off' => 0,
            'time_off' => 0,
        ];

        $away = [
            'total' => 0,
        ];

        $summary = [
            'present' => $present,
            'absent' => $absent,
            'away' => $away,
        ];

        return view('attendance.index', [
            'pageTitle' => 'Absensi',
            'search' => $search,
            'date' => $date,
            'division' => $division,
            'status' => $status,
            'rows' => $filtered,
            'summary' => $summary,
            'divisions' => ['all' => 'Semua Divisi', 'Divisi A' => 'Divisi A', 'Divisi B' => 'Divisi B'],
            'statuses' => ['all' => 'Semua Status', 'On-Time' => 'On-Time', 'Late' => 'Late'],
        ]);
    }
}
