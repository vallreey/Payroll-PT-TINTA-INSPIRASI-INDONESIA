<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - PT. Tinta Inspirasi Indonesia</title>

    {{-- Jika pakai Laravel Vite (disarankan): --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Alternatif cepat tanpa build step (Tailwind + Alpine via CDN), hapus @vite di atas jika pakai ini --}}
    {{-- <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}
</head>
<body class="font-sans antialiased">
    {{ $slot }}
</body>
</html>
