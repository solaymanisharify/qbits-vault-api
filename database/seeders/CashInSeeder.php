<?php

namespace Database\Seeders;

use App\Models\CashIn;
use App\Models\CashInRequiredApprover;
use App\Models\CashInRequiredVerifier;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultBag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CashInSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::where('email', 'super@admin.com')->firstOrFail();

        $vault = Vault::first() ?? Vault::create([
            'vault_code' => 100001,
            'name'       => 'Main Vault',
            'address'    => 'Dhaka, Bangladesh',
            'bag_limit'  => 50,
            'balance'    => 0,
        ]);

        $bag = VaultBag::where('vault_id', $vault->id)->first() ?? VaultBag::create([
            'vault_id'               => $vault->id,
            'barcode'                => 'BAG-' . strtoupper(Str::random(6)),
            'bag_identifier_barcode' => 'BID-' . strtoupper(Str::random(6)),
            'rack_number'            => 'A1',
            'current_amount'         => 0,
            'is_sealed'              => false,
            'is_active'              => true,
        ]);

        $records = [
            [
                'label'           => 'Pending (single order)',
                'verifier_status' => 'pending',
                'approver_status' => 'pending',
                'verifier_done'   => false,
                'approver_done'   => false,
                'order_count'     => 1,
            ],
            [
                'label'           => 'Pending (multiple orders)',
                'verifier_status' => 'pending',
                'approver_status' => 'pending',
                'verifier_done'   => false,
                'approver_done'   => false,
                'order_count'     => 4,
            ],
            [
                'label'           => 'Verified only',
                'verifier_status' => 'verified',
                'approver_status' => 'pending',
                'verifier_done'   => true,
                'approver_done'   => false,
                'order_count'     => 2,
            ],
            [
                'label'           => 'Approved',
                'verifier_status' => 'verified',
                'approver_status' => 'approved',
                'verifier_done'   => true,
                'approver_done'   => true,
                'order_count'     => 3,
            ],
        ];

        foreach ($records as $record) {
            $orderCount = $record['order_count'];
            $perOrder   = fake()->numberBetween(5000, 30000);
            $amount     = $perOrder * $orderCount;

            $orders = [];
            for ($j = 0; $j < $orderCount; $j++) {
                $orders[] = ['order_id' => 'ORD-' . strtoupper(Str::random(8)), 'amount' => $perOrder];
            }

            $cashIn = CashIn::create([
                'user_id'         => $superAdmin->id,
                'tran_id'         => strtoupper(Str::ulid()),
                'vault_id'        => $vault->id,
                'bag_id'          => $bag->id,
                'cash_in_amount'  => $amount,
                'verifier_status' => $record['verifier_status'],
                'approver_status' => $record['approver_status'],
                'completed_at'    => $record['approver_done'] ? now() : null,
                'orders'          => $orders,
                'denominations'   => [
                    ['note' => 1000, 'quantity' => intdiv($amount, 1000)],
                    ['note' => 500,  'quantity' => intdiv($amount % 1000, 500)],
                ],
            ]);

            CashInRequiredVerifier::create([
                'cash_in_id'  => $cashIn->id,
                'user_id'     => $superAdmin->id,
                'verified'    => $record['verifier_done'],
                'verified_at' => $record['verifier_done'] ? now()->subMinutes(30) : null,
            ]);

            CashInRequiredApprover::create([
                'cash_in_id'  => $cashIn->id,
                'user_id'     => $superAdmin->id,
                'approved'    => $record['approver_done'],
                'approved_at' => $record['approver_done'] ? now()->subMinutes(10) : null,
            ]);

            $this->command->info("Cash-In [{$record['label']}] created → tran_id: {$cashIn->tran_id}");
        }
    }
}
