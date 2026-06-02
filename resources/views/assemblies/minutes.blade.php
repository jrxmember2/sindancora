<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ata — {{ $assembly->title }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.6; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #6b7280; font-size: 11px; margin-bottom: 16px; }
        .content { white-space: pre-wrap; }
        .footer { margin-top: 28px; font-size: 10px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    <h1>Ata de Assembleia</h1>
    <div class="meta">
        {{ $assembly->condominium?->name }} ·
        {{ $assembly->title }}
        @if($assembly->scheduled_at) · {{ $assembly->scheduled_at->format('d/m/Y H:i') }} @endif
    </div>

    <div class="content">{{ $assembly->minutes }}</div>

    <div class="footer">
        Ata gerada por {{ config('app.name') }}
        @if($assembly->minutes_generated_at) em {{ $assembly->minutes_generated_at->format('d/m/Y H:i') }} @endif.
    </div>
</body>
</html>
