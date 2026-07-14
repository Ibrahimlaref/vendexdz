<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Exports commission data to Excel.
 * Summary sheet + per-confirmatrice detail sheets.
 */
class CommissionExportService
{
    private CommissionCalculationService $calculator;

    public function __construct()
    {
        $this->calculator = new CommissionCalculationService();
    }

    /**
     * Generate an Excel file with commission data for a store.
     *
     * @return string Absolute path to the generated file
     */
    public function export(int $storeId, string $storeName, Carbon $from, Carbon $to): string
    {
        $confirmatrices = $this->calculator->getConfirmatriceCommission($storeId, $from, $to);
        $gerants = $this->calculator->getGerantCommission($storeId, $from, $to);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        // Summary sheet
        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Summary');
        $this->buildSummarySheet($summarySheet, $confirmatrices, $gerants, $storeName, $from, $to);

        // Per-confirmatrice detail sheets
        foreach ($confirmatrices as $agent) {
            $sheetTitle = mb_substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $agent['agent_name']), 0, 28);
            $detailSheet = $spreadsheet->createSheet();
            $detailSheet->setTitle($sheetTitle);

            $lines = $this->calculator->getConfirmatriceDetailLines($agent['agent_id'], $storeId, $from, $to);
            $this->buildAgentDetailSheet($detailSheet, $agent, $lines);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'commission_' . str_replace(' ', '_', $storeName) . '_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.xlsx';
        $path = storage_path('app/exports/' . $filename);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    private function buildSummarySheet($sheet, $confirmatrices, $gerants, string $storeName, Carbon $from, Carbon $to): void
    {
        $sheet->setCellValue('A1', "PAYMENT INVOICE REPORT — {$storeName}");
        $sheet->setCellValue('A2', "Calculation Period: {$from->format('d/m/Y')} to {$to->format('d/m/Y')}");
        $sheet->setCellValue('A3', "Generated Date: " . now()->format('d/m/Y H:i'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A3')->getFont()->setItalic(true);

        $row = 5;
        $headers = [
            'Invoice No',
            'Recipient Type',
            'Full Name',
            'Email Address',
            'Phone Number',
            'Delivered Orders',
            'Calculated Period',
            'Invoice Date',
            'Amount Due (DA)',
            'Payment Status'
        ];

        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col) . $row;
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5'); // Indigo Header
            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF'); // White Text
        }

        $grandTotal = 0;
        $invoiceIndex = 1001;

        // Add Confirmatrices
        foreach ($confirmatrices as $agent) {
            $row++;
            $sheet->setCellValue("A{$row}", "INV-CONF-{$invoiceIndex}");
            $sheet->setCellValue("B{$row}", "Confirmatrice");
            $sheet->setCellValue("C{$row}", $agent['agent_name']);
            $sheet->setCellValue("D{$row}", $agent['agent_email'] ?? 'N/A');
            $sheet->setCellValue("E{$row}", $agent['agent_phone'] ?? 'N/A');
            $sheet->setCellValue("F{$row}", $agent['delivered_count']);
            $sheet->setCellValue("G{$row}", "{$from->format('d/m/Y')} - {$to->format('d/m/Y')}");
            $sheet->setCellValue("H{$row}", now()->format('d/m/Y'));
            $sheet->setCellValue("I{$row}", $agent['total_commission']);
            $sheet->setCellValue("J{$row}", "Delivered - Ready for Payout");

            $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $grandTotal += $agent['total_commission'];
            $invoiceIndex++;
        }

        // Add Gérants
        foreach ($gerants as $gerant) {
            $row++;
            $sheet->setCellValue("A{$row}", "INV-GERN-{$invoiceIndex}");
            $sheet->setCellValue("B{$row}", "Gérant / Manager");
            $sheet->setCellValue("C{$row}", $gerant['gerant_name']);
            $sheet->setCellValue("D{$row}", $gerant['gerant_email'] ?? 'N/A');
            $sheet->setCellValue("E{$row}", $gerant['gerant_phone'] ?? 'N/A');
            $sheet->setCellValue("F{$row}", $gerant['delivered_count']);
            $sheet->setCellValue("G{$row}", "{$from->format('d/m/Y')} - {$to->format('d/m/Y')}");
            $sheet->setCellValue("H{$row}", now()->format('d/m/Y'));
            $sheet->setCellValue("I{$row}", $gerant['total_commission']);
            $sheet->setCellValue("J{$row}", "Delivered - Ready for Payout");

            $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $grandTotal += $gerant['total_commission'];
            $invoiceIndex++;
        }

        // Grand Total row
        $row += 2;
        $sheet->setCellValue("H{$row}", "GRAND TOTAL:");
        $sheet->setCellValue("I{$row}", $grandTotal);
        $sheet->getStyle("H{$row}:I{$row}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        // Autocomplete widths
        foreach (range(0, 9) as $col) {
            $colLetter = chr(65 + $col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
    }

    private function buildAgentDetailSheet($sheet, array $agentResult, $lines): void
    {
        $sheet->setCellValue('A1', 'Agent: ' . $agentResult['agent_name']);
        $sheet->setCellValue('A2', 'Commission: ' . number_format($agentResult['total_commission'], 2) . ' DA');
        $sheet->setCellValue('A3', 'Delivered Orders: ' . $agentResult['delivered_count']);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getFont()->setBold(true);

        // Product breakdown
        $row = 5;
        $sheet->setCellValue("A{$row}", 'Product Breakdown');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $bHeaders = ['Product', 'Orders', 'Total Qty', 'Rate (DA)', 'Subtotal (DA)'];
        foreach ($bHeaders as $col => $header) {
            $cell = chr(65 + $col) . $row;
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        }

        foreach ($agentResult['breakdown'] as $b) {
            $row++;
            $sheet->setCellValue("A{$row}", $b['product_name']);
            $sheet->setCellValue("B{$row}", $b['order_count']);
            $sheet->setCellValue("C{$row}", $b['total_qty']);
            $sheet->setCellValue("D{$row}", $b['rate']);
            $sheet->setCellValue("E{$row}", $b['subtotal']);
            $sheet->getStyle("D{$row}:E{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // Line-by-line detail
        $row += 3;
        $sheet->setCellValue("A{$row}", 'Delivered Order Lines');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $lHeaders = ['Delivered Date', 'Confirmed Date', 'Order #', 'Product', 'Qty', 'Rate (DA)', 'Line Total (DA)'];
        foreach ($lHeaders as $col => $header) {
            $cell = chr(65 + $col) . $row;
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
        }

        foreach ($lines as $line) {
            $row++;
            $sheet->setCellValue("A{$row}", Carbon::parse($line->delivered_at)->format('d/m/Y H:i'));
            $sheet->setCellValue("B{$row}", Carbon::parse($line->confirmed_at)->format('d/m/Y H:i'));
            $sheet->setCellValue("C{$row}", $line->order_id);
            $sheet->setCellValue("D{$row}", $line->product_name);
            $sheet->setCellValue("E{$row}", $line->quantity);
            $sheet->setCellValue("F{$row}", $line->rate_at_confirmation ?? 0);
            $sheet->setCellValue("G{$row}", $line->line_total);
            $sheet->getStyle("F{$row}:G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
