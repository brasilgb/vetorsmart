<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Empresa sem acesso · VetorSmart</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        main { max-width: 440px; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 8px; }
        p { color: #475569; margin: 0 0 20px; line-height: 1.5; }
        button { min-height: 44px; padding: 0 20px; border: 0; border-radius: 6px; background: #2563eb; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        button:focus-visible { outline: 3px solid #0f172a; outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <h1>Sua empresa está sem acesso</h1>
        <p>Sua conta não está vinculada a uma empresa ativa no momento. Se a empresa foi suspensa ou o seu acesso foi desativado, fale com o responsável pela empresa ou com o suporte da VetorSmart.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Sair</button>
        </form>
    </main>
</body>
</html>
