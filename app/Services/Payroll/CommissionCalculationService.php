<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calculates commission for confirmatrices and gérants.
 *
 * Commission is earned ONLY on delivery — not on confirmation.
 * An order must have a delivery log (order_logs.step=2, statu_new=12)
 * within the date range for it to count.
 *
 * Confirmatrice formula: SUM(quantity × rate_at_confirmation)
 * Gérant formula:        SUM(quantity × gerant_rate_at_confirmation)
 *   summed across the gérant's confirmatrices (via manager_agents)
 *
 * No claw-back: once delivered, the commission is permanent.
 * A later return does NOT reverse commission (delivery log already exists).
 *
 * Date range filter: by delivery date (order_logs.created_at where step=2, statu_new=12).
 */
class CommissionCalculationService
{
    /** ssid for "Delivered" in step 2 */
    private const DELIVERED_STATUS = 12;

    /** step=1, statu_new=2 = agent confirmed the order */
    private const CONFIRMED_STATUS = 2;

    /**
     * Get per-confirmatrice commission for a store in a date range.
     *
     * @return Collection of ['agent_id', 'agent_name', 'delivered_count', 'total_commission', 'breakdown']
     */
    public function getConfirmatriceCommission(int $storeId, Carbon $from, Carbon $to): Collection
    {
        // Core query: order items where the order was delivered in the date range
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.oid', '=', 'oi.oid')
            // Join delivery log — the order MUST have been delivered in the range
            ->join('order_logs as ol_deliver', function ($join) use ($from, $to) {
                $join->on('ol_deliver.oid', '=', 'o.oid')
                    ->where('ol_deliver.step', 2)
                    ->where('ol_deliver.statu_new', self::DELIVERED_STATUS)
                    ->whereDate('ol_deliver.created_at', '>=', $from)
                    ->whereDate('ol_deliver.created_at', '<=', $to);
            })
            // Join confirmation log — to get the confirming agent
            ->join('order_logs as ol_confirm', function ($join) {
                $join->on('ol_confirm.oid', '=', 'o.oid')
                    ->where('ol_confirm.step', 1)
                    ->where('ol_confirm.statu_new', self::CONFIRMED_STATUS);
            })
            ->join('users as u', 'u.id', '=', 'ol_confirm.aid')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->where('o.sid', $storeId)
            ->select([
                'ol_confirm.aid as agent_id',
                'u.name as agent_name',
                'u.email as agent_email',
                'u.phone as agent_phone',
                'o.oid',
                'p.id as product_id',
                'p.name as product_name',
                'oi.quantity',
                'oi.rate_at_confirmation',
            ])
            ->get();

        // Group by agent
        return $rows->groupBy('agent_id')->map(function ($agentRows) {
            $first = $agentRows->first();
            $breakdown = [];

            foreach ($agentRows as $row) {
                $key = $row->product_id;
                if (!isset($breakdown[$key])) {
                    $breakdown[$key] = [
                        'product_id' => $row->product_id,
                        'product_name' => $row->product_name,
                        'total_qty' => 0,
                        'rate' => $row->rate_at_confirmation ?? 0,
                        'subtotal' => 0,
                        'order_count' => 0,
                    ];
                }
                $subtotal = $row->quantity * ($row->rate_at_confirmation ?? 0);
                $breakdown[$key]['total_qty'] += $row->quantity;
                $breakdown[$key]['subtotal'] += $subtotal;
                $breakdown[$key]['order_count']++;
            }

            $total = array_sum(array_column($breakdown, 'subtotal'));

            return [
                'agent_id' => $first->agent_id,
                'agent_name' => $first->agent_name,
                'agent_email' => $first->agent_email,
                'agent_phone' => $first->agent_phone,
                'delivered_count' => $agentRows->unique('oid')->count(),
                'total_commission' => round($total, 2),
                'breakdown' => array_values($breakdown),
            ];
        })->sortByDesc('total_commission')->values();
    }

    /**
     * Get per-gérant commission for a store in a date range.
     *
     * Sums gerant_rate_at_confirmation across delivered orders confirmed
     * by the gérant's own confirmatrices (via manager_agents).
     * Each gérant gets the full per-unit rate independently (no split).
     *
     * @return Collection of ['gerant_id', 'gerant_name', 'delivered_count', 'total_commission', 'agent_count']
     */
    public function getGerantCommission(int $storeId, Carbon $from, Carbon $to): Collection
    {
        // Get managers for this store
        $gerantIds = DB::table('store_managers')
            ->where('sid', $storeId)
            ->pluck('mid');

        if ($gerantIds->isEmpty()) {
            // Fallback: try via UserStore + role=3
            $gerantIds = DB::table('user_stores as us')
                ->join('user_roles as ur', 'ur.uid', '=', 'us.user_id')
                ->join('roles as r', 'r.rid', '=', 'ur.rid')
                ->where('us.store_id', $storeId)
                ->where('r.rid', 3)
                ->pluck('us.user_id');
        }

        $results = collect();

        foreach ($gerantIds as $gerantId) {
            // Get this gérant's confirmatrices via manager_agents
            $agentIds = DB::table('manager_agents')
                ->where('mid', $gerantId)
                ->pluck('aid');

            if ($agentIds->isEmpty()) {
                // Fallback: all agents in the store
                $agentIds = DB::table('store_agents')
                    ->where('sid', $storeId)
                    ->pluck('aid');
            }

            // Sum commission across these agents' delivered orders
            $rows = DB::table('order_items as oi')
                ->join('orders as o', 'o.oid', '=', 'oi.oid')
                ->join('order_logs as ol_deliver', function ($join) use ($from, $to) {
                    $join->on('ol_deliver.oid', '=', 'o.oid')
                        ->where('ol_deliver.step', 2)
                        ->where('ol_deliver.statu_new', self::DELIVERED_STATUS)
                        ->whereDate('ol_deliver.created_at', '>=', $from)
                        ->whereDate('ol_deliver.created_at', '<=', $to);
                })
                ->join('order_logs as ol_confirm', function ($join) use ($agentIds) {
                    $join->on('ol_confirm.oid', '=', 'o.oid')
                        ->where('ol_confirm.step', 1)
                        ->where('ol_confirm.statu_new', self::CONFIRMED_STATUS)
                        ->whereIn('ol_confirm.aid', $agentIds);
                })
                ->where('o.sid', $storeId)
                ->select([
                    'o.oid',
                    'oi.quantity',
                    'oi.gerant_rate_at_confirmation',
                ])
                ->get();

            $totalCommission = $rows->sum(function ($row) {
                return $row->quantity * ($row->gerant_rate_at_confirmation ?? 0);
            });

            $gerant = DB::table('users')->where('id', $gerantId)->first();

            $results->push([
                'gerant_id' => $gerantId,
                'gerant_name' => $gerant?->name ?? 'Unknown',
                'gerant_email' => $gerant?->email ?? '',
                'gerant_phone' => $gerant?->phone ?? '',
                'delivered_count' => $rows->unique('oid')->count(),
                'total_commission' => round($totalCommission, 2),
                'agent_count' => $agentIds->count(),
            ]);
        }

        return $results->sortByDesc('total_commission')->values();
    }

    /**
     * Get a single confirmatrice's delivered line items (for export/detail view).
     */
    public function getConfirmatriceDetailLines(int $agentId, int $storeId, Carbon $from, Carbon $to): Collection
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.oid', '=', 'oi.oid')
            ->join('order_logs as ol_deliver', function ($join) use ($from, $to) {
                $join->on('ol_deliver.oid', '=', 'o.oid')
                    ->where('ol_deliver.step', 2)
                    ->where('ol_deliver.statu_new', self::DELIVERED_STATUS)
                    ->whereDate('ol_deliver.created_at', '>=', $from)
                    ->whereDate('ol_deliver.created_at', '<=', $to);
            })
            ->join('order_logs as ol_confirm', function ($join) use ($agentId) {
                $join->on('ol_confirm.oid', '=', 'o.oid')
                    ->where('ol_confirm.step', 1)
                    ->where('ol_confirm.statu_new', self::CONFIRMED_STATUS)
                    ->where('ol_confirm.aid', $agentId);
            })
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->where('o.sid', $storeId)
            ->select([
                'o.id as order_id',
                'o.oid',
                'ol_deliver.created_at as delivered_at',
                'ol_confirm.created_at as confirmed_at',
                'p.name as product_name',
                'oi.quantity',
                'oi.rate_at_confirmation',
                DB::raw('oi.quantity * COALESCE(oi.rate_at_confirmation, 0) as line_total'),
            ])
            ->orderBy('ol_deliver.created_at')
            ->get();
    }
}
