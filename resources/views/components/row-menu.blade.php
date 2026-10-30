{{-- Menu "Action" di baris tabel. Panel memakai posisi fixed agar tidak terpotong wadah yang bisa di-scroll. --}}
<div x-data="{ open: false, top: 0, left: 0 }" @keydown.escape="open = false" @scroll.window="open = false" @resize.window="open = false" class="inline-block">
    {{-- Tombol pemicu: menghitung posisi panel dari posisi tombol --}}
    <button type="button" aria-haspopup="true" :aria-expanded="open.toString()"
            @click="const r = $el.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 176); open = !open"
            class="inline-flex items-center gap-2 rounded-md border border-blue-600 bg-white px-3 py-1.5 text-xs text-gray-800 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-500">
        Action <x-icon name="chevron-down" size="size-4" />
    </button>

    {{-- Panel menu: isi diberikan lewat slot --}}
    <div x-cloak x-show="open" @click.outside="open = false" x-transition
         :style="'top:' + top + 'px; left:' + left + 'px'"
         class="fixed z-50 w-44 rounded-xl bg-white p-1 text-left shadow-lg ring-1 ring-gray-100">
        {{ $slot }}
    </div>
</div>
