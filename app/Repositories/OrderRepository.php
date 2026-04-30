<?php

namespace App\Repositories;

use App\Models\CashIn;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderRepository
{

    public function upsertOrders(array $data)
    {
        // Laravel upsert expects an array of records
        $records = is_assoc($data) ? [$data] : $data;

        if (empty($records)) {
            return;
        }
        // Columns to update on conflict (exclude the unique keys if not specified)
        $updateColumns = $update ?? array_diff(array_keys($records[0]), ['order_id']);

        Order::upsert(
            $data,
            ['order_id'],
            $updateColumns
        );
    }

    public function index($request)
    {
        $perPage = $request->integer('per_page', 10);
        $search  = $request->input('search');

        // Get order_ids that already exist in cashIns table
        $excludeOrderIds = DB::table('cash_ins')
            ->whereNotNull('orders')
            ->pluck('orders')
            ->map(fn($item) => collect(json_decode($item, true))->pluck('order_id'))
            ->flatten()
            ->filter()
            ->unique()
            ->toArray();

        $query = Order::query();

        // Exclude orders that already have a cash-in
        if (!empty($excludeOrderIds)) {
            $query->whereNotIn('order_id', $excludeOrderIds);
        }

        // Apply search filter
        if (!empty(trim($search ?? ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest('created_at')->paginate($perPage);

        $orders->appends([
            'search'   => $search,
            'per_page' => $perPage,
        ]);

        return [
            'orders' => $orders->items(),
            'pagination' => [
                'current_page'  => $orders->currentPage(),
                'per_page'      => $orders->perPage(),
                'total'         => $orders->total(),
                'last_page'     => $orders->lastPage(),
                'from'          => $orders->firstItem(),
                'to'            => $orders->lastItem(),
                'next_page_url' => $orders->nextPageUrl(),
                'prev_page_url' => $orders->previousPageUrl(),
                'links'         => $orders->linkCollection()->toArray(),
            ],
        ];
    }
}
