<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Personnel Identity Report</title>
  <style>
    /* Reset and Single Page Optimization */
    * { 
      margin: 0; 
      padding: 0; 
      box-sizing: border-box; 
    }
    
    @page { 
      size: a4 portrait; 
      margin: 0; 
    }
    
    body { 
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
      color: #1a202c; 
      padding: 40px; 
      background: #ffffff;
      line-height: 1.4;
      font-size: 13px;
    }

    /* Layout structural fallback using standard table styling mechanics */
    .layout-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 25px;
    }
    .layout-table td {
      vertical-align: top;
      padding: 0;
    }

    /* Header component configuration */
    .header-left {
      text-align: left;
    }
    .header-left h1 { 
      font-size: 24px; 
      font-weight: 800; 
      text-transform: uppercase; 
      color: #1a2b4b; 
      letter-spacing: -0.5px;
    }
    .header-right { 
      text-align: right; 
      font-size: 10px; 
      color: #64748b; 
      font-family: monospace;
      line-height: 1.5;
    }
    .header-divider {
      border-bottom: 3px solid #1a2b4b;
      margin-top: 10px;
      margin-bottom: 25px;
    }

    /* Left Side Segment Columns */
    .profile-column {
      width: 28%;
      padding-right: 25px !important;
    }
    .profile-img { 
      width: 100%; 
      height: auto;
      border-radius: 12px; 
      border: 1px solid #e2e8f0; 
      margin-bottom: 12px;
      display: block;
    }
    .role-badge { 
      display: inline-block; 
      background: #1a2b4b; 
      color: #ffffff; 
      padding: 4px 10px; 
      border-radius: 6px; 
      font-size: 10px; 
      font-weight: 700; 
      margin-bottom: 5px;
      margin-right: 4px;
      text-transform: uppercase;
    }

    /* Right Side Segment Columns */
    .data-column { 
      width: 72%; 
    }
    .user-name { 
      font-size: 26px; 
      font-weight: 800; 
      color: #1a2b4b; 
      margin-bottom: 2px; 
    }
    .user-email { 
      font-size: 13px; 
      color: #64748b; 
      margin-bottom: 20px; 
    }
    
    .section-title { 
      font-size: 10px; 
      font-weight: 800; 
      color: #2563eb; 
      text-transform: uppercase; 
      letter-spacing: 1.2px; 
      margin-bottom: 8px; 
      display: block;
      border-bottom: 1px solid #e2e8f0;
      padding-bottom: 4px;
    }

    /* Traditional Data Boxes Info Components */
    .info-box-table {
      width: 100%;
      background: #f8fafc;
      border-collapse: separate;
      border-spacing: 15px;
      border-radius: 12px;
      border: 1px solid #f1f5f9;
      margin-bottom: 5px;
    }
    .info-item b { 
      display: block; 
      font-size: 9px; 
      color: #94a3b8; 
      text-transform: uppercase; 
      margin-bottom: 3px; 
      letter-spacing: 0.5px;
    }
    .info-item p { 
      font-size: 13px; 
      font-weight: 600; 
      color: #1e293b; 
    }
    
    /* Dedicated Single-Row NID Table layout (Guarantees true side-by-side rendering) */
    .nid-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 5px;
    }
    .nid-cell {
      width: 50%;
      vertical-align: top;
    }
    .nid-cell.left {
      padding-right: 10px;
    }
    .nid-cell.right {
      padding-left: 10px;
    }
    .nid-card { 
      width: 100%; 
      height: 180px; /* Constrained height footprint to enforce one single page limit rules */
      background: #f8fafc; 
      border-radius: 10px; 
      border: 1px solid #cbd5e1;
      text-align: center;
      vertical-align: middle;
    }
    .nid-card img { 
      max-width: 100%; 
      max-height: 178px; 
      display: block;
      margin: 0 auto;
    }
    .nid-placeholder {
      line-height: 178px;
      color: #94a3b8;
      font-size: 12px;
      font-weight: 500;
    }
    .nid-label { 
      font-size: 10px; 
      font-weight: 800; 
      color: #64748b; 
      margin-top: 8px; 
      text-transform: uppercase; 
      text-align: center;
      letter-spacing: 0.5px;
    }

    /* Fixed Sticky Position Page Footer */
    .footer-table {
      width: 100%;
      position: absolute;
      bottom: 40px;
      left: 40px;
      right: 40px;
      border-top: 1px solid #e2e8f0; 
      padding-top: 12px;
    }
    .footer-table td {
      font-size: 9px; 
      color: #94a3b8; 
      font-family: monospace;
      font-weight: 600;
    }
  </style>
</head>
<body>

  <table class="layout-table">
    <tr>
      <td class="header-left">
        <h1>Verified Personnel Profile</h1>
      </td>
      <td class="header-right">
        REF ID: #{{ $user->id }}<br>
        DATE: {{ now()->format('d/m/Y') }}
      </td>
    </tr>
  </table>

  <div class="header-divider"></div>

  <table class="layout-table">
    <tr>
      <td class="profile-column">
        @if($profileImg)
          <img src="{{ $profileImg }}" class="profile-img" alt="Profile Image">
        @else
          <div class="profile-img" style="height: 150px; background: #f8fafc; border: 1px dashed #cbd5e1; text-align: center; line-height: 150px; color: #94a3b8; font-size: 12px;">No Photo</div>
        @endif
        {{-- <div>
          @foreach($user->roles as $role)
            <span class="role-badge">{{ $role->name }}</span>
          @endforeach
        </div> --}}
      </td>

      <td class="data-column">
        <h2 class="user-name">{{ $user->name }}</h2>
        <p class="user-email">{{ $user->email }}</p>

        <span class="section-title">Official Contact Information</span>
        <table class="info-box-table">
          <tr>
            <td colspan="2" class="info-item">
              <b>Current Registered Address</b>
              <p>{{ collect([$user->current_address, $user->current_thana, $user->current_district, $user->current_division])->filter()->join(', ') }}</p>
            </td>
          </tr>
          <tr>
            <td class="info-item" style="width: 50%;">
              <b>Primary Phone</b>
              <p>{{ $user->phone }}</p>
            </td>
            <td class="info-item" style="width: 50%;">
              <b>Document Status</b>
              <p style="color: #059669;">✓ ACTIVE & VERIFIED</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <div style="margin-top: 5px;">
    <span class="section-title">National Identity Card (NID)</span>
    <table class="nid-table">
      <tr>
        <td class="nid-cell left">
          <table class="nid-card">
            <tr>
              <td>
                @if($nidFront)
                  <img src="{{ $nidFront }}" alt="NID Front">
                @else
                  <div class="nid-placeholder">Front Side Image Missing</div>
                @endif
              </td>
            </tr>
          </table>
          <p class="nid-label">NID: Front View</p>
        </td>

        <td class="nid-cell right">
          <table class="nid-card">
            <tr>
              <td>
                @if($nidBack)
                  <img src="{{ $nidBack }}" alt="NID Back">
                @else
                  <div class="nid-placeholder">Back Side Image Missing</div>
                @endif
              </td>
            </tr>
          </table>
          <p class="nid-label">NID: Back View</p>
        </td>
      </tr>
    </table>
  </div>

  <table class="footer-table">
    <tr>
      <td style="text-align: left;">THIS IS A SYSTEM GENERATED DOCUMENT</td>
      <td style="text-align: right;">SECURITY HASH: {{ strtoupper(substr(md5($user->id), 0, 16)) }}</td>
    </tr>
  </table>

</body>
</html>