<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convite para o portal</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#374151;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background-color:#1e40af; padding:20px 28px;">
                            <span style="color:#ffffff; font-size:18px; font-weight:bold;">{{ $tenantName }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 12px; font-size:22px; color:#111827;">Olá, {{ $userName }}!</h1>
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
                                Você foi convidado(a) a acessar o portal do morador do <strong>{{ $tenantName }}</strong>.
                                Por lá você acompanha comunicados, abre e acompanha ocorrências, faz reservas de áreas
                                comuns e acessa os documentos do seu condomínio.
                            </p>
                            <p style="margin:0 0 24px; font-size:15px; line-height:1.6;">
                                Para ativar seu acesso, defina sua senha clicando no botão abaixo:
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:8px; background-color:#1e40af;">
                                        <a href="{{ $url }}" target="_blank"
                                           style="display:inline-block; padding:12px 28px; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none;">
                                            Ativar meu acesso
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:24px 0 0; font-size:13px; color:#6b7280; line-height:1.6;">
                                Se o botão não funcionar, copie e cole este endereço no navegador:<br>
                                <a href="{{ $url }}" style="color:#1e40af; word-break:break-all;">{{ $url }}</a>
                            </p>
                            <p style="margin:16px 0 0; font-size:12px; color:#9ca3af;">
                                Este link de ativação é válido por tempo limitado. Se você não esperava este convite, ignore este e-mail.
                            </p>
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
