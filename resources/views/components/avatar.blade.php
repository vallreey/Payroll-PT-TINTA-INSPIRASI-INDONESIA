@props(['name' => '', 'photo' => null, 'size' => 'size-10'])
@php
    $initials = \Illuminate\Support\Str::of($name)->explode(' ')->filter()->take(2)
        ->map(fn ($w) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($w, 0, 1)))->implode('');
@endphp
@if ($photo)
    <img src="{{ $photo }}" alt="" loading="lazy" {{ $attributes->class([$size, 'shrink-0 rounded-full bg-gray-100 object-cover']) }}>
@else
    <span {{ $attributes->class([$size, 'flex shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700']) }} aria-hidden="true">{{ $initials }}</span>
@endif
