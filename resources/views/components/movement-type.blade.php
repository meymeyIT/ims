{{-- Movement type badge, with a "reversal" marker when the movement corrects another one. --}}
@props(['movement'])

<span class="badge text-bg-{{ $movement->type->badge() }}-subtle text-{{ $movement->type->badge() }}-emphasis">{{ $movement->type->label() }}</span>
@if ($movement->reversal_of_id)
    <span class="badge text-bg-warning-subtle text-warning-emphasis" title="Reversal of {{ $movement->reversalOf?->movement_number }}"><i class="bi bi-arrow-counterclockwise"></i> Reversal</span>
@endif
