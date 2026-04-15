<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Email Verification</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f8fafd; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; padding: 40px 30px; text-align: center; }
        .content { padding: 40px 30px; text-align: center; }
        .otp-box { 
            background: #f8fafc; 
            border: 2px dashed #3b82f6; 
            border-radius: 12px; 
            padding: 20px; 
            font-size: 32px; 
            letter-spacing: 8px; 
            font-weight: bold; 
            color: #1e40af; 
            margin: 25px 0;
        }
        .button {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
        }
        .footer { text-align: center; padding: 20px; color: #64748b; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin:0; font-size:28px;">QBits Vault</h1>
            <p style="margin:8px 0 0; opacity:0.9;">Secure Email Verification</p>
        </div>

        <div class="content">
            <h2 style="color:#1e3a8a;">Verify Your Email Address</h2>
            <p style="color:#475569; font-size:16px; line-height:1.6;">
                Hi <strong>{{ $user->name }}</strong>,<br><br>
                Please use the following OTP to verify your email address.
            </p>

            <div class="otp-box">
                {{ $otp }}
            </div>

            <p style="color:#64748b; font-size:14px;">
                This code will expire in <strong>15 minutes</strong>.
            </p>

            <a href="#" class="button">Enter Code in Application</a>
        </div>

        <div class="footer">
            If you didn't request this, please ignore this email.<br>
            © {{ date('Y') }} QBits Vault. All rights reserved.
        </div>
    </div>
</body>
</html>