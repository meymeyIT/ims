{{-- Delete / archive button with a SweetAlert2 confirmation. --}}
@props([
    'action',
    'message',
    'title' => 'Delete',
    'icon' => 'trash',
    'confirm' => 'Delete',
    'label' => null,
    'buttonClass' => 'btn btn-sm btn-light text-danger',
])

<form method="POST" action="{{ $action }}" class="d-inline" data-confirm="{{ $message }}" data-confirm-button="{{ $confirm }}">
    @csrf
    @method('DELETE')
    <button type="submit" class="{{ $buttonClass }}" title="{{ $title }}">
        <i class="bi bi-{{ $icon }}"></i>@if ($label) {{ $label }}@endif
    </button>
</form>
