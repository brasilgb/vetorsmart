@php
    $business = $content['business_name'] ?? '';
    $accent = in_array($content['accent_color'] ?? '', ['#0F172A', '#1E3A5F', '#2563EB', '#06B6D4'], true) ? $content['accent_color'] : '#2563EB';
    // Cyan on white does not reach 4.5:1; text and buttons use its darker tone (#0E7490, 5.4:1).
    $accent = $accent === '#06B6D4' ? '#0E7490' : $accent;
    $fontStack = ($content['font'] ?? 'sans') === 'serif' ? 'Georgia, Times New Roman, serif' : 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
    $selected = old('service_id', $chosen);
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Solicitar atendimento · {{ $business }}</title>
    <style>
        :root { --site-accent: {{ $accent }}; --site-font: {{ $fontStack }}; --site-text: #0B1220; --site-muted: #475569; --site-border: #CBD5E1; --site-tone: {{ $theme['tone'] }}; --site-danger: #B91C1C; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: var(--site-font); color: var(--site-text); background: #fff; line-height: 1.6; }
        a { color: var(--site-accent); }
        a:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible, button:focus-visible { outline: 3px solid var(--site-accent); outline-offset: 2px; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 0 20px; }
        header { background: var(--site-tone); border-bottom: 1px solid var(--site-border); }
        header .wrap { padding-top: 16px; padding-bottom: 16px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center; }
        header a.name { font-weight: 700; color: var(--site-text); text-decoration: none; }
        main.wrap { padding-top: 32px; padding-bottom: 48px; }
        h1 { font-size: clamp(1.6rem, 4vw, 2.2rem); line-height: 1.2; margin: 0 0 8px; }
        p.lead { color: var(--site-muted); margin: 0 0 24px; }
        form { display: grid; gap: 18px; }
        label { font-weight: 600; display: block; margin-bottom: 6px; }
        .hint { font-weight: 400; color: var(--site-muted); font-size: .9rem; }
        input[type=text], input[type=tel], input[type=email], select, textarea { width: 100%; font: inherit; padding: 12px; border: 1px solid #94A3B8; border-radius: 10px; background: #fff; color: var(--site-text); }
        textarea { min-height: 120px; resize: vertical; }
        .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; }
        .check input { width: 20px; height: 20px; margin-top: 3px; accent-color: var(--site-accent); }
        .error { color: var(--site-danger); font-size: .9rem; margin: 6px 0 0; }
        button { font: inherit; font-weight: 600; background: var(--site-accent); color: #fff; border: 0; border-radius: 10px; padding: 14px 22px; cursor: pointer; }
        .trap { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        .notice { border: 1px solid #86EFAC; background: #F0FDF4; color: #14532D; border-radius: 12px; padding: 16px; margin-bottom: 24px; }
        .alt { margin-top: 28px; color: var(--site-muted); font-size: .95rem; }
        .homolog { background: #FEF3C7; color: #78350F; text-align: center; padding: 10px 16px; font-size: .9rem; font-weight: 600; }
        button[disabled] { opacity: .75; cursor: progress; }
        @media (max-width: 640px) { button { width: 100%; } }
    </style>
</head>
<body>
@if ($homolog)
    <div class="homolog" role="note">Ambiente de teste da VetorSmart. Esta não é uma empresa real e nenhum pedido feito aqui será atendido.</div>
@endif
<header>
    <div class="wrap">
        <a class="name" href="{{ $home }}">{{ $business }}</a>
        <a href="{{ $home }}">← Voltar ao site</a>
    </div>
</header>
<main class="wrap">
    <h1>Solicitar atendimento</h1>
    <p class="lead">Conte o que você precisa. {{ $business }} recebe sua solicitação e entra em contato pelo telefone informado.</p>

    @if ($sent)
        <div class="notice" role="status">
            <strong>Solicitação recebida.</strong> {{ $business }} vai analisar o pedido e entrar em contato. Não é preciso enviar de novo.
        </div>
    @endif

    <form method="post" action="{{ $action }}" novalidate>
        @csrf
        <input type="hidden" name="envio" value="{{ $sendToken }}">
        @error('envio')<p class="error" role="alert">{{ $message }}</p>@enderror
        @if ($fromPortal ?? false)
            <input type="hidden" name="origem" value="portal">
        @endif
        <div>
            <label for="name">Seu nome</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<p class="error" id="name-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="phone">Telefone ou WhatsApp, com DDD</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" required maxlength="30" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
            @error('phone')<p class="error" id="phone-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email">E-mail <span class="hint">(opcional)</span></label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" maxlength="160" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p class="error" id="email-error">{{ $message }}</p>@enderror
        </div>
        @if (! empty($services))
            <div>
                <label for="service_id">Serviço <span class="hint">(opcional)</span></label>
                <select id="service_id" name="service_id">
                    <option value="">Não sei ou outro serviço</option>
                    @foreach ($services as $service)
                        @if (isset($service['id']))
                            <option value="{{ $service['id'] }}" @selected((int) $selected === (int) $service['id'])>{{ $service['name'] }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="description">O que você precisa?</label>
            <textarea id="description" name="description" required maxlength="1000" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description') }}</textarea>
            @error('description')<p class="error" id="description-error">{{ $message }}</p>@enderror
        </div>
        <div class="trap" aria-hidden="true">
            <label for="website">Não preencha este campo</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <div>
            <label class="check"><input type="checkbox" name="consent" value="1" @checked(old('consent')) @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror> Autorizo {{ $business }} a me contatar sobre esta solicitação.</label>
            @error('consent')<p class="error" id="consent-error">{{ $message }}</p>@enderror
        </div>
        <div><button type="submit" data-send-button>Enviar solicitação</button></div>
    </form>

    @if ($assistant)
        <p class="alt">Prefere escolher um horário agora? <a href="{{ $assistant }}">Agende pelo atendimento online</a>.</p>
    @endif
</main>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    // A second click while the first send is on its way does nothing; the server also recognises a repeated form.
    (function () {
        var button = document.querySelector('[data-send-button]');
        if (!button || !button.form) { return; }
        button.form.addEventListener('submit', function () { button.disabled = true; button.textContent = 'Enviando…'; });
        window.addEventListener('pageshow', function () { button.disabled = false; button.textContent = 'Enviar solicitação'; });
    })();
</script>
</body>
</html>
