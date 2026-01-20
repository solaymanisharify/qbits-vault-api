<?php

namespace App\Repositories;

use App\Models\Order;

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
        $excludeOrderIds = $request->input('exclude_order_ids');


        $query = Order::query();

        if ($excludeOrderIds !== null && is_array($excludeOrderIds) && count($excludeOrderIds) > 0) {
            $query->whereNotIn('order_id', $excludeOrderIds);
        }

        // Apply search filter if search term exists
        if ($search !== null && trim($search) !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        // Order by latest
        $query->latest('created_at');

        // Paginate results
        $orders = $query->paginate($perPage);
        // Append search params to pagination links (only if request exists)
        $orders->appends([
            'search'   => $search,
            'per_page' => $perPage,
            'exclude_order_ids' => $excludeOrderIds,
        ]);

        $new = [
            'orders' => $orders->items(),
            'pagination' => [
                'current_page'   => $orders->currentPage(),
                'per_page'       => $orders->perPage(),
                'total'          => $orders->total(),
                'last_page'      => $orders->lastPage(),
                'from'           => $orders->firstItem(),
                'to'             => $orders->lastItem(),
                'next_page_url'  => $orders->nextPageUrl(),
                'prev_page_url'  => $orders->previousPageUrl(),
                'links'          => $orders->linkCollection()->toArray()
            ]
        ];
        return $new;
    }
}
