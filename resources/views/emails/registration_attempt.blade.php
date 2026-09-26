<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Sign-up attempt with your email</title>
</head>

<body style="font-family: Arial, sans-serif; background:#f5f0e8; padding:24px; color:#2c2416;">
    <div
        style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e4ddd0;">
        <div style="background:#2c2416;color:#fff;padding:20px 28px;">
            <h2 style="margin:0;font-size:18px;">Villa Elena Private Pool Resort</h2>
        </div>
        <div style="padding:28px;line-height:1.6;">
            <p style="margin:0 0 14px;">Hi {{ $recipientName }},</p>

            <p style="margin:0 0 14px;">
                Someone just tried to create a Villa Elena account using this email address. You already
                have one, so nothing was created and nothing about your account has changed.
            </p>

            <p style="margin:0 0 14px;">
                <strong>If this was you</strong> &mdash; you already have an account, so just sign in as
                usual. If you have forgotten your password, you can reset it here:
            </p>

            <p style="margin:0 0 20px;">
                <a href="{{ $forgotPasswordUrl }}"
                    style="color:#8a6d3b;text-decoration:underline;">{{ $forgotPasswordUrl }}</a>
            </p>

            <p style="margin:0 0 14px;">
                <strong>If this was not you</strong> &mdash; there is nothing you need to do. Somebody
                mistyped their address, or was checking whether you have an account here. Your password
                still works and no one has gained access to it.
            </p>

            <p style="margin:24px 0 0;font-size:12px;color:#8a8478;">
                We sent this once, to the address that was entered. We will not email you again about
                further attempts.
            </p>
        </div>
    </div>
</body>

</html>
