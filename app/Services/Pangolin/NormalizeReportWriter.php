<?php

namespace App\Services\Pangolin;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ported from normalize_private_resources.py's write_report() — a fresh
 * single-sheet workbook (unlike ImportReportWriter, which appends to the
 * uploaded input file), same headers/status-colors/column-widths as the
 * Python original.
 */
class NormalizeReportWriter
{
    private const HEADERS = ['SiteResourceId', 'NiceId', 'NewNiceId', 'Enabled', 'NewEnabled', 'Mode',
        'Destination', 'City', 'TargetEmail', 'OldName', 'NewName', 'Action',
        'RemovedUsers', 'CurrentUsers', 'Roles', 'Status', 'Reason', 'Timestamp'];

    private const STATUS_COLORS = ['OK' => 'C6EFCE', 'FAIL' => 'FFC7CE', 'DRY-RUN' => 'FFEB9C', 'SKIPPED' => 'D9D9D9'];

    private const WIDTHS = [8, 26, 26, 8, 10, 6, 16, 12, 24, 24, 24, 18, 26, 30, 12, 10, 50, 18];

    public function write(array $rows, string $outPath): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Normalize Report');

        foreach (self::HEADERS as $c => $header) {
            $letter = Coordinate::stringFromColumnIndex($c + 1);
            $cell = $sheet->getCell("{$letter}1");
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $cell->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F4E78');
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $statusCol = array_search('Status', self::HEADERS, true) + 1;
        foreach (array_values($rows) as $r => $row) {
            $excelRow = $r + 2;
            $values = [
                $row['resource_id'], $row['nice_id'], $row['new_nice_id'], $row['enabled'],
                $row['new_enabled'], $row['mode'], $row['destination'], $row['city'],
                $row['target_email'], $row['old_name'], $row['new_name'], $row['action'],
                $row['removed_users'], $row['current_users'], $row['roles'],
                $row['status'], $row['reason'], $row['timestamp'],
            ];
            foreach ($values as $c => $value) {
                $letter = Coordinate::stringFromColumnIndex($c + 1);
                $sheet->getCell("{$letter}{$excelRow}")->setValue($value);
            }
            $fillColor = self::STATUS_COLORS[$row['status']] ?? null;
            if ($fillColor) {
                $letter = Coordinate::stringFromColumnIndex($statusCol);
                $sheet->getCell("{$letter}{$excelRow}")->getStyle()->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB("FF{$fillColor}");
            }
        }

        foreach (self::WIDTHS as $c => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c + 1))->setWidth($width);
        }

        (new Xlsx($spreadsheet))->save($outPath);
    }
}
