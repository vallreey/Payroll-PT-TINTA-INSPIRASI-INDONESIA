<x-guest-layout>

    <div class="min-h-screen w-full flex items-center justify-center bg-white px-6">
        <div class="w-full max-w-md">

            @if (session('status'))
                <div class="mb-4 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <h1 class="text-3xl font-bold text-gray-900 text-center">Login</h1>
            <p class="mt-2 text-gray-400 text-center">Selamat Datang Kembali</p>

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                           placeholder="Email"
                           class="w-full rounded-xl border border-gray-200 px-5 py-3.5 text-gray-700 placeholder-gray-400 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400 focus:outline-none transition">
                    @error('email')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <input id="password" type="password" name="password" required
                           placeholder="Password"
                           class="w-full rounded-xl border border-gray-200 px-5 py-3.5 text-gray-700 placeholder-gray-400 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400 focus:outline-none transition">
                    @error('password')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                @error('auth')
                    <p class="text-sm text-red-500">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="w-full rounded-xl bg-gradient-to-r from-cyan-400 to-indigo-600 py-3.5 text-white font-semibold shadow-md hover:opacity-95 transition">
                    Login
                </button>

                <p class="text-center text-sm text-gray-500">
                    Belum punya akun?
                    <a href="{{ route('register') }}" class="text-indigo-600 font-medium">Register</a>
                </p>
            </form>
        </div>
    </div>

</x-guest-layout>
