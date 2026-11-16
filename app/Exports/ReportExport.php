<?php

namespace App\Exports;

use App\Reports\Report;
use App\Reports\ReportFilters;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Generic .xlsx export for any Report (Laravel Excel).
 *
 * Layout: title (row 1), business + filters (row 2), headings (row 4), data, totals, note.
 * Numbers are written as real numbers with number formats, so the sheet can be
 * summed and sorted in Excel. Rows are read from the database in chunks.
 */
class ReportExport extends DefaultValueBinder implements FromQuery, WithMapping, WithHeadings, WithColumnFormatting,
    ShouldAutoSize, WithTitle, WithEvents, WithCustomStartCell, WithCustomValueBinder
{
    private const FORMATS = [
        'int' => '#,##0',
        'qty' => '#,##0.###',
        'money' => '#,##0.00',
        'cost' => '#,##0.00##',
        'percent' => '0.0"%"',
        'date' => 'dd/mm/yyyy',
        'datetime' => 'dd/mm/yyyy hh:mm',
    ];

    private const FIRST_DATA_ROW = 5; // headings are on row 4

    public function __construct(
        private readonly Report $report,
        private readonly ReportFilters $filters,
        private readonly string $filterLine,
        private readonly string $businessName,
    ) {
        $this->report->prepare($this->filters);
    }

    public function query(): Builder
    {
        return $this->report->query($this->filters);
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function title(): string
    {
        return mb_substr($this->report->title(), 0, 31); // Excel sheet-name limit
    }

    public function headings(): array
    {
        return array_column($this->report->columns(), 'label');
    }

    public function map($record): array
    {
        $row = $this->report->row($record);

        return array_map(
            fn (string $key, array $column) => $this->cellValue($row[$key] ?? null, $column['type']),
            array_keys($this->report->columns()),
            $this->report->columns(),
        );
    }

    public function columnFormats(): array
    {
        $formats = [];
        foreach (array_values($this->report->columns()) as $i => $column) {
            if (isset(self::FORMATS[$column['type']])) {
                $formats[Coordinate::stringFromColumnIndex($i + 1)] = self::FORMATS[$column['type']];
            }
        }

        return $formats;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $columns = $this->report->columns();
                $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

                // Title block
                $sheet->setCellValue('A1', $this->report->title());
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->setCellValue('A2', "{$this->businessName} · {$this->filterLine} · Exported " . now()->format('d/m/Y H:i'));
                $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');

                // Headings
                $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEF2F7']],
                ]);
                $sheet->freezePane('A' . self::FIRST_DATA_ROW);

                // Totals row
                $totals = $this->report->totals($this->filters);
                if ($totals !== []) {
                    $row = $sheet->getHighestRow() + 1;
                    $sheet->setCellValue("A{$row}", $this->report->totalsLabel());
                    foreach (array_keys($columns) as $i => $key) {
                        if ($i > 0 && array_key_exists($key, $totals)) {
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $row, $this->cellValue($totals[$key], $columns[$key]['type']));
                        }
                    }
                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                        'font' => ['bold' => true],
                        'borders' => ['top' => ['borderStyle' => 'thin']],
                    ]);
                }

                if ($note = $this->report->note()) {
                    $noteRow = $sheet->getHighestRow() + 2;
                    $sheet->setCellValue("A{$noteRow}", $note);
                    $sheet->getStyle("A{$noteRow}")->getFont()->setItalic(true);
                }
            },
        ];
    }

    /**
     * Security: every string is stored as plain TEXT, never as a formula.
     * Without this, a product named "=HYPERLINK(...)" would become a live formula in Excel.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    private function cellValue(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return ExcelDate::dateTimeToExcel($value);
        }

        if (is_string($value) && ! Report::isNumeric($type)) {
            return $value;
        }

        return is_numeric($value) ? (float) $value : (string) $value;
    }
}
