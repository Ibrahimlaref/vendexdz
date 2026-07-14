<?php

namespace App\Jobs;

use App\Models\GoogleSheetConnection;
use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\GoogleSheets\ColumnMapperService;
use App\Services\GoogleSheets\SheetOrderImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGoogleSheetOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        private int $connectionId
    ) {}

    public function handle(): void
    {
        $connection = GoogleSheetConnection::find($this->connectionId);

        if (!$connection || !$connection->is_active || !$connection->mapping_confirmed) {
            return;
        }

        try {
            $sheetsService = new GoogleSheetsService();
            $mapper = new ColumnMapperService();

            // 1. Read current headers to detect changes
            $currentHeaders = $sheetsService->readHeaders($connection->sheet_id);

            if (empty($currentHeaders)) {
                Log::warning("Google Sheets sync: empty headers for connection {$connection->id}");
                return;
            }

            // 2. Check if headers changed since last mapping
            $mapping = $connection->column_mapping ?? [];
            $mappedHeaders = array_keys($mapping);

            $newHeaders = array_diff($currentHeaders, $mappedHeaders);
            $removedHeaders = array_diff($mappedHeaders, $currentHeaders);

            if (!empty($removedHeaders)) {
                // A mapped header was renamed or removed — flag for re-confirmation
                Log::warning("Google Sheets sync: headers changed for connection {$connection->id}. Removed: " . implode(', ', $removedHeaders));
                $connection->update(['mapping_confirmed' => false]);
                return;
            }

            // Re-resolve column positions based on current headers (handles reordering)
            // The mapping is keyed by header text, not position — so reordering is handled automatically.

            // 3. Read new rows (start from last synced row + 1)
            $startRow = max(2, ($connection->last_synced_row ?? 1) + 1);
            $result = $sheetsService->readRows($connection->sheet_id, $startRow);

            if (empty($result['rows'])) {
                $connection->update(['last_synced_at' => now()]);
                return;
            }

            // 4. Import rows
            $importer = new SheetOrderImportService($connection);
            $stats = $importer->importRows($result['rows']);

            // 5. Update connection state
            $connection->update([
                'last_synced_at' => now(),
                'last_synced_row' => $result['last_row'],
            ]);

            Log::info("Google Sheets sync completed for connection {$connection->id}: " . json_encode($stats));

        } catch (\Exception $e) {
            Log::error("Google Sheets sync failed for connection {$connection->id}: " . $e->getMessage());

            // Don't re-throw — let the job fail gracefully without retrying bad configs
            if (str_contains($e->getMessage(), 'credentials') || str_contains($e->getMessage(), '403')) {
                $this->fail($e);
            }
        }
    }
}
