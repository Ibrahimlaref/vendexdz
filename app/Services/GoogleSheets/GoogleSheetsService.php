<?php

namespace App\Services\GoogleSheets;

use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Log;

/**
 * Handles Google Sheets API authentication and data reading.
 * Uses a service account — merchants share their sheet with the
 * service-account email (configured in .env).
 */
class GoogleSheetsService
{
    private ?Sheets $sheetsService = null;

    /**
     * Get an authenticated Google Sheets service instance.
     * Built fresh per call — safe under Octane (no singleton state).
     */
    private function getSheetsService(): Sheets
    {
        $client = new Client();
        $client->setApplicationName('Vendex Sheet Import');
        $client->setScopes([Sheets::SPREADSHEETS_READONLY]);

        $credentialsPath = config('services.google_sheets.service_account_credentials');

        if (!$credentialsPath || !file_exists($credentialsPath)) {
            throw new \RuntimeException(
                'Google service account credentials file not found. '
                . 'Set GOOGLE_SERVICE_ACCOUNT_CREDENTIALS in .env to the absolute path of the JSON key file.'
            );
        }

        $client->setAuthConfig($credentialsPath);

        return new Sheets($client);
    }

    /**
     * Read the header row (row 1) from the first sheet.
     *
     * @return string[] Array of header strings
     */
    public function readHeaders(string $sheetId): array
    {
        $service = $this->getSheetsService();

        $response = $service->spreadsheets_values->get($sheetId, 'A1:ZZ1');
        $rows = $response->getValues();

        if (empty($rows) || empty($rows[0])) {
            return [];
        }

        return array_map('strval', $rows[0]);
    }

    /**
     * Read data rows from the sheet, starting after the header row.
     *
     * @param int $startRow  First data row to read (2 = right after header)
     * @param int $maxRows   Maximum rows to fetch per call
     * @return array  ['headers' => [...], 'rows' => [[col_index => value, ...], ...], 'last_row' => int]
     */
    public function readRows(string $sheetId, int $startRow = 2, int $maxRows = 500): array
    {
        $service = $this->getSheetsService();

        // First, get headers to know column count
        $headers = $this->readHeaders($sheetId);
        if (empty($headers)) {
            return ['headers' => [], 'rows' => [], 'last_row' => $startRow];
        }

        $lastCol = $this->columnLetter(count($headers));
        $range = "A{$startRow}:{$lastCol}" . ($startRow + $maxRows - 1);

        $response = $service->spreadsheets_values->get($sheetId, $range);
        $rawRows = $response->getValues() ?? [];

        $rows = [];
        foreach ($rawRows as $index => $row) {
            // Skip completely empty rows
            $filtered = array_filter($row, fn($cell) => trim((string) $cell) !== '');
            if (empty($filtered)) {
                continue;
            }

            // Pad row to match header count
            $padded = array_pad($row, count($headers), '');
            $rows[] = [
                'row_number' => $startRow + $index,
                'data' => array_combine($headers, array_slice($padded, 0, count($headers))),
            ];
        }

        $lastRow = empty($rows) ? $startRow : end($rows)['row_number'];

        return [
            'headers' => $headers,
            'rows' => $rows,
            'last_row' => $lastRow,
        ];
    }

    /**
     * Test connectivity — reads sheet title and row count.
     */
    public function testConnection(string $sheetId): array
    {
        try {
            $service = $this->getSheetsService();
            $spreadsheet = $service->spreadsheets->get($sheetId);
            $title = $spreadsheet->getProperties()->getTitle();
            $headers = $this->readHeaders($sheetId);

            return [
                'success' => true,
                'title' => $title,
                'headers' => $headers,
                'header_count' => count($headers),
            ];
        } catch (\Exception $e) {
            Log::warning("Google Sheets test connection failed for sheet {$sheetId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert a 1-based column number to a letter (1=A, 26=Z, 27=AA, etc.)
     */
    private function columnLetter(int $colNumber): string
    {
        $letter = '';
        while ($colNumber > 0) {
            $colNumber--;
            $letter = chr(65 + ($colNumber % 26)) . $letter;
            $colNumber = intdiv($colNumber, 26);
        }
        return $letter;
    }
}
