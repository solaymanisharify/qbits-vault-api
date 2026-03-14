<?php

namespace App\Http\Controllers;

use App\Models\CashIn;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    // public function getLedgerData($id)
    // {
    //     try {
    //         $cashIn = CashIn::with(['vault', 'bags', 'user'])
    //             ->findOrFail($id);

    //         // Calculate opening balance
    //         $vault = $cashIn->vault;
    //         $isApproved = $cashIn->status === 'approved';

    //         // If approved, opening balance = current vault balance - this cash-in amount
    //         // If not approved, opening balance = current vault balance
    //         $openingBalance = $isApproved
    //             ? ($vault->balance - $cashIn->cash_in_amount)
    //             : $vault->balance;

    //         // Closing balance is current vault balance
    //         $closingBalance = $vault->balance;

    //         // Calculate order details if needed
    //         $ordersTotal = 0;
    //         if ($cashIn->orders && is_array($cashIn->orders)) {
    //             foreach ($cashIn->orders as $order) {
    //                 $ordersTotal += $order['amount'] ?? 0;
    //             }
    //         }

    //         // Prepare ledger rows
    //         $ledgerRows = [
    //             [
    //                 'sl' => 'Opening',
    //                 'date' => $cashIn->created_at->format('d/m/Y'),
    //                 'debit' => null,
    //                 'credit' => number_format($openingBalance, 2),
    //                 'balance' => number_format($openingBalance, 2),
    //                 'note' => 'Opening balance before this cash-in',
    //             ],
    //             [
    //                 'sl' => '1',
    //                 'date' => $cashIn->created_at->format('d/m/Y'),
    //                 'debit' => number_format($cashIn->cash_in_amount, 2),
    //                 'credit' => null,
    //                 'balance' => number_format($openingBalance + $cashIn->cash_in_amount, 2),
    //                 'note' => "Cash-in transaction #{$cashIn->tran_id}",
    //             ],
    //             [
    //                 'sl' => 'Closing',
    //                 'date' => $cashIn->created_at->format('d/m/Y'),
    //                 'debit' => null,
    //                 'credit' => null,
    //                 'balance' => number_format($closingBalance, 2),
    //                 'note' => 'Closing balance after cash-in',
    //             ],
    //         ];

    //         return response()->json([
    //             'success' => true,
    //             'data' => [
    //                 'cash_in' => $cashIn,
    //                 'opening_balance' => $openingBalance,
    //                 'closing_balance' => $closingBalance,
    //                 'ledger_rows' => $ledgerRows,
    //                 'vault' => [
    //                     'vault_id' => $vault->vault_id,
    //                     'current_balance' => $vault->balance,
    //                 ],
    //                 'status' => $cashIn->status,
    //                 'is_approved' => $isApproved,
    //             ],
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error fetching ledger data: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }
    public function getLedgerData($id)
    {
        try {
            $cashIn = CashIn::with([
                'vault',
                'bags',
                'user',
                'requiredVerifiers.user',
                'requiredApprovers.user'
            ])->findOrFail($id);

            // Calculate opening balance
            $vault = $cashIn->vault;
            $isApproved = $cashIn->status === 'approved';

            // If approved, opening balance = current vault balance - this cash-in amount
            // If not approved, opening balance = current vault balance
            $openingBalance = $isApproved
                ? ($vault->balance - $cashIn->cash_in_amount)
                : $vault->balance;

            // Closing balance is current vault balance
            $closingBalance = $vault->balance;

            // Calculate order details if needed
            $ordersTotal = 0;
            if ($cashIn->orders && is_array($cashIn->orders)) {
                foreach ($cashIn->orders as $order) {
                    $ordersTotal += $order['amount'] ?? 0;
                }
            }

            // Prepare ledger rows
            $ledgerRows = [
                [
                    'sl' => 'Opening',
                    'date' => $cashIn->created_at->format('d/m/Y'),
                    'debit' => null,
                    'credit' => number_format($openingBalance, 2),
                    'balance' => number_format($openingBalance, 2),
                    'note' => 'before this cash-in',
                ],
                [
                    'sl' => '1',
                    'date' => $cashIn->created_at->format('d/m/Y'),
                    'debit' => number_format($cashIn->cash_in_amount, 2),
                    'credit' => null,
                    'balance' => number_format($openingBalance + $cashIn->cash_in_amount, 2),
                    'note' => "transaction #{$cashIn->tran_id}",
                ],
                [
                    'sl' => 'Closing',
                    'date' => $cashIn->created_at->format('d/m/Y'),
                    'debit' => null,
                    'credit' => null,
                    'balance' => number_format($closingBalance, 2),
                    'note' => 'after cash-in',
                ],
            ];

            // Prepare verifiers data
            $verifiers = $cashIn->requiredVerifiers->map(function ($verifier) {
                return [
                    'id' => $verifier->id,
                    'name' => $verifier->user->name ?? 'N/A',
                    'email' => $verifier->user->email ?? 'N/A',
                    'verified' => $verifier->verified ?? false,
                    'verified_at' => $verifier->verified_at ? $verifier->verified_at->format('d/m/Y h:i A') : null,
                ];
            });

            // Prepare approvers data
            $approvers = $cashIn->requiredApprovers->map(function ($approver) {
                return [
                    'id' => $approver->id,
                    'name' => $approver->user->name ?? 'N/A',
                    'email' => $approver->user->email ?? 'N/A',
                    'approved' => $approver->approved ?? false,
                    'approved_at' => $approver->approved_at ? $approver->approved_at->format('d/m/Y h:i A') : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'cash_in' => $cashIn,
                    'opening_balance' => $openingBalance,
                    'closing_balance' => $closingBalance,
                    'ledger_rows' => $ledgerRows,
                    'vault' => [
                        'vault_id' => $vault->vault_id,
                        'current_balance' => $vault->balance,
                    ],
                    'status' => $cashIn->status,
                    'is_approved' => $isApproved,
                    'verifiers' => $verifiers,
                    'approvers' => $approvers,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching ledger data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
