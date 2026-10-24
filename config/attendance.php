<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jam Masuk Kerja
    |--------------------------------------------------------------------------
    | Check-in pada atau sebelum jam ini dihitung "On-Time", sesudahnya "Late".
    | Format 24 jam "HH:MM". Ubah lewat .env: ATTENDANCE_WORK_START=08:00
    */
    'work_start' => env('ATTENDANCE_WORK_START', '09:00'),

];
