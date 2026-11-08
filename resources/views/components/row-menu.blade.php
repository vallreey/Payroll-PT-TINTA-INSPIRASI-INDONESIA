@props(['label' => 'Action', 'iconOnly' => false])
{{-- Menu aksi di baris tabel (tombol "Action" atau ikon ⋮ bila icon-only). Panel memakai posisi fixed agar tidak terpotong wadah yang bisa di-scroll. --}}
<div x-data="{ open: false, top: 0, left: 0 }" @keydown.escape="open = false" @scroll.window="open = false" @resize.window="open = false" class="inline-block">
    {{-- Tombol pemicu: menghitung posisi panel dari posisi tombol --}}
    <button type="button" aria-haspopup="true" :aria-expanded="open.toString()" aria-label="{{ $label }}"
            @click="const r = $el.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 192); open = !open"
            @class([
                'inline-flex items-center gap-2 border border-blue-600 bg-white text-gray-800 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-500',
                'size-9 justify-center rounded-lg' => $iconOnly,
                'rounded-md px-3 py-1.5 text-xs' => ! $iconOnly,
            ])>
        @if ($iconOnly)
            <x-icon name="dots-vertical" size="size-4" />
        @else
            Action <x-icon name="chevron-down" size="size-4" />
        @endif
    </button>

    {{-- Panel menu: isi diberikan lewat slot --}}
    <div x-cloak x-show="open" @click.outside="open = false" x-transition
         :style="'top:' + top + 'px; left:' + left + 'px'"
         class="fixed z-50 w-48 rounded-xl bg-white p-1 text-left shadow-lg ring-1 ring-gray-100">
        {{ $slot }}
    </div>
</div>
