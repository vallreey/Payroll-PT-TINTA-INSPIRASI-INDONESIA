<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Support\Collection;

/** SDD "NotificationService": mengisi tbl_notifikasi. */
class NotificationService
{
    public const TYPES = ['izin', 'cuti', 'absensi', 'gaji'];

    /**
     * @param  User|iterable<User>  $recipients
     */
    public function send(User|iterable $recipients, string $judul, string $pesan, string $tipe): void
    {
        if (! in_array($tipe, self::TYPES, true)) {
            throw new \InvalidArgumentException("Tipe notifikasi tidak dikenal: {$tipe}");
        }

        $ids = Collection::wrap($recipients instanceof User ? [$recipients] : $recipients)
            ->map(fn (User $user) => $user->id_pengguna)
            ->unique()
            ->values();

        foreach ($ids as $id) {
            Notifikasi::create([
                'id_pengguna' => $id,
                'judul' => mb_substr($judul, 0, 200),
                'pesan' => $pesan,
                'tipe' => $tipe,
            ]);
        }
    }
}
