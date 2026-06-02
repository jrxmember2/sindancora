<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $charge->description }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#374151;">
    @php
        $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    @endphp
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
                            <h1 style="margin:0 0 8px; font-size:22px; color:#111827;">{{ $charge->description }}</h1>
                            <p style="margin:0 0 4px; font-size:13px; color:#6b7280;">
                                @if($condominiumName){{ $condominiumName }} &middot; @endif Vencimento {{ $charge->due_date->format('d/m/Y') }}
                            </p>
                            <p style="margin:16px 0; font-size:28px; font-weight:bold; color:#111827;">{{ $brl($charge->amount) }}</p>

                            @if($charge->invoice_url)
                                <a href="{{ $charge->invoice_url }}" style="display:inline-block; background-color:#1e40af; color:#ffffff; text-decoration:none; font-size:15px; font-weight:bold; padding:12px 24px; border-radius:8px;">
                                    Pagar com boleto ou PIX
                                </a>
                            @endif

                            @if($charge->pix_payload)
                                <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">
                                <p style="margin:0 0 6px; font-size:13px; font-weight:bold; color:#374151;">PIX copia-e-cola</p>
                                <p style="margin:0; font-size:12px; word-break:break-all; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:12px; color:#374151;">{{ $charge->pix_payload }}</p>
                            @endif

                            @if($charge->bank_slip_line)
                                <p style="margin:18px 0 6px; font-size:13px; font-weight:bold; color:#374151;">Linha digitável do boleto</p>
                                <p style="margin:0; font-size:13px; font-family:monospace; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:12px; color:#374151;">{{ $charge->bank_slip_line }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f9fafb; padding:18px 28px; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; font-size:12px; color:#9ca3af;">
                                Você recebeu este e-mail porque possui uma cobrança em aberto em {{ config('app.name') }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
