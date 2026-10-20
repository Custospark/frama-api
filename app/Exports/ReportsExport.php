<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ReportsExport implements FromArray, WithHeadings, ShouldAutoSize, WithColumnFormatting
{
    /**
     * @param  Collection<int, \App\Models\Report>  $reports
     * @param  array<int, array{key: string, label: string}>  $customColumns
     */
    public function __construct(
        private readonly Collection $reports,
        private readonly array $customColumns,
    ) {
    }

    public function headings(): array
    {
        return array_merge(
            ['Date', 'Branch', 'Rooms', 'Bar', 'Food', 'Sauna', 'Other', 'Rooms Occupied', 'Expected This Semester', 'Collected So Far', 'Total', 'Expenses', 'Cash Handed Over', 'Comment', 'Manager', 'Status'],
            array_map(fn (array $column) => $column['label'], $this->customColumns),
        );
    }

    public function array(): array
    {
        return $this->reports->map(function ($report) {
            $values = $report->custom_values ?? [];
            $customCells = array_map(fn (array $column) => $values[$column['key']] ?? '', $this->customColumns);

            return array_merge([
                $report->report_date?->toDateString(),
                $report->branch?->branch_name,
                $report->rooms_rev,
                $report->bar_rev,
                $report->food_rev,
                $report->sauna_rev,
                $report->other_rev,
                $report->rooms_occupied,
                $report->amount_expected_semester,
                $report->collected_so_far,
                $report->total_rev,
                $report->expenses,
                $report->cash_handed_over,
                $report->comment,
                $report->manager_name,
                $report->submitted_at ? 'Submitted' : 'Pending',
            ], $customCells);
        })->all();
    }

    public function columnFormats(): array
    {
        $formats = [
            'C' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'D' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'H' => NumberFormat::FORMAT_NUMBER,
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'K' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'M' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];

        $start = 17; // Column Q, after the fixed columns.
        foreach ($this->customColumns as $index => $column) {
            $formats[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($start + $index)] = NumberFormat::FORMAT_GENERAL;
        }

        return $formats;
    }
}
