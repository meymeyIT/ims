@php
    $flashTypes = [
        'success' => ['success', 'check-circle-fill'],
        'error' => ['danger', 'x-circle-fill'],
        'warning' => ['warning', 'exclamation-triangle-fill'],
        'info' => ['info', 'info-circle-fill'],
    ];
@endphp

@foreach ($flashTypes as $key => [$color, $icon])
    @if (session($key))
        <div class="alert alert-{{ $color }} alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-{{ $icon }} mt-1"></i>
            <div>{{ session($key) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach
