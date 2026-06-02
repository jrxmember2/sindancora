<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $announcement->title }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#374151;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background-color:#1e40af; padding:20px 28px;">
                            <span style="color:#ffffff; font-size:18px; font-weight:bold;">{{ config('app.name') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            @if($announcement->urgency === 'high')
                                <span style="display:inline-block; background-color:#fef2f2; color:#b91c1c; font-size:12px; font-weight:bold; padding:4px 10px; border-radius:999px; margin-bottom:12px;">URGENTE</span>
                            @endif
                            <h1 style="margin:0 0 8px; font-size:22px; color:#111827;">{{ $announcement->title }}</h1>
                            <p style="margin:0 0 4px; font-size:13px; color:#6b7280;">
                                @if($condominiumName){{ $condominiumName }} &middot; @endif{{ $categoryLabel }}
                            </p>
                            <hr style="border:none; border-top:1px solid #e5e7eb; margin:18px 0;">
                            <div style="font-size:15px; line-height:1.6; color:#374151;">
                                {!! $announcement->body !!}
                            </div>
                            @if($announcement->expires_at)
                                <p style="margin:24px 0 0; font-size:12px; color:#9ca3af;">
                                    Este comunicado é válido até {{ $announcement->expires_at->format('d/m/Y') }}.
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f9fafb; padding:18px 28px; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; font-size:12px; color:#9ca3af;">
                                Você recebeu este e-mail porque está cadastrado neste condomínio em {{ config('app.name') }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
