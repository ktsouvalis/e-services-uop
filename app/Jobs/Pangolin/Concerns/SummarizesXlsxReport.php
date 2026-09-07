<?php

namespace App\Jobs\Pangolin\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

trait SummarizesXlsxReport
{
    /**
     * Tally how many rows fall under each value of a given status column,
     * reading the report xlsx directly rather than scraping process stdout.
     *
     * Looked up by header name (row 1) rather than a fixed column index --
     * the pangolin-utils Python scripts' write_report() functions have
     * changed column layout twice in one session (adding Enabled/NewEnabled,
     * then dropping Clients), silently breaking a hardcoded index each time
     * without any error, just a nonsense summary. This makes that whole
     * class of bug impossible instead of re-fixing a magic number again.
     */
    private function summarizeStatusColumn(string $path, string $sheetName, string $columnName): array
    {
        $sheet = IOFactory::load($path)->getSheetByName($sheetName);
        if (! $sheet) {
            return [];
        }

        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn(1));
        $columnLetter = null;
        for ($i = 1; $i <= $highestColumnIndex; $i++) {
            $letter = Coordinate::stringFromColumnIndex($i);
            if ((string) $sheet->getCell($letter.'1')->getValue() === $columnName) {
                $columnLetter = $letter;
                break;
            }
        }
        if ($columnLetter === null) {
            throw new \RuntimeException("Column '{$columnName}' not found in sheet '{$sheetName}' of {$path}");
        }

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
