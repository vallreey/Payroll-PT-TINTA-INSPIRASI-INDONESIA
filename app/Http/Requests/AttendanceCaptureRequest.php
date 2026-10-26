<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Validasi kiriman check-in / check-out dari ponsel: foto + koordinat GPS. */
class AttendanceCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'foto' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('attendance.photo_max_kb', 8192)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'foto.required' => 'Foto bukti kehadiran wajib diambil.',
            'foto.image' => 'File harus berupa foto.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'foto.max' => 'Ukuran foto terlalu besar.',
            'foto.uploaded' => 'Foto gagal diunggah. Periksa koneksi lalu coba lagi.',
            'latitude.required' => 'Lokasi tidak ditemukan. Aktifkan GPS lalu coba lagi.',
            'longitude.required' => 'Lokasi tidak ditemukan. Aktifkan GPS lalu coba lagi.',
            'latitude.between' => 'Koordinat lokasi tidak valid.',
            'longitude.between' => 'Koordinat lokasi tidak valid.',
            'latitude.numeric' => 'Koordinat lokasi tidak valid.',
            'longitude.numeric' => 'Koordinat lokasi tidak valid.',
        ];
    }

    public function after(): array
    {
        return [
            // (0,0) hampir selalu berarti GPS belum mendapat sinyal, bukan lokasi sebenarnya.
            function (Validator $validator) {
                if ((float) $this->input('latitude') === 0.0 && (float) $this->input('longitude') === 0.0) {
                    $validator->errors()->add('latitude', 'Lokasi tidak ditemukan. Aktifkan GPS lalu coba lagi.');
                }
            },
        ];
    }
}
