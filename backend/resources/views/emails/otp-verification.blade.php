<!DOCTYPE html>
<html>
<head>
    <title>Email Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 30px;
        }
        .otp-code {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 3px;
            color: #2563eb;
            margin: 20px 0;
            padding: 15px 25px;
            background-color: #f0f5ff;
            display: inline-block;
            border-radius: 6px;
            border: 1px dashed #93c5fd;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Verify Your Email Address</h2>
        <p>Hello,</p>
        <p>Thank you for registering with MyShop. Please use the following One Time Password (OTP) to verify your email address:</p>
        
        <div class="otp-code">{{ $otp }}</div>
        
        <p>This OTP is valid for 15 minutes. Please do not share this code with anyone for security reasons.</p>
        
        <p>If you didn't request this email, you can safely ignore it.</p>
        
        <div class="footer">
            <p>© {{ date('Y') }} MyShop. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
