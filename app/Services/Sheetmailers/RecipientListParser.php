<?php

namespace App\Services\Sheetmailers;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class RecipientListParser
{
    /**
     * Split a worksheet's rows into eligible recipients and invalid entries.
     * Row 1 must be column headers: one column named "email" (case-insensitive)
     * holds the recipient address, every other named column becomes a mail-merge
     * placeholder - a "place1" header lets the subject/body use {{place1}} and
     * get that row's value substituted in per recipient.
     *
     * @return array{eligible: array<int, array{email: string, placeholders: array<string, string>}>, invalid: array<int, mixed>}
     *
     * @throws RuntimeException if row 1 has no column named "email"
     */
    public static function fromWorksheet(Worksheet $sheet): array
    {
        $headers = self::readHeaders($sheet);
        $emailColumn = array_search('email', array_map('strtolower', $headers), true);

        if ($emailColumn === false) {
            throw new RuntimeException('The first row must contain column headers, including one named "email".');
        }

        $eligible = [];
        $invalid = [];

        foreach ($sheet->getRowIterator(2) as $row) {
            $rowIndex = $row->getRowIndex();
            $email = self::cellValue($sheet, $emailColumn, $rowIndex);

            // A fully blank row (common trailing artifact in exported xlsx files) isn't
            // a real invalid entry - skip it instead of listing an empty string.
            if ($email === null || $email === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;
                continue;
            }

            $placeholders = [];
            foreach ($headers as $column => $header) {
                if ($column === $emailColumn) {
                    continue;
                }
                // Placeholder values get substituted directly into the mail body's raw
                // HTML (see PlaceholderReplacer) without further escaping - strip any tags
                // here, at the point they're read from the (untrusted) uploaded file, so a
                // cell containing markup can't inject HTML into the sent email.
                $placeholders[$header] = strip_tags((string) self::cellValue($sheet, $column, $rowIndex));
            }

            $eligible[] = ['email' => $email, 'placeholders' => $placeholders];
        }

        return ['eligible' => $eligible, 'invalid' => $invalid];
    }

    /**
     * Split a comma separated string of addresses into eligible recipients and invalid entries.
     * There's no spreadsheet here, so there are no placeholder columns to merge in.
     *
     * @return array{eligible: array<int, array{email: string, placeholders: array<string, string>}>, invalid: array<int, mixed>}
     */
    public static function fromCommaList(string $commaList): array
    {
        $eligible = [];
        $invalid = [];

        foreach (explode(',', $commaList) as $email) {
            $email = trim($email);

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $eligible[] = ['email' => $email, 'placeholders' => []];
            } else {
                $invalid[] = $email;
            }
        }

        return ['eligible' => $eligible, 'invalid' => $invalid];
    }

    /**
     * @return array<string, string> column letter => trimmed header text, blank headers dropped
     */
    private static function readHeaders(Worksheet $sheet): array
    {
        $headers = [];

        foreach (Coordinate::extractAllCellReferencesInRange('A1:' . $sheet->getHighestColumn(1) . '1') as $cellRef) {
            [$column] = Coordinate::coordinateFromString($cellRef);
            $value = trim((string) $sheet->getCell($cellRef)->getValue());

            if ($value !== '') {
                $headers[$column] = $value;
            }
        }

        return $headers;
    }

    private static function cellValue(Worksheet $sheet, string $column, int $row): mixed
    {
        return $sheet->getCell($column . $row)->getValue();
    }
}
