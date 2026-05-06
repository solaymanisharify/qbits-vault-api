{{--
    Route example (web.php):
    Route::get('/cashin-ledger/{id}', [CashInController::class, 'ledger'])->name('cashin.ledger');
    Route::get('/cashin-ledger/demo', fn() => view('cashin-ledger', ['demo' => true]))->name('cashin.ledger.demo');

    Controller method:
    public function ledger($id) {
        $cashIn = CashIn::with(['vault', 'bags', 'orders', 'user', 'verifiers', 'approvers'])->findOrFail($id);
        return view('cashin-ledger', compact('cashIn'));
    }
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cash-In Ledger &mdash; {{ $cashIn->tran_id ?? 'TXN-DEMO-2025' }}</title>
  <style>

    * {
  -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, sans-serif;
      font-size: 13px;
      color: #1f2937;
      background: #f3f4f6;
      padding: 32px 20px;
    }

    /* ── Page wrapper ── */
    .page {
      background: #fff;
      max-width: 860px;
      margin: 0 auto;
      padding: 36px 40px;
      border-radius: 8px;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
    }

    /* ── Print button (no-print) ── */
    .no-print {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-bottom: 24px;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 9px 20px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      border: none;
      letter-spacing: .3px;
      transition: opacity .15s;
    }
    .btn:hover { opacity: .85; }
    .btn-print  { background: #1e3a8a; color: #fff; }
    .btn-back   { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }

    /* ── Header ── */
    .ledger-header {
      text-align: center;
      border-bottom: 2.5px solid #1e3a8a;
      padding-bottom: 18px;
      margin-bottom: 22px;
    }
    .ledger-header h1 {
      font-size: 24px;
      font-weight: 800;
      letter-spacing: 4px;
      color: #1e3a8a;
      margin-bottom: 4px;
    }
    .ledger-header p {
      font-size: 11px;
      color: #6b7280;
      margin-bottom: 10px;
      letter-spacing: 1px;
      text-transform: uppercase;
    }
    .badge {
      display: inline-block;
      padding: 4px 14px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 1px;
    }
    .badge-approved { background: #dcfce7; color: #166534; }
    .badge-pending  { background: #fef3c7; color: #92400e; }
    .badge-rejected { background: #fee2e2; color: #991b1b; }

    /* ── Warning bar ── */
    .warn-bar {
      background: #fffbeb;
      border: 1px solid #fcd34d;
      border-radius: 6px;
      padding: 10px 14px;
      margin-bottom: 18px;
      font-size: 12px;
      color: #92400e;
    }

    /* ── Meta grid ── */
    .meta-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      overflow: hidden;
      margin-bottom: 22px;
    }
    .meta-cell {
      padding: 9px 14px;
      border-bottom: 1px solid #e5e7eb;
      border-right: 1px solid #e5e7eb;
    }
    .meta-cell:nth-child(even) { border-right: none; }
    .meta-cell.full { grid-column: span 2; border-right: none; }
    .meta-label {
      color: #6b7280;
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-bottom: 3px;
    }
    .meta-value { font-weight: 700; color: #111827; font-size: 13px; }
    .meta-value.mono { font-family: monospace; font-size: 12px; }

    /* ── Section title ── */
    .section-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 1.5px;
      color: #1e3a8a;
      text-transform: uppercase;
      margin: 22px 0 10px;
    }
    .section-title::before {
      content: '';
      width: 4px;
      height: 14px;
      background: #1e3a8a;
      border-radius: 2px;
      display: inline-block;
      flex-shrink: 0;
    }

    /* ── Tables ── */
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      margin-bottom: 4px;
    }
    th {
      background: #1e3a8a;
      color: #fff;
      padding: 9px 10px;
      text-align: center;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .5px;
    }
    th.left { text-align: left; }
    td {
      padding: 8px 10px;
      border-bottom: 1px solid #e5e7eb;
      text-align: right;
    }
    td.left   { text-align: left; }
    td.center { text-align: center; }
    td.debit  { color: #dc2626; font-weight: 600; }
    td.credit { color: #16a34a; font-weight: 600; }
    tr.opening td { background: #dbeafe; font-weight: 700; }
    tr.closing td { background: #dcfce7; font-weight: 700; }
    tr.pending  td { background: #fef9c3; }
    tr:last-child td { border-bottom: none; }

    /* ── Grand total bar ── */
    .grand-total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 14px;
      background: #f3f4f6;
      border-radius: 6px;
      margin-top: 8px;
      border: 1px solid #e5e7eb;
    }

    /* ── Signature blocks ── */
    .sig-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-top: 8px;
    }
    .sig-box {
      border: 1px solid #d1d5db;
      border-radius: 6px;
      padding: 14px;
      background: #fafafa;
      min-height: 130px;
    }
    .sig-name  { font-weight: 700; font-size: 13px; color: #111827; margin-bottom: 2px; }
    .sig-email { font-size: 11px; color: #6b7280; margin-bottom: 10px; }
    .sig-line  {
      border-top: 1.5px solid #374151;
      margin-top: 40px;
      padding-top: 6px;
      text-align: center;
      font-size: 10px;
      color: #9ca3af;
    }
    .verified-yes {
      display: inline-block;
      background: #dcfce7; color: #166534;
      font-size: 10px; font-weight: 700;
      padding: 2px 7px; border-radius: 3px; margin-left: 6px;
    }
    .verified-no {
      display: inline-block;
      background: #fee2e2; color: #991b1b;
      font-size: 10px; font-weight: 700;
      padding: 2px 7px; border-radius: 3px; margin-left: 6px;
    }
    .verified-date { font-size: 10px; color: #059669; font-style: italic; margin-top: 3px; }

    /* ── Status bar ── */
    .status-bar {
      display: flex;
      gap: 20px;
      align-items: center;
      padding: 12px 16px;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      margin-top: 22px;
      flex-wrap: wrap;
    }
    .status-item   { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #6b7280; }
    .status-dot    { width: 10px; height: 10px; border-radius: 50%; border: 1.5px solid #9ca3af; background: #fff; display: inline-block; }
    .status-dot.active { background: #1e3a8a; border-color: #1e3a8a; }

    /* ── Footer ── */
    .footer {
      text-align: center;
      margin-top: 26px;
      padding-top: 14px;
      border-top: 1px solid #e5e7eb;
      font-size: 11px;
      color: #9ca3af;
    }

    /* ── Print styles ── */
    @media print {
      body { background: #fff; padding: 0; }
      .page { box-shadow: none; border-radius: 0; padding: 0; max-width: 100%; }
      .no-print { display: none !important; }
      @page { size: A4; margin: 14mm 12mm; }
    }
  </style>
</head>
<body>

{{-- ════════════ DEMO DATA ════════════ --}}
@php
  /*
   * Replace this block with real model data from the controller:
   *   $isApproved      = $cashIn->is_approved;
   *   $tranId          = $cashIn->tran_id;
   *   $status          = $cashIn->status;   // 'pending' | 'verified' | 'approved' | 'completed' | 'rejected'
   *   $cashInAmount    = (float) $cashIn->cash_in_amount;
   *   $vaultBalance    = (float) $cashIn->vault->current_balance;
   *   $createdAt       = $cashIn->created_at->format('d M Y');
   *   $createdTime     = $cashIn->created_at->format('h:i A');
   *   $preparedBy      = $cashIn->user->name ?? '—';
   *   $vaultName       = $cashIn->vault->name ?? '—';
   *   $vaultCode       = $cashIn->vault->vault_code ?? '—';
   *   $bagBarcode      = $cashIn->bags->barcode ?? '—';
   *   $rackNumber      = $cashIn->bags->rack_number ?? '—';
   *   $denominations   = $cashIn->denominations ?? [];   // ['1000'=>3,'500'=>5, ...]
   *   $orders          = $cashIn->orders ?? [];
   *   $verifiers       = $cashIn->verifiers ?? [];
   *   $approvers       = $cashIn->approvers ?? [];
   */

  // ── Demo values ──────────────────────────────
  $isApproved   = true;
  $status       = 'approved';   // pending | verified | approved | completed | rejected
  $tranId       = 'TXN-M5K2J-0042';
  $cashInAmount = 185000.00;
  $vaultBalance = 1340000.00;
  $createdAt    = '28 Apr 2025';
  $createdTime  = '10:35 AM';
  $preparedBy   = 'Arafat Hossain';
  $vaultName    = 'Main HQ Vault';
  $vaultCode    = 'VLT-001';
  $bagBarcode   = 'BAG-00293847';
  $rackNumber   = '14-B';
  $denominations = [
    1000 => 100,
    500  => 80,
    200  => 50,
    100  => 150,
    50   => 60,
    20   => 25,
  ];
  $orders = [
    ['order_id' => 'ORD-10041'],
    ['order_id' => 'ORD-10042'],
    ['order_id' => 'ORD-10043'],
  ];
  $verifiers = [
    ['name' => 'Minhazul Islam',  'email' => 'minhaz@qbits.io',  'verified' => true,  'verified_at' => '28 Apr 2025, 11:00 AM'],
    ['name' => 'Rakib Hasan',     'email' => 'rakib@qbits.io',   'verified' => true,  'verified_at' => '28 Apr 2025, 11:15 AM'],
  ];
  $approvers = [
    ['name' => 'Farhan Ahmed',    'email' => 'farhan@qbits.io',  'approved' => true,  'approved_at' => '28 Apr 2025, 12:00 PM'],
    ['name' => 'Nusrat Jahan',    'email' => 'nusrat@qbits.io',  'approved' => false, 'approved_at' => null],
  ];
  // ── Derived ──────────────────────────────────
  $openingBalance = $isApproved ? $vaultBalance - $cashInAmount : $vaultBalance;
  $closingBalance = $isApproved ? $openingBalance + $cashInAmount : $openingBalance;
  $fmt = fn($n) => '৳' . number_format((float)$n, 2);
  $statusList = ['Pending', 'Verified', 'Approved', 'Completed', 'Rejected'];
@endphp


<div class="page">

  {{-- ── Print / Back buttons ── --}}
  <div class="no-print">
    <button class="btn btn-back" onclick="history.back()">
      &#8592; Back
    </button>
    <button class="btn btn-print" onclick="window.print()">
      &#128438; Print / Save PDF
    </button>
  </div>

  {{-- ── Header ── --}}
  <div class="ledger-header">
    <h1>CASH IN LEDGER</h1>
    <p>Official Vault Transaction Record</p>
    <span class="badge {{ $isApproved ? 'badge-approved' : ($status === 'rejected' ? 'badge-rejected' : 'badge-pending') }}">
      {{ strtoupper($status) }}
    </span>
  </div>

  {{-- ── Pending warning ── --}}
  @if(!$isApproved && $status !== 'rejected')
    <div class="warn-bar">
      &#9888; This cash-in is <strong>pending approval</strong>. The vault balance has not changed yet.
      The cash-in amount will be credited once all approvers have approved.
    </div>
  @endif

  {{-- ── Meta grid ── --}}
  <div class="meta-grid">
    <div class="meta-cell">
      <div class="meta-label">Vault Name</div>
      <div class="meta-value">{{ $vaultName }}</div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Vault Code</div>
      <div class="meta-value">{{ $vaultCode }}</div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Bag Barcode</div>
      <div class="meta-value">{{ $bagBarcode }}</div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Rack Number</div>
      <div class="meta-value">RN-{{ $rackNumber }}</div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Cash In Transaction ID</div>
      <div class="meta-value mono">{{ $tranId }}</div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Date &amp; Time</div>
      <div class="meta-value">{{ $createdAt }}, {{ $createdTime }}</div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Opening Balance</div>
      <div class="meta-value">{{ $fmt($openingBalance) }}</div>
    </div>
    <div class="meta-cell">
      <div class="meta-label">Generated At</div>
      <div class="meta-value">{{ now()->format('d M Y, h:i A') }}</div>
    </div>
    <div class="meta-cell full">
      <div class="meta-label">Prepared By</div>
      <div class="meta-value">{{ $preparedBy }}</div>
    </div>
  </div>

  {{-- ── Denomination breakdown ── --}}
  @if(!empty($denominations) && array_sum($denominations) > 0)
    <div class="section-title">Denomination Breakdown</div>
    <table>
      <thead>
        <tr>
          <th class="left">Denomination</th>
          <th>Count</th>
          <th>Total (৳)</th>
        </tr>
      </thead>
      <tbody>
        @foreach(array_reverse($denominations, true) as $note => $count)
          @if($count > 0)
            <tr>
              <td class="left">৳{{ number_format((int)$note) }}</td>
              <td class="center">{{ $count }}</td>
              <td>{{ number_format($note * $count, 2) }}</td>
            </tr>
          @endif
        @endforeach
      </tbody>
    </table>
    <div class="grand-total-row">
      <span style="font-weight:700">Grand Total</span>
      <span style="font-weight:700;font-size:15px;color:#1e3a8a">{{ $fmt($cashInAmount) }}</span>
    </div>
  @endif

  {{-- ── Verifiers ── --}}
  @if(!empty($verifiers))
    <div class="section-title">Verification</div>
    <div class="sig-grid">
      @foreach($verifiers as $v)
        <div class="sig-box">
          <div class="sig-name">
            {{ $v['name'] }}
            @if($v['verified'])
              <span class="verified-yes">&#10003; Verified</span>
            @else
              <span class="verified-no">Pending</span>
            @endif
          </div>
          <div class="sig-email">{{ $v['email'] }}</div>
          @if($v['verified'] && !empty($v['verified_at']))
            <div class="verified-date">Verified on: {{ $v['verified_at'] }}</div>
          @endif
          <div class="sig-line">Signature &amp; Date</div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- ── Approvers ── --}}
  @if(!empty($approvers))
    <div class="section-title">Approvals</div>
    <div class="sig-grid">
      @foreach($approvers as $a)
        <div class="sig-box">
          <div class="sig-name">
            {{ $a['name'] }}
            @if($a['approved'])
              <span class="verified-yes">&#10003; Approved</span>
            @else
              <span class="verified-no">Pending</span>
            @endif
          </div>
          <div class="sig-email">{{ $a['email'] }}</div>
          @if($a['approved'] && !empty($a['approved_at']))
            <div class="verified-date">Approved on: {{ $a['approved_at'] }}</div>
          @endif
          <div class="sig-line">Signature &amp; Date</div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- ── Status pipeline bar ── --}}
  <div class="status-bar">
    <span style="font-size:12px;font-weight:700;color:#374151;margin-right:4px">Status:</span>
    @foreach($statusList as $s)
      <span class="status-item">
        <span class="status-dot {{ strtolower($status) === strtolower($s) ? 'active' : '' }}"></span>
        {{ $s }}
      </span>
    @endforeach
  </div>

  {{-- ── Footer ── --}}
  <div class="footer">
    Generated on {{ now()->format('d M Y, h:i A') }} &middot; QBits Vault Management System
  </div>

</div>

</body>
</html>