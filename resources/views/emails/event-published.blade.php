@component('emails.brand-layout', [
    'subject' => $subject ?? 'Votix',
    'headline' => $headline ?? null,
    'intro' => $intro ?? null,
    'logoUrl' => $logoUrl ?? null,
    'actionUrl' => $actionUrl ?? null,
    'actionText' => $actionText ?? null,
    'footerNote' => $footerNote ?? null,
])
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <td style="padding:14px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px;">
                <p style="margin:0 0 8px; font-size:13px; color:#6b7280;">Évènement</p>
                <p style="margin:0; font-size:16px; color:#111827; font-weight:700;">{{ $eventTitle ?? '—' }}</p>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-top:14px;">
        <tr>
            <td style="padding:10px 0; border-bottom:1px solid #f1f5f9;">
                <span style="font-size:13px; color:#6b7280;">Statut</span><br>
                <span style="font-size:14px; color:#111827; font-weight:600;">{{ $statusText ?? '—' }}</span>
            </td>
        </tr>
        @if(!empty($eventUrl))
            <tr>
                <td style="padding:10px 0;">
                    <span style="font-size:13px; color:#6b7280;">Lien public</span><br>
                    <a href="{{ $eventUrl }}" style="font-size:14px; color:#2563eb; text-decoration:none;">{{ $eventUrl }}</a>
                </td>
            </tr>
        @endif
    </table>
@endcomponent

