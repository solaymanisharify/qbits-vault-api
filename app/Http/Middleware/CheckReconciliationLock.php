<?php

namespace App\Http\Middleware;

use App\Models\Reconciliation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckReconciliationLock
{
    public function handle(Request $request, Closure $next): Response
    {
        // Get the latest (or active) reconciliation record
        // Adjust this query according to your actual business logic
        $reconciliation = Reconciliation::latest()->first();

        // No reconciliation → allow (or you can decide to block)
        if (!$reconciliation) {
            return $next($request);
        }

        $isLocked       = $reconciliation->is_locked === true;
        $isInProgress   = $reconciliation->status === 'in_progress';

        // Block if locked AND in progress
        if ($isLocked && $isInProgress) {
            return response()->json([
                'message' => 'Vault operations are temporarily locked due to an ongoing reconciliation process.',
                'status'  => 'reconciliation_in_progress',
                'locked'  => true
            ], 423); // 423 = Locked (semantic), or use 403 Forbidden
        }

        return $next($request);
    }
}
