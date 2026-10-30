{{-- Pembungkus bidang formulir: label, isian (slot), petunjuk, dan pesan error --}}
@props(['name', 'label', 'hint' => null, 'required' => false])

<div {{ $attributes->class(['min-w-0']) }}>
    <label for="{{ $name }}" class="mb-1.5 block text-xs font-medium text-gray-700">
        {{ $label }}@if ($required) <span class="text-red-600" aria-hidden="true">*</span>@endif
    </label>
    {{ $slot }}
    @if ($hint)
        <p class="mt-1 text-[11px] text-gray-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-700" role="alert">{{ $message }}</p>
    @enderror
</div>
