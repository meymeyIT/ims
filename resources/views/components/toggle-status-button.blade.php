{{-- Activate / deactivate button with a SweetAlert2 confirmation. --}}
@props(['action', 'active', 'name'])

<form method="POST" action="{{ $action }}" class="d-inline"
      data-confirm="{{ $active ? "Deactivate \"{$name}\"? It will no longer be available for new records." : "Activate \"{$name}\"?" }}"
      data-confirm-button="{{ $active ? 'Deactivate' : 'Activate' }}"
      data-confirm-color="{{ $active ? '#fd7e14' : '#198754' }}">
    @csrf
    @method('PATCH')
    <button type="submit" class="btn btn-sm btn-light" title="{{ $active ? 'Deactivate' : 'Activate' }}">
        <i class="bi bi-{{ $active ? 'pause-circle' : 'play-circle' }}"></i>
    </button>
</form>
