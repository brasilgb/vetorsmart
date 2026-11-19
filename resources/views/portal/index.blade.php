@php
    use App\Services\Portal\PortalSearch;
    use App\Services\Portal\PortalText;
    use Illuminate\Support\Facades\Vite;

    $value = fn (string $field): string => (string) ($filters[$field] ?? '');
    $moreOpen = $value('categoria') !== '' || $value('nome') !== '' || $searchErrors->has('nome') || $searchErrors->has('categoria');
    $tokens = PortalText::tokens($filters['q'] ?? null);
    $icons = [
        'search' => 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM21 21l-4.35-4.35',
        'pin' => 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 7.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z',
        'close' => 'M6 6l12 12M18 6 6 18',
        'filter' => 'M4 6h16M7 12h10M10 18h4',
        'arrow' => 'M5 12h14M13 6l6 6-6 6',
        'check' => 'M5 12l5 5 9-10',
        'sun' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4',
        'moon' => 'M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z',
        'auto' => 'M4 5h16v11H4zM8 20h8M12 16v4',
    ];
    $icon = fn (string $path, string $class = 'icon'): string => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="'.e($path).'"/></svg>';

    // States and municipalities where participating companies are based (real data only).
    $states = collect($places)->pluck('state')->unique()->values()->all();
    if ($value('uf') !== '' && ! in_array($value('uf'), $states, true)) {
        $states[] = $value('uf');
    }
    sort($states);
    $placesByState = collect($places)->groupBy('state')->sortKeys();

    $absolute = fn (?string $path): ?string => $path === null ? null : rtrim($home, '/').(str_starts_with($path, '/') ? $path : '/'.$path);
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $structured = [];

    if ($indexable && ! $searched) {
        $structured[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Portal Regional VetorSmart', 'url' => $home,
            'potentialAction' => ['@type' => 'SearchAction', 'target' => $home.'?q={q}', 'query-input' => 'required name=q']];
    }

    if ($indexable && $breadcrumbs !== [] && $results !== null) {
        $structured[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect($breadcrumbs)->values()->map(fn (array $item, int $index): array => array_filter([
            '@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url'] === null ? $canonical : $absolute($item['url']),
        ]))->all()];
        $structured[] = ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $friendlyHeading, 'url' => $canonical, 'description' => $description,
            'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => collect($results->items())->values()->map(fn ($listing, int $index): array => [
                '@type' => 'ListItem', 'position' => $index + 1, 'name' => $listing->business_name, 'url' => $siteBase($listing),
            ])->all()]];
    }

    $heading = match (true) {
        $notFound => 'Página não encontrada',
        $friendlyHeading !== null => $friendlyHeading,
        $searched => 'Buscar profissionais',
        default => 'Encontre profissionais na sua região',
    };
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script nonce="{{ Vite::cspNonce() }}">
        // Before painting: the theme chosen on this browser (light or dark), or the system preference (automatic).
        try { var theme = localStorage.getItem('portal-theme'); if (theme === 'light' || theme === 'dark') { document.documentElement.setAttribute('data-theme', theme); } } catch (error) {}
    </script>
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    @unless ($indexable)
        <meta name="robots" content="noindex, follow">
    @endunless
    <meta name="theme-color" content="#0F172A">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="VetorSmart">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $assets }}/images/vetor-smart-logo-transparent.png">
    <link rel="icon" href="{{ $assets }}/images/vetor-smart-favicon-32.png" sizes="32x32">
    @foreach ($structured as $data)
        <script type="application/ld+json">{!! json_encode($data, $jsonFlags) !!}</script>
    @endforeach
    <style>
        :root {
            --text: #0B1220; --muted: #475569; --border: #CBD5E1; --surface: #F1F5F9; --card: #FFFFFF; --page: #FFFFFF;
            --brand: #1D4ED8; --brand-hover: #1E40AF; --brand-soft: #EFF6FF; --brand-ink: #1E3A8A; --navy: #0F172A;
            --link: #1D4ED8; --danger: #B91C1C; --focus: #1D4ED8; --input: #64748B; --accent-icon: #1D4ED8; --success: #15803D;
            --band-link: #BFDBFE;
            color-scheme: light;
        }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --text: #E2E8F0; --muted: #A3B3C9; --border: #334155; --surface: #111A2E; --card: #16213A; --page: #0B1220;
                --brand: #2563EB; --brand-hover: #1D4ED8; --brand-soft: #1E293B; --brand-ink: #BFDBFE; --navy: #020617;
                --link: #93C5FD; --danger: #FCA5A5; --focus: #93C5FD; --input: #64748B; --accent-icon: #93C5FD; --success: #4ADE80;
                color-scheme: dark;
            }
        }
        :root[data-theme="dark"] {
            --text: #E2E8F0; --muted: #A3B3C9; --border: #334155; --surface: #111A2E; --card: #16213A; --page: #0B1220;
            --brand: #2563EB; --brand-hover: #1D4ED8; --brand-soft: #1E293B; --brand-ink: #BFDBFE; --navy: #020617;
            --link: #93C5FD; --danger: #FCA5A5; --focus: #93C5FD; --input: #64748B; --accent-icon: #93C5FD; --success: #4ADE80;
            color-scheme: dark;
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 16px; color: var(--text); background: var(--page); line-height: 1.55; }
        a { color: var(--link); }
        :focus-visible { outline: 3px solid var(--focus); outline-offset: 2px; }
        h1, h2, h3 { line-height: 1.25; }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 0 16px; }
        .icon { width: 20px; height: 20px; flex: 0 0 auto; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .skip { position: absolute; left: -9999px; top: 8px; }
        .skip:focus { left: 16px; background: var(--card); color: var(--text); padding: 10px 14px; border-radius: 8px; z-index: 20; }

        /* Header */
        header.top { background: var(--card); border-bottom: 1px solid var(--border); }
        header.top .wrap { display: flex; align-items: center; justify-content: space-between; gap: 8px 12px; min-height: 64px; flex-wrap: wrap; padding-top: 6px; padding-bottom: 6px; }
        .brand { display: inline-flex; align-items: center; gap: 10px; color: var(--text); text-decoration: none; }
        .brand img { height: 34px; width: auto; }
        .brand strong { display: block; font-size: 1rem; }
        .brand small { display: block; color: var(--muted); font-size: .78rem; }
        .top-actions { display: flex; align-items: center; gap: 4px 12px; flex-wrap: wrap; }
        header.top nav { display: flex; gap: 4px 12px; }
        header.top nav a { color: var(--text); text-decoration: none; font-weight: 500; padding: 10px 4px; }
        header.top nav a:hover { text-decoration: underline; }
        .theme { display: inline-flex; border: 1px solid var(--border); border-radius: 12px; padding: 2px; background: var(--surface); }
        .theme button { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-width: 44px; min-height: 40px; padding: 6px 10px; border: 0; border-radius: 10px; background: transparent; color: var(--text); font: inherit; font-size: .88rem; cursor: pointer; }
        .theme button[aria-pressed="true"] { background: var(--card); box-shadow: 0 1px 3px rgba(2, 6, 23, .2); font-weight: 600; }
        .theme .label { display: none; }
        @media (min-width: 900px) { .theme .label { display: inline; } }

        /* Search */
        .search-band { background: var(--navy); color: #F8FAFC; padding: 24px 0; }
        .search-band h1 { margin: 0 0 6px; font-size: clamp(1.45rem, 4.5vw, 2.3rem); }
        .search-band p.lead { margin: 0 0 18px; color: #CBD5E1; max-width: 680px; }
        nav.crumbs ol { list-style: none; margin: 0 0 10px; padding: 0; display: flex; flex-wrap: wrap; gap: 4px; font-size: .92rem; color: #CBD5E1; }
        nav.crumbs li + li::before { content: "›"; margin: 0 6px; color: #94A3B8; }
        nav.crumbs a { color: var(--band-link); }
        nav.crumbs [aria-current="page"] { color: #F8FAFC; font-weight: 600; }
        form.search { background: var(--card); color: var(--text); border-radius: 16px; padding: 14px; display: grid; gap: 12px; box-shadow: 0 8px 24px rgba(2, 6, 23, .25); }
        .row { display: grid; gap: 12px; }
        @media (min-width: 760px) { .row.main { grid-template-columns: minmax(0, 1.7fr) 110px minmax(0, 1.3fr) auto; align-items: start; } .row.main > .submit { padding-top: calc(.9rem * 1.55 + 6px); } .row.more { grid-template-columns: 1fr 1fr; } }
        .field label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: 6px; }
        .field .hint { margin: 6px 0 0; color: var(--muted); font-size: .84rem; }
        .control { position: relative; }
        .control .icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
        .control input { padding-left: 42px; }
        input, select { width: 100%; font: inherit; font-size: 16px; min-height: 48px; padding: 10px 12px; border: 1px solid var(--input); border-radius: 12px; background: var(--page); color: var(--text); }
        select { padding-right: 28px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; padding: 10px 18px; border-radius: 12px; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; border: 1px solid transparent; }
        .btn.primary { background: var(--brand); color: #fff; }
        .btn.primary:hover { background: var(--brand-hover); }
        .btn.secondary { background: var(--card); color: var(--text); border-color: var(--border); }
        .btn.secondary:hover { background: var(--surface); }
        .btn[disabled] { opacity: .8; cursor: progress; }
        details.more summary { cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: var(--link); min-height: 44px; list-style: none; }
        details.more summary::-webkit-details-marker { display: none; }
        details.more[open] summary { margin-bottom: 8px; }
        .field-error { color: var(--danger); font-size: .88rem; margin: 6px 0 0; }

        /* Active filters */
        .active-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 16px 0 0; padding: 0; list-style: none; }
        .active-filters a.chip { display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 6px 12px; border-radius: 999px; background: var(--brand-soft); color: var(--brand-ink); border: 1px solid var(--border); text-decoration: none; font-size: .92rem; font-weight: 500; }
        .active-filters a.chip .icon { width: 16px; height: 16px; }
        .active-filters a.clear { color: var(--band-link); font-weight: 600; padding: 8px 4px; }

        /* Sections */
        main.wrap { padding-top: 28px; padding-bottom: 48px; }
        section.block { margin-top: 32px; }
        section.block:first-child { margin-top: 0; }
        .section-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 14px; }
        .section-head h2 { margin: 0; font-size: 1.3rem; }
        .section-head p { margin: 0; color: var(--muted); }

        /* Categories and places */
        ul.categories { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(min(100%, 180px), 1fr)); }
        ul.categories span { min-width: 0; overflow-wrap: anywhere; }
        ul.categories a { display: flex; align-items: center; gap: 10px; min-height: 56px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 14px; background: var(--card); color: var(--text); text-decoration: none; font-weight: 600; font-size: .95rem; }
        ul.categories a:hover { border-color: var(--brand); }
        ul.categories a[aria-current="true"] { border-color: var(--brand); background: var(--brand-soft); color: var(--brand-ink); }
        ul.categories .icon { width: 24px; height: 24px; color: var(--accent-icon); }
        ul.pills { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 8px; }
        ul.pills a { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; padding: 8px 14px; border: 1px solid var(--border); border-radius: 999px; background: var(--card); color: var(--text); text-decoration: none; font-weight: 500; }
        ul.pills a:hover { border-color: var(--brand); }
        ul.pills .icon { width: 18px; height: 18px; color: var(--accent-icon); }
        .regions { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); }
        .regions section { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 16px; }
        .regions h3 { margin: 0 0 10px; font-size: 1.05rem; }
        .regions h3 a { color: var(--text); }

        /* Layout of lists: region links beside the results on wide screens */
        .listing { display: grid; gap: 24px; }
        @media (min-width: 1080px) { .listing.with-aside { grid-template-columns: minmax(0, 1fr) 280px; align-items: start; } .listing aside { position: sticky; top: 16px; } }
        aside.related { display: grid; gap: 18px; }
        aside.related section { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 16px; }
        aside.related h2 { margin: 0 0 10px; font-size: 1.05rem; }

        /* Cards */
        .summary { margin: 0 0 14px; color: var(--muted); }
        ul.results { list-style: none; margin: 0; padding: 0; display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 18px; display: flex; flex-direction: column; gap: 12px; height: 100%; }
        .card .head { display: flex; gap: 14px; align-items: flex-start; }
        .avatar { width: 64px; height: 64px; flex: 0 0 64px; border-radius: 14px; border: 1px solid var(--border); background: #fff; object-fit: contain; }
        .avatar.placeholder { display: grid; place-items: center; color: #1E293B; }
        .avatar.placeholder svg { width: 30px; height: 30px; }
        .card h3 { margin: 0 0 4px; font-size: 1.1rem; overflow-wrap: anywhere; }
        .card .activity { margin: 0; color: var(--muted); font-size: .92rem; display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: center; }
        .card .activity span { display: inline-flex; align-items: center; gap: 4px; }
        .card .activity .icon { width: 16px; height: 16px; }
        .card .about { margin: 0; color: var(--muted); }
        .card .match { margin: 0; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: var(--text); }
        .card .match .icon { width: 18px; height: 18px; color: var(--success); }
        .card .served { margin: 0; font-size: .9rem; padding: 6px 10px; border-radius: 10px; background: var(--surface); border: 1px solid var(--border); }
        .card .region { margin: 0; font-size: .92rem; }
        .card ul.services { margin: 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 6px; }
        .card ul.services li { font-size: .86rem; padding: 4px 10px; border-radius: 999px; background: var(--surface); border: 1px solid var(--border); }
        .card .actions { margin-top: auto; display: grid; gap: 8px; grid-template-columns: 1fr; padding-top: 4px; }
        /* Side by side only where a card is wide (a single column on large phones and small tablets). */
        @media (min-width: 480px) and (max-width: 699px) { .card .actions { grid-template-columns: 1fr 1fr; } }
        .card .actions .btn { white-space: nowrap; }

        /* States */
        .notice { border: 1px dashed var(--border); border-radius: 16px; padding: 20px; background: var(--card); }
        .notice h2 { margin: 0 0 6px; font-size: 1.2rem; }
        .notice p { margin: 0 0 10px; color: var(--muted); }
        .notice ul { margin: 8px 0 0; padding-left: 20px; }
        .notice li { margin: 6px 0; }
        .notice li a { font-weight: 600; }
        nav.pages { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: center; margin-top: 28px; }
        nav.pages span { color: var(--muted); padding: 8px; }

        /* How it works */
        ol.steps { list-style: none; margin: 0; padding: 0; display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); counter-reset: step; }
        ol.steps li { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 16px; }
        ol.steps li::before { counter-increment: step; content: counter(step); display: inline-grid; place-items: center; width: 32px; height: 32px; border-radius: 999px; background: var(--brand); color: #fff; font-weight: 700; margin-bottom: 8px; }
        ol.steps h3 { margin: 0 0 4px; font-size: 1.02rem; }
        ol.steps p { margin: 0; color: var(--muted); }
        .trust { margin-top: 16px; color: var(--muted); font-size: .95rem; }

        /* Footer */
        footer.site { background: var(--surface); border-top: 1px solid var(--border); padding: 28px 0; color: var(--muted); font-size: .92rem; }
        footer.site .cols { display: grid; gap: 18px; grid-template-columns: 1fr; }
        @media (min-width: 720px) { footer.site .cols { grid-template-columns: 2fr 1fr; } }
        footer.site strong { color: var(--text); }
        footer.site ul { list-style: none; margin: 0; padding: 0; }
        footer.site li { margin: 6px 0; }
        footer.site p { margin: 6px 0; }
    </style>
</head>
<body>
<a class="skip" href="#conteudo">Ir para o conteúdo</a>

<header class="top">
    <div class="wrap">
        <a class="brand" href="/">
            <img src="{{ $assets }}/images/vetor-smart-logo-reduced.png" alt="" width="47" height="34">
            <span><strong>Portal Regional</strong><small>VetorSmart</small></span>
        </a>
        <div class="top-actions">
            <nav aria-label="Navegação do portal">
                @if ($places !== [])<a href="/#regioes">Regiões</a>@endif
                <a href="/#como-funciona">Como funciona</a>
            </nav>
            <div class="theme" role="group" aria-label="Tema" data-theme-switch hidden>
                <button type="button" data-theme-option="light" aria-pressed="false">{!! $icon($icons['sun']) !!}<span class="label">Claro</span><span class="sr-only">Tema claro</span></button>
                <button type="button" data-theme-option="dark" aria-pressed="false">{!! $icon($icons['moon']) !!}<span class="label">Escuro</span><span class="sr-only">Tema escuro</span></button>
                <button type="button" data-theme-option="auto" aria-pressed="false">{!! $icon($icons['auto']) !!}<span class="label">Automático</span><span class="sr-only">Tema automático, conforme o sistema</span></button>
            </div>
        </div>
    </div>
</header>

<div class="search-band">
    <div class="wrap">
        @if ($breadcrumbs !== [])
            <nav class="crumbs" aria-label="Você está em">
                <ol>
                    @foreach ($breadcrumbs as $crumb)
                        <li>@if ($crumb['url'] !== null)<a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>@else<span aria-current="page">{{ $crumb['name'] }}</span>@endif</li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <h1 @if ($searched || $notFound) style="font-size:clamp(1.35rem, 4vw, 1.9rem)" @endif>{{ $heading }}</h1>
        @if (! $searched && ! $notFound)
            <p class="lead">Busque pelo serviço que você precisa, conheça cada empresa e peça atendimento diretamente a ela, sem custo.</p>
        @elseif ($friendlyHeading !== null)
            <p class="lead">Empresas participantes do Portal Regional. Refine pelo serviço que você precisa.</p>
        @endif

        <form class="search" method="get" action="/" role="search" aria-label="Encontrar profissionais" data-search-form novalidate>
            <div class="row main">
                <div class="field">
                    <label for="q">Qual serviço você precisa?</label>
                    <div class="control">
                        {!! $icon($icons['search']) !!}
                        <input id="q" name="q" type="search" value="{{ $value('q') }}" maxlength="80" placeholder="Ex.: instalação de ar-condicionado" autocomplete="off" enterkeyhint="search" @if ($searchErrors->has('q')) aria-invalid="true" aria-describedby="q-error" @endif>
                    </div>
                    @if ($searchErrors->has('q'))<p class="field-error" id="q-error">{{ $searchErrors->first('q') }}</p>@endif
                </div>
                <div class="field">
                    <label for="uf">UF</label>
                    <select id="uf" name="uf" data-state-select @if ($searchErrors->has('uf')) aria-invalid="true" aria-describedby="uf-error" @endif>
                        <option value="">Todas</option>
                        @foreach ($states as $state)
                            <option value="{{ $state }}" @selected($value('uf') === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                    @if ($searchErrors->has('uf'))<p class="field-error" id="uf-error">{{ $searchErrors->first('uf') }}</p>@endif
                </div>
                <div class="field">
                    <label for="cidade">Município</label>
                    <div class="control">
                        {!! $icon($icons['pin']) !!}
                        <input id="cidade" name="cidade" type="text" value="{{ $value('cidade') }}" maxlength="80" list="cidades" placeholder="Ex.: Campinas" autocomplete="address-level2" aria-describedby="cidade-hint{{ $searchErrors->has('cidade') ? ' cidade-error' : '' }}" @if ($searchErrors->has('cidade')) aria-invalid="true" @endif>
                    </div>
                    <datalist id="cidades" data-city-list>
                        @foreach ($places as $item)
                            <option value="{{ $item['city'] }}" data-uf="{{ $item['state'] }}">{{ $item['city'] }} - {{ $item['state'] }}</option>
                        @endforeach
                    </datalist>
                    <p class="hint" id="cidade-hint">Sugestões de municípios com empresas participantes.</p>
                    @if ($searchErrors->has('cidade'))<p class="field-error" id="cidade-error">{{ $searchErrors->first('cidade') }}</p>@endif
                </div>
                <div class="submit"><button class="btn primary" type="submit" data-search-button style="width:100%">{!! $icon($icons['search']) !!}<span data-search-label>Encontrar profissionais</span></button></div>
            </div>
            <details class="more" @if ($moreOpen) open @endif>
                <summary>{!! $icon($icons['filter']) !!} Mais filtros</summary>
                <div class="row more">
                    <div class="field">
                        <label for="categoria">Categoria</label>
                        <select id="categoria" name="categoria" @if ($searchErrors->has('categoria')) aria-invalid="true" aria-describedby="categoria-error" @endif>
                            <option value="">Todas as categorias</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category['slug'] }}" @selected($value('categoria') === $category['slug'])>{{ $category['name'] }}</option>
                            @endforeach
                        </select>
                        @if ($searchErrors->has('categoria'))<p class="field-error" id="categoria-error">{{ $searchErrors->first('categoria') }}</p>@endif
                    </div>
                    <div class="field">
                        <label for="nome">Nome da empresa</label>
                        <input id="nome" name="nome" type="text" value="{{ $value('nome') }}" maxlength="80" autocomplete="off" @if ($searchErrors->has('nome')) aria-invalid="true" aria-describedby="nome-error" @endif>
                        @if ($searchErrors->has('nome'))<p class="field-error" id="nome-error">{{ $searchErrors->first('nome') }}</p>@endif
                    </div>
                </div>
            </details>
        </form>

        @if ($activeFilters !== [])
            <ul class="active-filters" aria-label="Filtros ativos">
                @foreach ($activeFilters as $filter)
                    <li><a class="chip" href="{{ $filter['remove'] }}" aria-label="Remover filtro {{ $filter['label'] }}">{{ $filter['label'] }} {!! $icon($icons['close']) !!}</a></li>
                @endforeach
                <li><a class="clear" href="/">Limpar filtros</a></li>
            </ul>
        @endif
    </div>
</div>

<main class="wrap" id="conteudo" tabindex="-1">
    <div class="sr-only" aria-live="polite" data-search-status></div>

    @if ($notFound)
        <div class="notice" role="status">
            <h2>Não há empresas participantes neste endereço</h2>
            <p>A categoria ou a região procurada não existe no portal ou ainda não tem empresas participantes.</p>
            <ul>
                <li><a href="/?pagina=1">Ver todos os profissionais do portal</a></li>
                @if ($places !== [])<li><a href="/#regioes">Escolher uma região</a></li>@endif
            </ul>
        </div>
    @elseif ($searchErrors->has('pagina'))
        <div class="notice" role="alert"><h2>Refine a busca</h2><p>{{ $searchErrors->first('pagina') }}</p></div>
    @elseif ($searchErrors->isNotEmpty())
        <div class="notice" role="alert">
            <h2>Revise a busca</h2>
            <p>Corrija o campo indicado acima e tente novamente.</p>
        </div>
    @endif

    @if ($results !== null)
        @php($hasAside = $regionCategories !== [] || $regionCities !== [])
        <div class="listing @if ($hasAside) with-aside @endif">
            <section class="block" aria-labelledby="resultados-titulo">
                <div class="section-head">
                    <h2 id="resultados-titulo">{{ $friendlyHeading !== null ? 'Profissionais' : 'Resultados da busca' }}</h2>
                </div>

                @if ($results->total() === 0)
                    <div class="notice" role="status">
                        <h2>Nenhum profissional encontrado</h2>
                        <p>Ainda não há empresas participantes com esses critérios. Experimente ampliar a busca:</p>
                        <ul>
                            @foreach ($suggestions as $suggestion)
                                <li><a href="{{ $suggestion['url'] }}">{{ $suggestion['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="summary" role="status">
                        {{ $results->total() }} {{ $results->total() === 1 ? 'profissional encontrado' : 'profissionais encontrados' }}@if ($results->lastPage() > 1) · página {{ $results->currentPage() }} de {{ $results->lastPage() }}@endif
                    </p>
                    @include('portal.partials.cards', ['listings' => $results, 'tokens' => $tokens])

                    @if ($results->lastPage() > 1)
                        <nav class="pages" aria-label="Páginas de resultados">
                            @if ($results->previousPageUrl())
                                <a class="btn secondary" href="{{ $results->previousPageUrl() }}" rel="prev">← Anterior</a>
                            @endif
                            <span>Página {{ $results->currentPage() }} de {{ $results->lastPage() }}</span>
                            @if ($results->hasMorePages() && $results->currentPage() < PortalSearch::MAX_PAGE)
                                <a class="btn secondary" href="{{ $results->nextPageUrl() }}" rel="next">Próxima →</a>
                            @endif
                        </nav>
                    @endif
                @endif
            </section>

            @if ($hasAside)
                <aside class="related" aria-label="Refinar por região e categoria">
                    @if ($regionCities !== [])
                        <section aria-labelledby="municipios-titulo">
                            <h2 id="municipios-titulo">Municípios em {{ $place['state_name'] ?? '' }}</h2>
                            <ul class="pills">
                                @foreach ($regionCities as $item)
                                    <li><a href="/regioes/{{ strtolower($item['state']) }}/{{ $item['slug'] }}{{ $keepText }}">{!! $icon($icons['pin']) !!}{{ $item['city'] }}</a></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    @if ($regionCategories !== [])
                        <section aria-labelledby="categorias-regiao-titulo">
                            <h2 id="categorias-regiao-titulo">Categorias em {{ $place['city'] ?? $place['state_name'] ?? '' }}</h2>
                            <ul class="pills">
                                @foreach ($regionCategories as $category)
                                    <li><a href="{{ $category['url'] }}" @if ($value('categoria') === $category['slug']) aria-current="true" @endif>{!! $icon($category['icon']) !!}{{ $category['name'] }}</a></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </aside>
            @endif
        </div>
    @endif

    @if ($featured !== null)
        <section class="block" aria-labelledby="destaque-titulo">
            <div class="section-head">
                <h2 id="destaque-titulo">Profissionais no portal</h2>
                @if ($featured->total() > 0)
                    <p>A ordem muda a cada dia. Não há posições pagas.</p>
                @endif
            </div>
            @if ($featured->total() === 0)
                <div class="notice">
                    <h2 style="font-size:1.05rem">As primeiras empresas estão chegando</h2>
                    <p>Ainda não há empresas participantes no portal. Volte em breve.</p>
                </div>
            @else
                @include('portal.partials.cards', ['listings' => $featured, 'tokens' => []])
                @if ($featured->total() > $featured->count())
                    <p style="margin-top:16px"><a class="btn secondary" href="/?pagina=1">Ver todos os profissionais {!! $icon($icons['arrow']) !!}</a></p>
                @endif
            @endif
        </section>
    @endif

    @if ($categories !== [] && $regionCategories === [])
        <section class="block" id="categorias" aria-labelledby="categorias-titulo">
            <div class="section-head">
                <h2 id="categorias-titulo">Categorias de serviços</h2>
                <p>Somente categorias com empresas participantes.</p>
            </div>
            <ul class="categories">
                @foreach ($categories as $category)
                    <li>
                        <a href="/categorias/{{ $category['slug'] }}" @if ($value('categoria') === $category['slug']) aria-current="true" @endif>
                            {!! $icon($category['icon']) !!}<span>{{ $category['name'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($places !== [] && ($featured !== null || $notFound))
        <section class="block" id="regioes" aria-labelledby="regioes-titulo">
            <div class="section-head">
                <h2 id="regioes-titulo">Explorar por região</h2>
                <p>Estados e municípios com empresas participantes.</p>
            </div>
            <div class="regions">
                @foreach ($placesByState as $state => $items)
                    <section aria-labelledby="estado-{{ strtolower($state) }}">
                        <h3 id="estado-{{ strtolower($state) }}"><a href="/regioes/{{ strtolower($state) }}">{{ PortalText::STATE_NAMES[$state] ?? $state }}</a></h3>
                        <ul class="pills">
                            @foreach ($items->take(24) as $item)
                                <li><a href="/regioes/{{ strtolower($state) }}/{{ $item['slug'] }}">{!! $icon($icons['pin']) !!}{{ $item['city'] }}</a></li>
                            @endforeach
                            @if ($items->count() > 24)
                                <li><a href="/regioes/{{ strtolower($state) }}">Ver todos os municípios</a></li>
                            @endif
                        </ul>
                    </section>
                @endforeach
            </div>
        </section>
    @endif

    @if (! $searched || $notFound)
        <section class="block" id="como-funciona" aria-labelledby="como-titulo">
            <div class="section-head"><h2 id="como-titulo">Como funciona</h2></div>
            <ol class="steps">
                <li><h3>Busque o serviço</h3><p>Digite o que você precisa e escolha sua região, ou navegue pelas categorias.</p></li>
                <li><h3>Conheça a empresa</h3><p>Veja os serviços, a região atendida e o site de cada profissional.</p></li>
                <li><h3>Solicite atendimento</h3><p>Envie seu pedido direto à empresa, que entra em contato com você.</p></li>
            </ol>
        </section>
    @endif
    <p class="trust" id="criterios">Aparecem apenas empresas com site publicado que escolheram participar. A ordem considera o serviço buscado, a região atendida e as informações públicas de cada empresa; empresas equivalentes alternam de posição a cada dia. Nenhuma empresa paga por posição. O portal não intermedeia pagamentos: o orçamento e o atendimento são combinados diretamente com a empresa.</p>
</main>

<footer class="site">
    <div class="wrap cols">
        <div>
            <p><strong>Portal Regional VetorSmart</strong></p>
            <p>Diretório gratuito de empresas de serviços que usam o VetorSmart. As informações exibidas são publicadas pelas próprias empresas.</p>
        </div>
        <div>
            <ul>
                <li><a href="/#como-funciona">Como funciona</a></li>
                <li><a href="/#categorias">Categorias</a></li>
                @if ($places !== [])<li><a href="/#regioes">Regiões</a></li>@endif
            </ul>
        </div>
    </div>
</footer>

<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        // Theme: light, dark or automatic (system preference), remembered on this browser only.
        var root = document.documentElement;
        var themeSwitch = document.querySelector('[data-theme-switch]');
        var readTheme = function () { try { return localStorage.getItem('portal-theme') || 'auto'; } catch (error) { return 'auto'; } };
        var applyTheme = function (choice) {
            if (choice === 'light' || choice === 'dark') { root.setAttribute('data-theme', choice); } else { root.removeAttribute('data-theme'); }
            Array.prototype.forEach.call(document.querySelectorAll('[data-theme-option]'), function (button) {
                button.setAttribute('aria-pressed', button.getAttribute('data-theme-option') === choice ? 'true' : 'false');
            });
        };
        if (themeSwitch) {
            themeSwitch.hidden = false;
            applyTheme(readTheme());
            themeSwitch.addEventListener('click', function (event) {
                var button = event.target.closest('[data-theme-option]');
                if (!button) { return; }
                var choice = button.getAttribute('data-theme-option');
                try { if (choice === 'auto') { localStorage.removeItem('portal-theme'); } else { localStorage.setItem('portal-theme', choice); } } catch (error) {}
                applyTheme(choice);
            });
        }

        // Municipality suggestions follow the chosen state.
        var stateSelect = document.querySelector('[data-state-select]');
        var cityList = document.querySelector('[data-city-list]');
        if (stateSelect && cityList) {
            var allCities = Array.prototype.slice.call(cityList.querySelectorAll('option'));
            var filterCities = function () {
                cityList.innerHTML = '';
                allCities.forEach(function (option) {
                    if (stateSelect.value === '' || option.getAttribute('data-uf') === stateSelect.value) { cityList.appendChild(option); }
                });
            };
            stateSelect.addEventListener('change', filterCities);
            filterCities();
        }

        // Search: empty fields stay out of the address, and the button shows the search is running.
        var form = document.querySelector('[data-search-form]');
        var button = document.querySelector('[data-search-button]');
        var label = document.querySelector('[data-search-label]');
        var status = document.querySelector('[data-search-status]');
        if (!form || !button || !label) { return; }
        form.addEventListener('submit', function () {
            Array.prototype.forEach.call(form.querySelectorAll('input[name], select[name]'), function (field) {
                if (field.value === '') { field.disabled = true; }
            });
            button.disabled = true;
            label.textContent = 'Buscando…';
            form.setAttribute('aria-busy', 'true');
            if (status) { status.textContent = 'Buscando profissionais…'; }
        });
        window.addEventListener('pageshow', function () {
            Array.prototype.forEach.call(form.querySelectorAll('[disabled]'), function (field) { field.disabled = false; });
            label.textContent = 'Encontrar profissionais';
            form.removeAttribute('aria-busy');
        });
    })();
</script>
</body>
</html>
