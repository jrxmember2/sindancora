@component('mail::message')
# Bem-vindo ao Sindâncora, {{ $tenantName }}!

Seu pagamento foi confirmado e sua conta já está ativa. 🎉

Use o botão abaixo para acessar pela primeira vez (o link é pessoal e expira em 7 dias):

@component('mail::button', ['url' => $magicLink])
Acessar minha conta
@endcomponent

Se preferir, acesse pelo login com as credenciais temporárias:

- **Endereço:** [{{ $loginUrl }}]({{ $loginUrl }})
- **E-mail:** {{ $email }}
- **Senha temporária:** `{{ $tempPassword }}`

Por segurança, troque a senha logo no primeiro acesso, em **Meu perfil**.

Qualquer dúvida, estamos à disposição.

Obrigado,<br>
Equipe Sindâncora
@endcomponent
