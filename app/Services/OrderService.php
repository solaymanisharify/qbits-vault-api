<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(protected OrderRepository $orderRepository) {}

    public function getOrdersFromQbits($request)
    {

        $result = handleHttpRequest('GET', env('QBITS_SERVICE_BASE_URL') . '/get-orders', [
            'token' => env('QBITS_SERVICE_TOKEN'),
        ], []);


        if (!$result['success']) {
            \Log::warning('Failed to fetch orders from Qbits', [
                'status' => $result['status'],
                'response' => $result['data']
            ]);

            return errorResponse(
                message: 'Failed to retrieve orders from Qbits.',
                errors: $result['data'],
                status: $result['status'] === 401 ? 401 : 502
            );
        }

        $data['data'] = $result['data']['data']['orders'];

        info($data['data']);

        $updateData = [];

        foreach ($data['data'] as $order) {
            $updateData[] = [
                'order_id' => $order['order_id'],
                'customer_name' => $order['customer']['name'],
                'customer_phone' => $order['customer']['phone'],
                'total' => $order['payable_amount'],
                'payable_amount' => $order['payable_amount'],
                'paid_amount' => $order['paid_amount'],
                'total_cash_to_deposit' => $order['total_cash_to_deposit'],
            ];
        }


        $this->orderRepository->upsertOrders($updateData);
        $data = $this->orderRepository->index($request);


        return successResponse('Orders fetched successfully', $data, 200);
    }
}
