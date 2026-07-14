<?php

namespace App\Services\Statistics;

use Illuminate\Support\Facades\DB;

/**
 * Calculates delivery rate: delivered ÷ (delivered + returned).
 * Excludes in-transit orders from the denominator.
 *
 * Status mapping (from order_logs, step=2):
 *   ssid=12 → Delivered
 *   ssid=17 → Returned
 *
 * The denominator is swappable: change DENOMINATOR_STATUSES to
 * include in-transit if the client wants shipped-inclusive instead.
 */
class DeliveryRateService
{
    /** ssid values that count as "delivered" */
    private const DELIVERED_STATUSES = [12];

    /** ssid values that count as "returned" */
    private const RETURNED_STATUSES = [17];

    /**
     * Calculate delivery rate with filters.
     *
     * @param array $filters Keys: date_from, date_to, store_id (int|array|null),
     *                       product_id (int|null), category_id (int|null)
     * @return array ['summary' => [...], 'by_product' => [...], 'by_category' => [...]]
     */
    public function getRate(array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $storeIds = $filters['store_id'] ?? null;
        $productId = $filters['product_id'] ?? null;
        $categoryId = $filters['category_id'] ?? null;

        if (is_int($storeIds)) {
            $storeIds = [$storeIds];
        }

        // Base query: find distinct order OIDs with their final delivery status
        $baseQuery = DB::table('order_logs as ol')
            ->join('orders as o', 'o.oid', '=', 'ol.oid')
            ->where('ol.step', 2)
            ->whereIn('ol.statu_new', array_merge(self::DELIVERED_STATUSES, self::RETURNED_STATUSES))
            ->when($storeIds, fn($q) => $q->whereIn('o.sid', $storeIds))
            ->when($dateFrom, fn($q) => $q->whereDate('ol.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('ol.created_at', '<=', $dateTo));

        // If product or category filter, join order_items and products
        if ($productId || $categoryId) {
            $baseQuery->join('order_items as oi', 'oi.oid', '=', 'o.oid')
                ->join('products as p', 'p.id', '=', 'oi.product_id')
                ->when($productId, fn($q) => $q->where('oi.product_id', $productId))
                ->when($categoryId, fn($q) => $q->where('p.category_id', $categoryId));
        }

        // Overall summary
        $summary = (clone $baseQuery)->selectRaw('
            COUNT(DISTINCT CASE WHEN ol.statu_new IN (' . implode(',', self::DELIVERED_STATUSES) . ') THEN o.oid END) as delivered,
            COUNT(DISTINCT CASE WHEN ol.statu_new IN (' . implode(',', self::RETURNED_STATUSES) . ') THEN o.oid END) as returned
        ')->first();

        $summaryResult = $this->computeRate($summary->delivered ?? 0, $summary->returned ?? 0);

        // By product
        $byProduct = DB::table('order_logs as ol')
            ->join('orders as o', 'o.oid', '=', 'ol.oid')
            ->join('order_items as oi', 'oi.oid', '=', 'o.oid')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->where('ol.step', 2)
            ->whereIn('ol.statu_new', array_merge(self::DELIVERED_STATUSES, self::RETURNED_STATUSES))
            ->when($storeIds, fn($q) => $q->whereIn('o.sid', $storeIds))
            ->when($dateFrom, fn($q) => $q->whereDate('ol.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('ol.created_at', '<=', $dateTo))
            ->when($productId, fn($q) => $q->where('oi.product_id', $productId))
            ->when($categoryId, fn($q) => $q->where('p.category_id', $categoryId))
            ->selectRaw('
                p.id as product_id,
                p.name as product_name,
                p.category_id,
                COUNT(DISTINCT CASE WHEN ol.statu_new IN (' . implode(',', self::DELIVERED_STATUSES) . ') THEN o.oid END) as delivered,
                COUNT(DISTINCT CASE WHEN ol.statu_new IN (' . implode(',', self::RETURNED_STATUSES) . ') THEN o.oid END) as returned
            ')
            ->groupBy('p.id', 'p.name', 'p.category_id')
            ->orderByDesc('delivered')
            ->get()
            ->map(function ($row) {
                $row->rate = $this->computeRate($row->delivered, $row->returned)['rate'];
                return $row;
            });

        // By category
        $byCategory = DB::table('order_logs as ol')
            ->join('orders as o', 'o.oid', '=', 'ol.oid')
            ->join('order_items as oi', 'oi.oid', '=', 'o.oid')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->join('categories as c', 'c.cid', '=', 'p.category_id')
            ->where('ol.step', 2)
            ->whereIn('ol.statu_new', array_merge(self::DELIVERED_STATUSES, self::RETURNED_STATUSES))
            ->when($storeIds, fn($q) => $q->whereIn('o.sid', $storeIds))
            ->when($dateFrom, fn($q) => $q->whereDate('ol.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('ol.created_at', '<=', $dateTo))
            ->selectRaw('
                c.cid as category_id,
                c.name as category_name,
                COUNT(DISTINCT CASE WHEN ol.statu_new IN (' . implode(',', self::DELIVERED_STATUSES) . ') THEN o.oid END) as delivered,
                COUNT(DISTINCT CASE WHEN ol.statu_new IN (' . implode(',', self::RETURNED_STATUSES) . ') THEN o.oid END) as returned
            ')
            ->groupBy('c.cid', 'c.name')
            ->orderByDesc('delivered')
            ->get()
            ->map(function ($row) {
                $row->rate = $this->computeRate($row->delivered, $row->returned)['rate'];
                return $row;
            });

        // By store (for multi-store clients)
        $byStore = [];
        if ($storeIds && count($storeIds) > 1) {
            $byStore = DB::table('order_logs as ol')
                ->join('orders as o', 'o.oid', '=', 'ol.oid')
                ->join('stores as s', 's.id', '=', 'o.sid')
                ->where('ol.step', 2)
                ->whereIn('ol.statu_new', array_merge(self::DELIVERED_STATUSES, self::RETURNED_STATUSES))
                ->whereIn('o.sid', $storeIds)
                ->when($dateFrom, fn($q) => $q->whereDate('ol.created_at', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->whereDate('ol.created_at', '<=', $dateTo))
                ->selectRaw('
                    s.id as store_id,
                    s.name as store_name,
                    COUNT(DISTINCT CASE WHEN ol.statu_new IN (' . implode(',', self::DELIVERED_STATUSES) . ') THEN o.oid END) as delivered,
                    COUNT(DISTINCT CASE WHEN ol.statu_new IN (' . implode(',', self::RETURNED_STATUSES) . ') THEN o.oid END) as returned
                ')
                ->groupBy('s.id', 's.name')
                ->get()
                ->map(function ($row) {
                    $row->rate = $this->computeRate($row->delivered, $row->returned)['rate'];
                    return $row;
                });
        }

        return [
            'summary' => $summaryResult,
            'by_product' => $byProduct,
            'by_category' => $byCategory,
            'by_store' => $byStore,
        ];
    }

    /**
     * Compute rate from counts.
     * Formula: delivered ÷ (delivered + returned)
     */
    private function computeRate(int $delivered, int $returned): array
    {
        $total = $delivered + $returned;
        $rate = $total > 0 ? round(($delivered / $total) * 100, 1) : 0;

        return [
            'delivered' => $delivered,
            'returned' => $returned,
            'total' => $total,
            'rate' => $rate,
        ];
    }
}
