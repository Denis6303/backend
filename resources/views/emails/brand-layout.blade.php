<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? 'Votix' }}</title>
</head>
<body style="margin:0; padding:0; background:#f5f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb; padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px; width:100%;">
                <tr>
                    <td style="padding:0 16px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e5e7eb;">
                            <tr>
                                <td style="background:#111827; padding:18px 24px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="left" style="vertical-align:middle;">
                                                <img src="{{ $logoUrl ?? '' }}" alt="Votix" style="height:36px; width:auto; display:block;">
                                            </td>
                                            <td align="right" style="vertical-align:middle;">
                                                <span style="font-size:12px; color:#d1d5db;">{{ $subject ?? 'Notification Votix' }}</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:28px 24px 22px;">
                                    @if(!empty($headline))
                                        <h1 style="margin:0 0 12px; font-size:24px; line-height:1.3; color:#111827;">{{ $headline }}</h1>
                                    @endif
                                    @if(!empty($intro))
                                        <p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:#4b5563;">{{ $intro }}</p>
                                    @endif

                                    {{ $slot }}

                                    @if(!empty($actionUrl) && !empty($actionText))
                                        <div style="margin:24px 0 0;">
                                            <a href="{{ $actionUrl }}"
                                               style="display:inline-block; background:#111827; color:#ffffff; text-decoration:none; padding:12px 18px; border-radius:10px; font-size:14px; font-weight:700;">
                                                {{ $actionText }}
                                            </a>
                                        </div>
                                    @endif

                                    @if(!empty($footerNote))
                                        <p style="margin:22px 0 0; font-size:13px; line-height:1.6; color:#6b7280;">
                                            {{ $footerNote }}
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        </table>
                        <p style="margin:14px 0 0; font-size:12px; color:#94a3b8; text-align:center;">
                            © {{ date('Y') }} Votix. Tous droits réservés.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

