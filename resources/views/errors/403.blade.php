<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso negado · VetorSmart</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        main { max-width: 420px; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 8px; }
        p { color: #475569; margin: 0 0 20px; }
        a { color: #2563eb; font-weight: 600; }
    </style>
</head>
<body>
    <main>
        <h1>Acesso negado</h1>
        <p>Sua conta não tem permissão para acessar esta área.</p>
        <a href="{{ url('/dashboard') }}">Ir para a sua área</a>
    </main>
</body>
</html>
