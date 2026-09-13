<x-guest-layout>

    <div class="min-h-screen w-full flex flex-col lg:flex-row bg-white">

        {{-- ================= LEFT / FORM SIDE ================= --}}
        <div class="relative w-full lg:w-1/2 flex flex-col px-6 sm:px-10 lg:px-16 py-8 lg:py-12">

            {{-- Mobile background image (only visible < lg) --}}
            <div class="lg:hidden absolute inset-0 -z-10">
                <img src="{{ asset('images/buildings.jpg') }}"
                     alt=""
                     class="w-full h-full object-cover opacity-15">
                <div class="absolute inset-0 bg-white/70"></div>
            </div>

            {{-- Top bar: logo + login button (desktop) --}}
            <div class="flex items-center justify-between mb-8 lg:mb-10">
                <div class="mx-auto lg:mx-0">
                    <img src="{{ asset('images/logoPT.png') }}" alt="Logo"
                         class="h-16 sm:h-20 lg:h-24 w-auto">
                </div>

                <a href="{{ route('login') }}"
                   class="hidden lg:inline-flex items-center justify-center px-6 py-2 rounded-full border border-indigo-400 text-indigo-600 font-medium hover:bg-indigo-50 transition-colors">
                    Login
                </a>
            </div>

            {{-- Form container --}}
            <div class="w-full max-w-md mx-auto lg:mx-0">

                <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 text-center lg:text-left">
                    Register
                </h1>
                <p class="mt-2 text-gray-400 text-center lg:text-left">
                    Selamat Datang di Perusahaan
                </p>

                <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                    @csrf

                    {{-- Nama Lengkap --}}
                    <div>
                        <input id="name" type="text" name="name"
                               value="{{ old('name') }}" required autofocus autocomplete="name"
                               placeholder="Nama Lengkap"
                               class="w-full rounded-xl border border-gray-200 bg-white/90 px-5 py-3.5 text-gray-700 placeholder-gray-400 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400 focus:outline-none transition">
                        @error('name')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- NIK --}}
                    <div>
                        <input id="nik" type="text" name="nik" inputmode="numeric"
                               value="{{ old('nik') }}" required
                               placeholder="NIK"
                               class="w-full rounded-xl border border-gray-200 bg-white/90 px-5 py-3.5 text-gray-700 placeholder-gray-400 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400 focus:outline-none transition">
                        @error('nik')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <input id="email" type="email" name="email"
                               value="{{ old('email') }}" required autocomplete="username"
                               placeholder="Email"
                               class="w-full rounded-xl border border-gray-200 bg-white/90 px-5 py-3.5 text-gray-700 placeholder-gray-400 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400 focus:outline-none transition">
                        @error('email')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div class="relative" x-data="{ show: false }">
                        <input :type="show ? 'text' : 'password'" id="password" name="password"
                               required autocomplete="new-password"
                               placeholder="Password"
                               class="w-full rounded-xl border border-gray-200 bg-white/90 px-5 py-3.5 pr-12 text-gray-700 placeholder-gray-400 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400 focus:outline-none transition">
                        <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-4 flex items-center text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                            <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                        @error('password')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Ingat saya + Lupa password --}}
                    <div class="flex items-center justify-between pt-1">
                        <label class="inline-flex items-center gap-2 select-none cursor-pointer">
                            <input type="checkbox" name="remember"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400">
                            <span class="text-sm text-gray-600">Ingat Saya</span>
                        </label>

                        <a href="{{ route('password.request') }}" class="text-sm text-indigo-500 hover:text-indigo-700">
                            Lupa Password?
                        </a>
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                            class="w-full rounded-xl bg-gradient-to-r from-cyan-400 to-indigo-600 py-3.5 text-white font-semibold shadow-md hover:opacity-95 active:scale-[0.99] transition">
                        Register
                    </button>

                    {{-- Mobile-only: sudah punya akun --}}
                    <p class="lg:hidden text-center text-sm text-gray-500">
                        Sudah Memiliki akun?
                        <a href="{{ route('login') }}" class="text-indigo-600 font-medium">Login</a>
                    </p>

                    {{-- Divider --}}
                    <div class="flex items-center gap-4 py-1">
                        <span class="flex-1 h-px bg-gray-200"></span>
                        <span class="text-sm text-gray-400">atau</span>
                        <span class="flex-1 h-px bg-gray-200"></span>
                    </div>

                    {{-- Google --}}
                    <a href="{{ route('auth.google') }}"
                       class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white py-3.5 text-gray-700 font-medium hover:bg-gray-50 transition">
                        Register dengan Google
                        <svg class="h-5 w-5" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47a5.54 5.54 0 01-2.4 3.63v3h3.88c2.27-2.09 3.54-5.17 3.54-8.87z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.87-3a7.4 7.4 0 01-4.06 1.16c-3.13 0-5.78-2.11-6.73-4.96H1.27v3.11A12 12 0 0012 24z"/>
                            <path fill="#FBBC05" d="M5.27 14.29a7.2 7.2 0 010-4.58V6.6H1.27a12 12 0 000 10.8l4-3.11z"/>
                            <path fill="#EA4335" d="M12 4.77c1.76 0 3.34.61 4.59 1.8l3.44-3.44C17.94 1.19 15.24 0 12 0A12 12 0 001.27 6.6l4 3.11C6.22 6.88 8.87 4.77 12 4.77z"/>
                        </svg>
                    </a>
                </form>
            </div>
        </div>

        {{-- ================= RIGHT / IMAGE SIDE (desktop only) ================= --}}
        <div class="hidden lg:block lg:w-1/2 relative overflow-hidden">
            <img src="{{ asset('images/buildings.png') }}"
                 alt="Gedung perkantoran"
                 class="absolute inset-0 w-full h-full object-cover">
        </div>

    </div>

</x-guest-layout>
