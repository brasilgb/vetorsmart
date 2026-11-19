@php
    $assets = rtrim((string) config('platform.public_url'), '/');
    $title = 'Portal Regional VetorSmart · Encontre prestadores de serviço na sua região';
    $description = 'Encontre prestadores de serviço por tipo de serviço e cidade, veja o site de cada um e solicite atendimento diretamente à empresa.';
    $value = fn (string $field): string => (string) ($filters[$field] ?? '');
    $categoryLink = fn (?string $slug): string => '/?'.http_build_query(array_filter([...$filters, 'categoria' => $slug], fn ($item) => $item !== null && $item !== ''));
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $searched ? 'Resultados · ' : '' }}{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    @if ($searched)
        <meta name="robots" content="noindex, follow">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="VetorSmart">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <link rel="icon" href="{{ $assets }}/images/vetor-smart-favicon-32.png" sizes="32x32">
    <style>
        :root {
            --vs-text: #0B1220; --vs-muted: #475569; --vs-border: #CBD5E1; --vs-surface: #F8FAFC; --vs-link: #1D4ED8;
            --vs-blue: #1D4ED8; --vs-blue-hover: #1E40AF; --vs-navy: #0F172A; --vs-danger: #B91C1C; --vs-page: #FFFFFF; --vs-card: #FFFFFF; --vs-chip: #EFF6FF;
            color-scheme: light dark;
        }
        @media (prefers-color-scheme: dark) {
            :root { --vs-text: #E2E8F0; --vs-muted: #A3B3C9; --vs-border: #334155; --vs-surface: #16213A; --vs-link: #93C5FD; --vs-blue: #2563EB; --vs-blue-hover: #1D4ED8; --vs-danger: #FCA5A5; --vs-page: #0B1220; --vs-card: #111A2E; --vs-chip: #1E293B; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--vs-text); background: var(--vs-page); line-height: 1.55; }
        a { color: var(--vs-link); }
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible { outline: 3px solid var(--vs-link); outline-offset: 2px; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 0 16px; }
        .skip { position: absolute; left: -9999px; }
        .skip:focus { left: 16px; top: 8px; background: var(--vs-card); padding: 8px 12px; z-index: 10; }
        header.top { border-bottom: 1px solid var(--vs-border); }
        header.top .wrap { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding-top: 12px; padding-bottom: 12px; }
        header.top .brand { display: inline-flex; align-items: center; gap: 10px; color: var(--vs-text); text-decoration: none; font-weight: 700; }
        header.top .brand img { height: 36px; width: auto; }
        .hero { background: var(--vs-navy); color: #F8FAFC; padding: 40px 0 32px; }
        .hero h1 { margin: 0 0 8px; font-size: clamp(1.6rem, 4vw, 2.4rem); line-height: 1.2; }
        .hero p { margin: 0 0 24px; color: #CBD5E1; max-width: 720px; }
        form.search { display: grid; gap: 12px; grid-template-columns: 1fr; background: var(--vs-card); color: var(--vs-text); border-radius: 14px; padding: 16px; }
        @media (min-width: 760px) { form.search { grid-template-columns: 2fr 1.4fr 0.7fr 1.2fr auto; align-items: end; } }
        form.search label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: 4px; }
        form.search input, form.search select { width: 100%; font: inherit; padding: 11px 12px; border: 1px solid #94A3B8; border-radius: 10px; background: var(--vs-page); color: var(--vs-text); min-height: 46px; }
        form.search button { font: inherit; font-weight: 700; background: var(--vs-blue); color: #fff; border: 0; border-radius: 10px; padding: 12px 22px; min-height: 46px; cursor: pointer; }
        form.search button:hover { background: var(--vs-blue-hover); }
        form.search button[disabled] { opacity: .75; cursor: progress; }
        .field-error, .hero form.search .field-error { color: var(--vs-danger); font-size: .85rem; margin: 4px 0 0; }
        .chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 24px 0 8px; padding: 0; list-style: none; }
        .chips a { display: inline-block; padding: 8px 14px; border-radius: 999px; border: 1px solid var(--vs-border); background: var(--vs-chip); color: var(--vs-text); text-decoration: none; font-size: .92rem; }
        .chips a[aria-current="true"] { background: var(--vs-blue); border-color: var(--vs-blue); color: #fff; font-weight: 600; }
        main { padding-bottom: 48px; }
        .summary { margin: 16px 0; color: var(--vs-muted); }
        .results { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); list-style: none; padding: 0; margin: 0; }
        .card { background: var(--vs-card); border: 1px solid var(--vs-border); border-radius: 14px; padding: 18px; display: flex; flex-direction: column; gap: 10px; height: 100%; }
        .card header { display: flex; gap: 12px; align-items: center; }
        .card .logo { width: 56px; height: 56px; flex: 0 0 56px; border-radius: 10px; border: 1px solid var(--vs-border); background: #fff; object-fit: contain; }
        .card .initial { width: 56px; height: 56px; flex: 0 0 56px; border-radius: 10px; background: var(--vs-chip); color: var(--vs-text); display: grid; place-items: center; font-weight: 700; font-size: 1.3rem; }
        .card h3 { margin: 0; font-size: 1.08rem; line-height: 1.3; }
        .card .meta { margin: 2px 0 0; color: var(--vs-muted); font-size: .9rem; }
        .card .about { margin: 0; color: var(--vs-muted); font-size: .95rem; }
        .card ul.services { margin: 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 6px; }
        .card ul.services li { font-size: .85rem; padding: 3px 10px; border-radius: 999px; background: var(--vs-surface); border: 1px solid var(--vs-border); }
        .card .region { margin: 0; font-size: .9rem; }
        .card .actions { margin-top: auto; display: flex; flex-wrap: wrap; gap: 8px; padding-top: 6px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 10px 16px; border-radius: 10px; font-weight: 600; text-decoration: none; }
        .btn.primary { background: var(--vs-blue); color: #fff; }
        .btn.secondary { border: 1px solid var(--vs-border); color: var(--vs-text); }
        .empty { border: 1px dashed var(--vs-border); border-radius: 14px; padding: 24px; }
        .empty h2 { margin-top: 0; font-size: 1.2rem; }
        nav.pages { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: center; margin-top: 28px; }
        nav.pages a, nav.pages span { min-height: 44px; display: inline-flex; align-items: center; padding: 8px 14px; border-radius: 10px; }
        nav.pages a { border: 1px solid var(--vs-border); color: var(--vs-text); text-decoration: none; }
        .about-portal { border-top: 1px solid var(--vs-border); margin-top: 40px; padding-top: 24px; color: var(--vs-muted); font-size: .92rem; }
        footer { border-top: 1px solid var(--vs-border); padding: 20px 0; color: var(--vs-muted); font-size: .88rem; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        @media (max-width: 640px) { .card .actions .btn { flex: 1 1 100%; } }
    </style>
</head>
<body>
<a class="skip" href="#resultados">Ir para os resultados</a>
<header class="top">
    <div class="wrap">
        <a class="brand" href="/">
            <img src="{{ $assets }}/images/vetor-smart-logo-reduced.png" alt="" width="50" height="36">
            <span>Portal Regional VetorSmart</span>
        </a>
    </div>
</header>

<section class="hero" aria-labelledby="titulo">
    <div class="wrap">
        <h1 id="titulo">Encontre prestadores de serviço na sua região</h1>
        <p>Busque pelo serviço e pela cidade, conheça o site de cada empresa e solicite atendimento diretamente a ela.</p>

        <form class="search" method="get" action="/" role="search" aria-label="Buscar prestadores" data-search-form>
            <div>
                <label for="q">Qual serviço você precisa?</label>
                <input id="q" name="q" type="search" value="{{ $value('q') }}" maxlength="80" placeholder="Ex.: instalação de ar-condicionado" autocomplete="off" @if ($searchErrors->has('q')) aria-invalid="true" aria-describedby="q-error" @endif>
                @if ($searchErrors->has('q'))<p class="field-error" id="q-error">{{ $searchErrors->first('q') }}</p>@endif
            </div>
            <div>
                <label for="cidade">Município ou região</label>
                <input id="cidade" name="cidade" type="text" value="{{ $value('cidade') }}" maxlength="80" list="cidades" placeholder="Ex.: Campinas" autocomplete="address-level2" @if ($searchErrors->has('cidade')) aria-invalid="true" aria-describedby="cidade-error" @endif>
                <datalist id="cidades">
                    @foreach ($cities as $city)
                        <option value="{{ \Illuminate\Support\Str::beforeLast($city, ' - ') }}">{{ $city }}</option>
                    @endforeach
                </datalist>
                @if ($searchErrors->has('cidade'))<p class="field-error" id="cidade-error">{{ $searchErrors->first('cidade') }}</p>@endif
            </div>
            <div>
                <label for="uf">UF</label>
                <select id="uf" name="uf" @if ($searchErrors->has('uf')) aria-invalid="true" aria-describedby="uf-error" @endif>
                    <option value="">Todas</option>
                    @foreach (\App\Services\Portal\PortalText::STATES as $state)
                        <option value="{{ $state }}" @selected($value('uf') === $state)>{{ $state }}</option>
                    @endforeach
                </select>
                @if ($searchErrors->has('uf'))<p class="field-error" id="uf-error">{{ $searchErrors->first('uf') }}</p>@endif
            </div>
            <div>
                <label for="nome">Nome da empresa <span style="font-weight:400">(opcional)</span></label>
                <input id="nome" name="nome" type="text" value="{{ $value('nome') }}" maxlength="80" autocomplete="off" @if ($searchErrors->has('nome')) aria-invalid="true" aria-describedby="nome-error" @endif>
                @if ($searchErrors->has('nome'))<p class="field-error" id="nome-error">{{ $searchErrors->first('nome') }}</p>@endif
            </div>
            @if ($value('categoria') !== '')
                <input type="hidden" name="categoria" value="{{ $value('categoria') }}">
            @endif
            <div><button type="submit" data-search-button>Buscar</button></div>
        </form>
    </div>
</section>

<main class="wrap" id="resultados" tabindex="-1">
    <nav aria-label="Categorias de serviço">
        <ul class="chips">
            <li><a href="{{ $categoryLink(null) }}" @if ($value('categoria') === '') aria-current="true" @endif>Todas as categorias</a></li>
            @foreach ($categories as $category)
                <li><a href="{{ $categoryLink($category['slug']) }}" @if ($value('categoria') === $category['slug']) aria-current="true" @endif>{{ $category['name'] }}</a></li>
            @endforeach
        </ul>
    </nav>
    @if ($searchErrors->has('categoria') || $searchErrors->has('pagina'))
        <p class="field-error" role="alert">{{ $searchErrors->first('categoria') ?: $searchErrors->first('pagina') }}</p>
    @endif

    <div aria-live="polite" data-search-status class="sr-only"></div>

    @if (! $searched)
        <div class="empty">
            <h2>Como funciona</h2>
            <p style="margin:0">Digite o serviço que você precisa e a sua cidade, ou escolha uma categoria. Você verá as empresas que atendem a região, poderá abrir o site de cada uma e enviar sua solicitação diretamente a ela, sem custo.</p>
        </div>
    @elseif ($results === null)
        <div class="empty" role="alert">
            <h2>Revise a busca</h2>
            <p style="margin:0">Corrija os campos indicados e tente novamente.</p>
        </div>
    @elseif ($results->total() === 0)
        <div class="empty" role="status">
            <h2>Nenhum prestador encontrado</h2>
            <p style="margin:0">Tente um termo mais geral (por exemplo, "elétrica" em vez de um serviço específico), outra cidade da região ou outra categoria.</p>
        </div>
    @else
        <h2 class="summary" role="status" style="font-size:1rem; font-weight:500">
            {{ $results->total() }} {{ $results->total() === 1 ? 'prestador encontrado' : 'prestadores encontrados' }}@if ($results->lastPage() > 1) · página {{ $results->currentPage() }} de {{ $results->lastPage() }}@endif
        </h2>
        <ul class="results">
            @foreach ($results as $listing)
                @php
                    $base = $siteBase($listing);
                    $matched = $value('q') === '' ? null : collect($listing->services)->first(function (array $service) use ($filters): bool {
                        $name = \App\Services\Portal\PortalText::normalize($service['name']);
                        foreach (\App\Services\Portal\PortalText::tokens($filters['q'] ?? null) as $token) {
                            if (! str_contains($name, $token)) { return false; }
                        }
                        return true;
                    });
                    $requestUrl = $base.'/solicitar?origem=portal'.(is_array($matched) && is_int($matched['id'] ?? null) ? '&servico='.$matched['id'] : '');
                    $services = array_slice($listing->services, 0, 6);
                @endphp
                <li>
                    <article class="card" aria-labelledby="empresa-{{ $listing->id }}">
                        <header>
                            @if (is_array($listing->logo))
                                <img class="logo" src="{{ $base }}/midia/{{ $listing->logo['file'] }}" alt="" width="56" height="56" loading="lazy" decoding="async">
                            @else
                                <span class="initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($listing->business_name, 0, 1)) }}</span>
                            @endif
                            <div>
                                <h3 id="empresa-{{ $listing->id }}">{{ $listing->business_name }}</h3>
                                <p class="meta">{{ $listing->getAttribute('segment_name') }} · {{ $listing->city }} - {{ $listing->state }}</p>
                            </div>
                        </header>
                        @if ($listing->summary)
                            <p class="about">{{ $listing->summary }}</p>
                        @endif
                        @if ($listing->region)
                            <p class="region"><strong>Atende:</strong> {{ $listing->region }}</p>
                        @endif
                        @if ($services !== [])
                            <ul class="services" aria-label="Serviços de {{ $listing->business_name }}">
                                @foreach ($services as $service)
                                    <li>{{ $service['name'] }}</li>
                                @endforeach
                                @if (count($listing->services) > 6)
                                    <li>+{{ count($listing->services) - 6 }}</li>
                                @endif
                            </ul>
                        @endif
                        <div class="actions">
                            <a class="btn primary" href="{{ $requestUrl }}">Solicitar atendimento<span class="sr-only"> a {{ $listing->business_name }}</span></a>
                            <a class="btn secondary" href="{{ $base }}?origem=portal">Ver site<span class="sr-only"> de {{ $listing->business_name }}</span></a>
                        </div>
                    </article>
                </li>
            @endforeach
        </ul>

        @if ($results->lastPage() > 1)
            <nav class="pages" aria-label="Páginas de resultados">
                @if ($results->previousPageUrl())
                    <a href="{{ $results->previousPageUrl() }}" rel="prev">← Anterior</a>
                @endif
                <span>Página {{ $results->currentPage() }} de {{ $results->lastPage() }}</span>
                @if ($results->hasMorePages() && $results->currentPage() < \App\Services\Portal\PortalSearch::MAX_PAGE)
                    <a href="{{ $results->nextPageUrl() }}" rel="next">Próxima →</a>
                @endif
            </nav>
        @endif
    @endif

    <section class="about-portal" aria-labelledby="sobre-portal">
        <h2 id="sobre-portal" style="font-size:1rem; color: var(--vs-text)">Sobre o portal</h2>
        <p>Aparecem apenas empresas que usam o VetorSmart, têm site publicado e escolheram participar. A ordem considera a compatibilidade com o serviço buscado, a região atendida e as informações públicas de cada empresa; empresas equivalentes alternam de posição a cada dia. Não há posições pagas nem anúncios.</p>
        <p>O portal não intermedeia pagamentos: o atendimento e o orçamento são combinados diretamente com a empresa.</p>
    </section>
</main>

<footer>
    <div class="wrap">Portal Regional · VetorSmart</div>
</footer>

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (function () {
        var form = document.querySelector('[data-search-form]');
        var button = document.querySelector('[data-search-button]');
        var status = document.querySelector('[data-search-status]');
        if (!form || !button) { return; }
        form.addEventListener('submit', function () {
            button.setAttribute('disabled', 'disabled');
            button.textContent = 'Buscando…';
            form.setAttribute('aria-busy', 'true');
            if (status) { status.textContent = 'Buscando prestadores…'; }
        });
        window.addEventListener('pageshow', function () {
            button.removeAttribute('disabled');
            button.textContent = 'Buscar';
            form.removeAttribute('aria-busy');
        });
    })();
</script>
</body>
</html>
