<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>New Contact Form Message</title>
</head>

<body style="font-family: Arial, sans-serif; background:#f5f0e8; padding:24px; color:#2c2416;">
    <div
        style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e4ddd0;">
        <div style="background:#2c2416;color:#fff;padding:20px 28px;">
            <h2 style="margin:0;font-size:18px;">New Message from the Villa Elena Website</h2>
        </div>
        <div style="padding:28px;">
            <p style="margin:0 0 14px;"><strong>From:</strong> {{ $senderName }} ({{ $senderEmail }})</p>
            @if ($subjectLine)
                <p style="margin:0 0 14px;"><strong>Subject:</strong> {{ $subjectLine }}</p>
            @endif
            <p style="margin:0 0 6px;"><strong>Message:</strong></p>
            <p style="white-space:pre-line;line-height:1.6;background:#f9f5ee;padding:14px 16px;border-radius:8px;">
                {{ $messageBody }}</p>
            <p style="margin-top:24px;font-size:12px;color:#8a8478;">You can reply directly to this email to respond to
                {{ $senderName }}.</p>
        </div>
    </div>
</body>

</html>

