<?php

namespace Database\Seeders;

use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\CashOutBag;
use App\Models\CashoutRequiredApprover;
use App\Models\CashoutRequiredVerifier;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultBag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CashOutSeeder extends Seeder
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
            'current_amount'         => 50000,
            'is_sealed'              => false,
            'is_active'              => true,
        ]);

        // Use existing approved cash-ins or create a dummy one to link cash-outs
        $approvedCashIn = CashIn::where('approver_status', 'approved')->first() ?? CashIn::create([
            'user_id'         => $superAdmin->id,
            'tran_id'         => strtoupper(Str::ulid()),
            'vault_id'        => $vault->id,
            'bag_id'          => $bag->id,
            'cash_in_amount'  => 200000,
            'verifier_status' => 'verified',
            'approver_status' => 'approved',
            'completed_at'    => now()->subHours(2),
            'orders'          => [
                ['order_id' => 'ORD-' . strtoupper(Str::random(8)), 'amount' => 200000],
            ],
            'denominations'   => [
                ['note' => 1000, 'quantity' => 200],
            ],
        ]);

        $records = [
            [
                'label'           => 'Pending',
                'verifier_status' => 'pending',
                'approver_status' => 'pending',
                'verifier_done'   => false,
                'approver_done'   => false,
                'request_amount'  => 10000,
                'cash_out_amount' => 10000,
            ],
            [
                'label'           => 'Verified only',
                'verifier_status' => 'verified',
                'approver_status' => 'pending',
                'verifier_done'   => true,
                'approver_done'   => false,
                'request_amount'  => 25000,
                'cash_out_amount' => 25000,
            ],
            [
                'label'           => 'Approved',
                'verifier_status' => 'verified',
                'approver_status' => 'approved',
                'verifier_done'   => true,
                'approver_done'   => true,
                'request_amount'  => 39688,
                'cash_out_amount' => 39688,
            ],
        ];

        foreach ($records as $record) {
            $cashOut = CashOut::create([
                'user_id'         => $superAdmin->id,
                'cash_in_id'      => $approvedCashIn->id,
                'vault_id'        => $vault->id,
                'tran_id'         => strtoupper(Str::ulid()),
                'cash_out_amount' => $record['cash_out_amount'],
                'request_amount'  => $record['request_amount'],
                'verifier_status' => $record['verifier_status'],
                'approver_status' => $record['approver_status'],
                'completed_at'    => $record['approver_done'] ? now() : null,
                'note'            => 'Seeded cash-out: ' . $record['label'],
            ]);

            CashOutBag::create([
                'cash_out_id'     => $cashOut->id,
                'bags_id'         => $bag->id,
                'verifier_status' => $record['verifier_done'] ? 'verified' : 'pending',
                'status'          => $record['approver_done'] ? 'completed' : 'pending',
                'note'            => null,
            ]);

            CashoutRequiredVerifier::create([
                'cash_out_id' => $cashOut->id,
                'user_id'     => $superAdmin->id,
                'verified'    => $record['verifier_done'],
                'verified_at' => $record['verifier_done'] ? now()->subMinutes(30) : null,
            ]);

            CashoutRequiredApprover::create([
                'cash_out_id' => $cashOut->id,
                'user_id'     => $superAdmin->id,
                'approved'    => $record['approver_done'],
                'approved_at' => $record['approver_done'] ? now()->subMinutes(10) : null,
            ]);

            $this->command->info("Cash-Out [{$record['label']}] created → tran_id: {$cashOut->tran_id}");
        }
    }
}
