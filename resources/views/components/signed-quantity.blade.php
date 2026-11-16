{{-- "+10 pcs" in green or "-3 pcs" in red. --}}
@props(['quantity', 'unit' => null])

@php $increase = bccomp((string) $quantity, '0', 3) > 0; @endphp
<span {{ $attributes->merge(['class' => 'fw-semibold text-nowrap ' . ($increase ? 'text-success' : 'text-danger')]) }}>
    {{ $increase ? '+' : '' }}{{ App\Support\Quantity::format($quantity) }}
    @if ($unit)<span class="small text-body-secondary fw-normal">{{ $unit }}</span>@endif
</span>
