@php
    $business = $content['business_name'] ?? '';
    $colors = \App\Services\Websites\SegmentTheme::colors($content);
    $fontStack = ($content['font'] ?? 'sans') === 'serif' ? 'Georgia, Times New Roman, serif' : 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
    $selected = old('service_id', $chosen);
    $selectedName = collect($services)->first(fn ($service) => is_array($service) && isset($service['id']) && (int) $service['id'] === (int) $selected)['name'] ?? null;
    $municipalityText = old('municipality', $municipality?->inputLabel() ?? '');
    $municipalityId = old('municipality_id', $municipality?->id);
    $mode = $content['location']['mode'] ?? null;
    $municipalityLabel = $mode === 'at_company' ? 'Seu município' : 'Município onde você precisa do serviço';
    $onlineLabel = $onlineLabel ?? 'Atendimento online';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Solicitar orçamento · {{ $business }}</title>
    <style>
        :root { --site-accent: {{ $colors['accent'] }}; --site-font: {{ $fontStack }}; --site-text: #0B1220; --site-muted: #475569; --site-border: #CBD5E1; --site-tone: {{ $colors['tone'] }}; --site-danger: #B91C1C; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: var(--site-font); color: var(--site-text); background: #fff; line-height: 1.6; font-size: 16px; }
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
        .hint { font-weight: 400; color: var(--site-muted); font-size: .9rem; margin: 6px 0 0; }
        label .hint { margin: 0; }
        input[type=text], input[type=tel], input[type=email], select, textarea { width: 100%; font: inherit; padding: 12px; min-height: 48px; border: 1px solid #94A3B8; border-radius: 10px; background: #fff; color: var(--site-text); }
        textarea { min-height: 120px; resize: vertical; }
        .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; }
        .check input { width: 20px; height: 20px; margin-top: 3px; accent-color: var(--site-accent); }
        .error { color: var(--site-danger); font-size: .9rem; margin: 6px 0 0; }
        button { font: inherit; font-weight: 600; background: var(--site-accent); color: #fff; border: 0; border-radius: 10px; padding: 14px 22px; cursor: pointer; min-height: 48px; }
        .trap { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        .notice { border: 1px solid #86EFAC; background: #F0FDF4; color: #14532D; border-radius: 12px; padding: 16px; margin-bottom: 24px; }
        .alt { margin-top: 28px; color: var(--site-muted); font-size: .95rem; }
        .homolog { background: #FEF3C7; color: #78350F; text-align: center; padding: 10px 16px; font-size: .9rem; font-weight: 600; }
        button[disabled] { opacity: .75; cursor: progress; }
        .municipality-row { display: flex; gap: 8px; flex-wrap: wrap; }
        .municipality-row input { flex: 1 1 240px; width: auto; }
        button.locate { display: inline-flex; align-items: center; gap: 8px; background: #fff; color: var(--site-text); border: 1px solid #94A3B8; padding: 10px 14px; flex: 0 0 auto; }
        button.locate:hover { border-color: var(--site-accent); }
        .locate-status p { margin: 8px 0 0; }
        button.link-button { background: none; border: 0; color: var(--site-accent); text-decoration: underline; padding: 4px 2px; min-height: 0; font-weight: 700; }
        .coverage-warning { margin-top: 10px; border: 1px solid #FCD34D; background: #FFFBEB; color: #78350F; border-radius: 10px; padding: 12px 14px; }
        .coverage-warning p { margin: 0 0 4px; }
        @media (max-width: 640px) { form > div > button[type=submit] { width: 100%; } button.locate { width: 100%; justify-content: center; } }
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
    <h1>Solicitar orçamento</h1>
    <p class="lead">Conte o que você precisa. {{ $business }} recebe sua solicitação e entra em contato pelo telefone informado.@if ($selectedName) Serviço escolhido: <strong>{{ $selectedName }}</strong> (você pode trocar abaixo).@endif</p>

    @if ($sent)
        <div class="notice" role="status">
            <strong>Solicitação recebida.</strong> {{ $business }} vai analisar o pedido e entrar em contato. Não é preciso enviar de novo.
        </div>
    @endif

    <form method="post" action="{{ $action }}" novalidate>
        @csrf
        <input type="hidden" name="envio" value="{{ $sendToken }}">
        @error('envio')<p class="error" role="alert">{{ $message }}</p>@enderror
        @if (! empty($services))
            <div>
                <label for="service_id">Serviço</label>
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
            @include('website.partials.municipality-field', [
                'fieldId' => 'municipality',
                'label' => $municipalityLabel,
                'value' => $municipalityText,
                'municipalityId' => $municipalityId,
                'error' => $errors->first('municipality') ?: null,
                'coverage' => $coverage,
                'coverageCompany' => $business,
                'required' => true,
            ])
        </div>
        <div>
            <label for="description">O que você precisa?</label>
            <textarea id="description" name="description" required maxlength="1000" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description') }}</textarea>
            @error('description')<p class="error" id="description-error">{{ $message }}</p>@enderror
        </div>
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
        <p class="alt">Prefere conversar agora? <a href="{{ $assistant }}">{{ $onlineLabel }}</a>.</p>
    @endif
</main>
@include('website.partials.municipality-script')
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
