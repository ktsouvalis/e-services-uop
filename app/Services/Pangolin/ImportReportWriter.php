<?php

namespace App\Services\Pangolin;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ported from create_private_resources.py's write_report() — appends/
 * replaces a "Results" sheet on the original input workbook (so the
 * "Requests" sheet the user uploaded stays in the output file too) and
 * saves alongside it as "<input>_results_<timestamp>.xlsx", same column
 * headers/colors/widths as the Python original.
 *
 * Note: this installed PhpSpreadsheet version (3.x) has no
 * getCellByColumnAndRow() — cells are addressed via getCell($letter.$row),
 * the letter built with Coordinate::stringFromColumnIndex() (see CLAUDE.md).
 */
class ImportReportWriter
{
    private const HEADERS = ['Row', 'City', 'Name', 'Destination', 'Alias', 'TCP Ports', 'Email', 'Notes', 'Sites',
        'Status', 'Nice ID', 'Enabled', 'Timestamp', 'Error'];

    private const STATUS_COLORS = [
        'OK' => 'C6EFCE',
        'FAIL' => 'FFC7CE',
        'DRY-RUN' => 'FFEB9C',
        'OK_NO_USER' => 'FCE4D6',
        'DRY-RUN_NO_USER' => 'FFF2CC',
    ];

    private const WIDTHS = [6, 14, 24, 22, 22, 14, 26, 30, 30, 10, 26, 10, 18, 40];

    public function write(Spreadsheet $inputWorkbook, string $inputPath, array $results): string
    {
        if ($inputWorkbook->sheetNameExists('Results')) {
            $inputWorkbook->removeSheetByIndex($inputWorkbook->getIndex($inputWorkbook->getSheetByName('Results')));
        }
        $sheet = $inputWorkbook->createSheet();
        $sheet->setTitle('Results');

        foreach (self::HEADERS as $c => $header) {
            $letter = Coordinate::stringFromColumnIndex($c + 1);
            $cell = $sheet->getCell("{$letter}1");
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $cell->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F4E78');
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach (array_values($results) as $r => $res) {
            $row = $r + 2;
            $values = [
                $res['row_num'], $res['city'], $res['name'], $res['destination'], $res['alias'],
                $res['tcp_ports'], $res['email'], $res['notes'], $res['sites'],
                $res['status'], $res['nice_id'], $res['enabled'], $res['timestamp'], $res['error'],
            ];
            foreach ($values as $c => $value) {
                $letter = Coordinate::stringFromColumnIndex($c + 1);
                $sheet->getCell("{$letter}{$row}")->setValue($value);
            }
            $fillColor = self::STATUS_COLORS[$res['status']] ?? null;
            if ($fillColor) {
                $statusLetter = Coordinate::stringFromColumnIndex(10);
                $sheet->getCell("{$statusLetter}{$row}")->getStyle()->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB("FF{$fillColor}");
            }
        }

        foreach (self::WIDTHS as $c => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c + 1))->setWidth($width);
        }

        $ts = Carbon::now()->format('Ymd-His');
        $outPath = preg_replace('/\.[^.]+$/', '', $inputPath)."_results_{$ts}.xlsx";
        (new Xlsx($inputWorkbook))->save($outPath);

        return $outPath;
    }
}
