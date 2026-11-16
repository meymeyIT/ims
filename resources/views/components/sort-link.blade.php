{{-- Clickable table header that toggles sorting, keeping the other filters. --}}
@props(['column', 'label', 'sort', 'direction'])

@php
    $isCurrent = $sort === $column;
    $next = $isCurrent && $direction === 'asc' ? 'desc' : 'asc';
@endphp

<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $next, 'page' => null]) }}"
   class="text-reset text-decoration-none d-inline-flex align-items-center gap-1">
    {{ $label }}
    @if ($isCurrent)
        <i class="bi bi-caret-{{ $direction === 'asc' ? 'up' : 'down' }}-fill small"></i>
    @else
        <i class="bi bi-chevron-expand small opacity-50"></i>
    @endif
</a>
