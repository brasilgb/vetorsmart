<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Prévia no celular</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; gap: 12px; background: #E2E8F0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: #0B1220; padding: 24px 12px; box-sizing: border-box; }
        p { margin: 0; font-size: .95rem; text-align: center; }
        .phone { width: 390px; max-width: 100%; height: 780px; max-height: calc(100vh - 96px); border: 12px solid #0F172A; border-radius: 36px; overflow: hidden; background: #fff; box-shadow: 0 12px 32px rgba(2, 6, 23, .3); }
        iframe { width: 100%; height: 100%; border: 0; display: block; }
    </style>
</head>
<body>
    <p>Prévia do rascunho em um celular (390 px de largura). Esta versão ainda não está publicada.</p>
    <div class="phone"><iframe src="{{ $frame }}" title="Prévia do site no celular"></iframe></div>
</body>
</html>
