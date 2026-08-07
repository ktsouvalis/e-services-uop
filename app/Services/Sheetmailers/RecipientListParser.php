<?php

namespace App\Services\Sheetmailers;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecipientListParser
{
    /**
     * Split a worksheet's rows (email in column A, extra data in column B) into
     * eligible recipients and invalid entries.
     *
     * @return array{eligible: array<int, array{email: string, additionalData: mixed}>, invalid: array<int, mixed>}
     */
    public static function fromWorksheet(Worksheet $sheet): array
    {
        $eligible = [];
        $invalid = [];

        foreach ($sheet->getRowIterator() as $row) {
            $email = $sheet->getCell('A' . $row->getRowIndex())->getValue();
            $additionalData = $sheet->getCell('B' . $row->getRowIndex())->getValue();

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $eligible[] = ['email' => $email, 'additionalData' => $additionalData];
            } else {
                $invalid[] = $email;
            }
        }

        return ['eligible' => $eligible, 'invalid' => $invalid];
    }

    /**
     * Split a comma separated string of addresses into eligible recipients and invalid entries.
     *
     * @return array{eligible: array<int, array{email: string, additionalData: mixed}>, invalid: array<int, mixed>}
     */
    public static function fromCommaList(string $commaList): array
    {
        $eligible = [];
        $invalid = [];

        foreach (explode(',', $commaList) as $email) {
            $email = trim($email);

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $eligible[] = ['email' => $email, 'additionalData' => ''];
            } else {
                $invalid[] = $email;
            }
        }

        return ['eligible' => $eligible, 'invalid' => $invalid];
    }
}
