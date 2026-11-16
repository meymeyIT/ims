{{-- Table shared by the print view and the PDF. $plain = true uses currency codes (PDF fonts). --}}
@php
    $plain = $plain ?? false;
    $columns = $report->columns();
@endphp
<table class="table table-bordered table-sm report-doc">
    <thead class="table-light">
    <tr>
        @foreach ($columns as $column)
            <th class="{{ App\Reports\Report::isNumeric($column['type']) ? 'text-end num' : '' }}">{{ $column['label'] }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
        <tr>
            @foreach ($columns as $key => $column)
                <td class="{{ App\Reports\Report::isNumeric($column['type']) ? 'text-end num' : '' }}">{{ App\Reports\Report::format($row[$key] ?? null, $column['type'], $plain) }}</td>
            @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($columns) }}" class="text-center">No records for these filters.</td></tr>
    @endforelse
    </tbody>
    @if ($totals && $rows->isNotEmpty())
        <tfoot>
        <tr class="fw-bold totals">
            @foreach (array_keys($columns) as $i => $key)
                <td class="{{ App\Reports\Report::isNumeric($columns[$key]['type']) ? 'text-end num' : '' }}">
                    @if ($i === 0)
                        {{ $report->totalsLabel() }}
                    @elseif (array_key_exists($key, $totals))
                        {{ is_numeric($totals[$key]) ? App\Reports\Report::format($totals[$key], $columns[$key]['type'], $plain) : $totals[$key] }}
                    @endif
                </td>
            @endforeach
        </tr>
        </tfoot>
    @endif
</table>
@if ($report->note())
    <p class="small note"><em>{{ $report->note() }}</em></p>
@endif
