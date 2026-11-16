{{-- Accessible table view of a chart's data (spec: charts must not rely on colour alone). --}}
@props(['labels', 'series'])

<details class="chart-table mt-2">
    <summary class="small text-body-secondary">View as table</summary>
    <div class="table-responsive mt-2">
        <table class="table table-sm small mb-0">
            <thead>
            <tr>
                <th></th>
                @foreach ($series as $name => $values)<th class="text-end">{{ $name }}</th>@endforeach
            </tr>
            </thead>
            <tbody>
            @foreach ($labels as $i => $label)
                <tr>
                    <td>{{ $label }}</td>
                    @foreach ($series as $values)<td class="text-end">{{ is_numeric($values[$i] ?? null) ? App\Support\Quantity::format($values[$i]) : ($values[$i] ?? '') }}</td>@endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</details>
