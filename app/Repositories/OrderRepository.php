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

    public function index($request = null)
    {
        // Handle null request
        $perPage = $request ? $request->integer('per_page', 5) : 5;
        $search  = $request ? $request->get('search') : null;

        $query = Order::query()
            ->select([
                'id',
                'order_id',
                'customer_name',
                'customer_phone',
                'total',
                'payable_amount',
                'paid_amount',
                'created_at',
                'updated_at'
            ]);

        // Apply search filter if search term exists
        if ($search) {
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

        info($orders);

        // Append search params to pagination links (only if request exists)
        if ($request) {
            $orders->appends($request->only(['search', 'per_page']));
        }

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data' => [
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
            ]
        ]);
    }
}
