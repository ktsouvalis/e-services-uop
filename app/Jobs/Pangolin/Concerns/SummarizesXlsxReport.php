<?php

namespace App\Jobs\Pangolin\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

trait SummarizesXlsxReport
{
    /**
     * Tally how many rows fall under each value of a given status column,
     * reading the report xlsx directly rather than scraping process stdout.
     */
    private function summarizeStatusColumn(string $path, string $sheetName, int $columnIndex): array
    {
        $sheet = IOFactory::load($path)->getSheetByName($sheetName);
        if (! $sheet) {
            return [];
        }

        $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);

        $counts = [];
        foreach ($sheet->getRowIterator(2) as $row) {
            $value = (string) $sheet->getCell($columnLetter.$row->getRowIndex())->getValue();
            if ($value === '') {
                continue;
            }
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $counts;
    }
}
