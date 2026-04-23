<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    /* Reset and Single Page Optimization */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    @page { size: A4; margin: 0; }
    
    body { 
      font-family: 'Segoe UI', Roboto, sans-serif; 
      color: #1a202c; 
      padding: 40px; 
      background: #fff;
      line-height: 1.4;
    }

    /* Header */
    .header { 
      display: flex; 
      justify-content: space-between; 
      align-items: center; 
      border-bottom: 3px solid #1a202c; 
      padding-bottom: 15px; 
      margin-bottom: 30px; 
    }
    .header h1 { font-size: 26px; font-weight: 900; text-transform: uppercase; color: #1a202c; }
    .header-meta { text-align: right; font-size: 10px; color: #718096; font-weight: bold; }

    /* Main Info Grid */
    .content-grid { display: flex; gap: 40px; margin-bottom: 30px; }
    
    /* Left side (Profile) */
    .left-col { width: 30%; text-align: left; }
    .profile-img { 
      width: 100%; 
      aspect-ratio: 1/1; 
      object-fit: cover; 
      border-radius: 12px; 
      border: 1px solid #e2e8f0; 
      margin-bottom: 15px;
    }

    /* Right side (Data) */
    .right-col { width: 70%; }
    .user-name { font-size: 28px; font-weight: 800; color: #1a202c; margin-bottom: 2px; }
    .user-email { font-size: 14px; color: #4a5568; margin-bottom: 20px; font-style: italic; }
    
    .section-title { 
      font-size: 11px; 
      font-weight: 800; 
      color: #2563eb; 
      text-transform: uppercase; 
      letter-spacing: 1.5px; 
      margin-bottom: 10px; 
      display: block;
      border-bottom: 1px solid #e2e8f0;
      padding-bottom: 5px;
    }

    .info-box { 
      background: #f8fafc; 
      border-radius: 10px; 
      padding: 20px; 
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .info-item b { display: block; font-size: 10px; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; }
    .info-item p { font-size: 14px; font-weight: 600; color: #1e293b; }
    
    .role-badge { 
      display: inline-block; 
      background: #1e293b; 
      color: #fff; 
      padding: 5px 12px; 
      border-radius: 6px; 
      font-size: 11px; 
      font-weight: 700; 
      margin-top: 15px;
      margin-right: 6px;
    }

    /* NID Row - Pasha Pasha 50/50 */
    .nid-section { margin-top: 20px; }
    .nid-row { 
      display: flex; 
      width: 100%; 
      gap: 20px; /* Space between the two cards */
    }
    .nid-wrapper { 
      flex: 1; /* This forces exactly 50% width minus the gap */
      text-align: center;
    }
    .nid-card { 
      width: 100%; 
      height: 220px; /* Adjusted height for better visibility */
      background: #f1f5f9; 
      border-radius: 12px; 
      border: 1px solid #cbd5e1;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .nid-card img { 
      width: 100%; 
      height: 100%; 
      object-fit: contain; /* Keeps the NID proportions correct */
      background: #fff;
    }
    .nid-label { 
      font-size: 11px; 
      font-weight: 800; 
      color: #64748b; 
      margin-top: 10px; 
      text-transform: uppercase; 
    }

    /* Footer */
    .footer { 
      margin-top: 50px;
      padding-top: 15px;
      border-top: 1px solid #edf2f7; 
      display: flex; 
      justify-content: space-between; 
      font-size: 10px; 
      color: #94a3b8; 
      font-family: monospace;
    }
  </style>
</head>
<body>

  <div class="header">
    <div>
      <h1>Verified Personnel Profile</h1>
    </div>
    <div class="header-meta">
      REF ID: #{{ $user->id }}<br>
      DATE: {{ now()->format('d/m/Y') }}
    </div>
  </div>

  <div class="content-grid">
    <div class="left-col">
      @if($profileImg)
        <img src="{{ $profileImg }}" class="profile-img">
      @endif
      <div style="text-align: left;">
        @foreach($user->roles as $role)
          <span class="role-badge">{{ $role->name }}</span>
        @endforeach
      </div>
    </div>

    <div class="right-col">
      <h2 class="user-name">{{ $user->name }}</h2>
      <p class="user-email">{{ $user->email }}</p>

      <span class="section-title">Official Contact Information</span>
      <div class="info-box">
        <div class="info-item" style="grid-column: span 2;">
          <b>Current Registered Address</b>
          <p>{{ collect([$user->current_address, $user->current_thana, $user->current_district, $user->current_division])->filter()->join(', ') }}</p>
        </div>
        <div class="info-item">
          <b>Primary Phone</b>
          <p>{{ $user->phone }}</p>
        </div>
        <div class="info-item">
          <b>Document Status</b>
          <p style="color: #059669;">✓ ACTIVE & VERIFIED</p>
        </div>
      </div>
    </div>
  </div>

  <div class="nid-section">
    <span class="section-title">National Identity Card (NID)</span>
    <div class="nid-row">
      <div class="nid-wrapper">
        <div class="nid-card">
          @if($nidFront)
            <img src="{{ $nidFront }}" alt="NID Front">
          @else
            <span style="color: #94a3b8; font-size: 12px;">Front Side Image Missing</span>
          @endif
        </div>
        <p class="nid-label">NID: Front View</p>
      </div>

      <div class="nid-wrapper">
        <div class="nid-card">
          @if($nidBack)
            <img src="{{ $nidBack }}" alt="NID Back">
          @else
            <span style="color: #94a3b8; font-size: 12px;">Back Side Image Missing</span>
          @endif
        </div>
        <p class="nid-label">NID: Back View</p>
      </div>
    </div>
  </div>

  <div class="footer">
    <span>THIS IS A SYSTEM GENERATED DOCUMENT</span>
    <span>SECURITY HASH: {{ strtoupper(substr(md5($user->id), 0, 16)) }}</span>
  </div>

</body>
</html>