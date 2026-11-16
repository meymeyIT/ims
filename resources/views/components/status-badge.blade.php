@props(['active' => true])

@if ($active)
    <span {{ $attributes->merge(['class' => 'badge text-bg-success-subtle text-success-emphasis']) }}>Active</span>
@else
    <span {{ $attributes->merge(['class' => 'badge text-bg-secondary-subtle text-secondary-emphasis']) }}>Inactive</span>
@endif
