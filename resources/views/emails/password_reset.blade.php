<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
    .container { max-width: 520px; margin: 40px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .header { background: #1a2b4b; padding: 32px; text-align: center; }
    .header h1 { color: white; margin: 0; font-size: 22px; }
    .body { padding: 32px; }
    .body p { color: #444; line-height: 1.6; }
    .btn { display: block; width: fit-content; margin: 24px auto; background: #4f46e5; color: white; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 15px; }
    .footer { text-align: center; padding: 20px; color: #999; font-size: 12px; }
    .expire { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 12px 16px; color: #c2410c; font-size: 13px; margin-top: 16px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🔐 Password Reset Request</h1>
    </div>
    <div class="body">
      <p>Hi <strong>{{ $name }}</strong>,</p>
      <p>A password reset was requested for your account. Click the button below to set a new password.</p>
      <a href="{{ $resetUrl }}" class="btn">Reset My Password</a>
      <div class="expire">
        ⏱ This link will expire in <strong>1 hour</strong>. Do not share it with anyone.
      </div>
      <p>If you didn't request this, you can safely ignore this email.</p>
    </div>
    <div class="footer">
      &copy; {{ date('Y') }} QBits Vault. All rights reserved.
    </div>
  </div>
</body>
</html>