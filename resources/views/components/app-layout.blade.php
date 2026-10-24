<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} - PT. Tinta Inspirasi Indonesia</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50 min-h-screen">

    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logoPT.png') }}" alt="Logo" class="h-9 w-auto">
                <span class="font-semibold text-gray-800 hidden sm:inline">Tinta Inspirasi Indonesia</span>
            </div>

            <div class="flex items-center gap-4">
                @auth
                    @if (auth()->user()->role === 'super_admin')
                        <a href="{{ route('admin.user-approvals.index') }}"
                           class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                            Persetujuan Akun
                        </a>
                    @endif

                    <span class="text-sm text-gray-500 hidden sm:inline">{{ auth()->user()->name }}</span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-gray-500 hover:text-red-600">
                            Logout
                        </button>
                    </form>
                @endauth
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-6 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>

</body>
</html>
