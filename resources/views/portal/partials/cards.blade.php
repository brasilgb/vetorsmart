{{--
    Company cards of the Regional Portal. Only published, public data: no phone, e-mail, rating or availability.
    Links go to the company's own standard address with ?origem=portal and, when the searched service is one of
    the published services, its id; the site decides the company from its address only.
--}}
<ul class="results">
    @foreach ($listings as $listing)
        @php
            $base = $siteBase($listing);
            $theme = \App\Services\Websites\SegmentTheme::for($listing->getAttribute('segment_slug'));
            $matched = $tokens === [] ? null : collect($listing->services)->first(function (array $service) use ($tokens): bool {
                $name = \App\Services\Portal\PortalText::normalize($service['name']);
                foreach ($tokens as $token) {
                    if (! str_contains($name, $token)) {
                        return false;
                    }
                }

                return true;
            });
            $serviceQuery = is_array($matched) && is_int($matched['id'] ?? null) ? '&servico='.$matched['id'] : '';
            $services = array_slice($listing->services, 0, 5);
        @endphp
        <li>
            <article class="card" aria-labelledby="empresa-{{ $listing->id }}">
                <div class="head">
                    @if (is_array($listing->logo))
                        <img class="avatar" src="{{ $base }}/midia/{{ $listing->logo['file'] }}" alt="" width="64" height="64" loading="lazy" decoding="async">
                    @else
                        <span class="avatar placeholder" style="background: {{ $theme['tone'] }}" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="{{ $theme['icon'] }}"/></svg>
                        </span>
                    @endif
                    <div>
                        <h3 id="empresa-{{ $listing->id }}">{{ $listing->business_name }}</h3>
                        <p class="activity">
                            <span>{{ $listing->getAttribute('segment_name') }}</span>
                            <span>{!! $icon($icons['pin']) !!}{{ $listing->city }} - {{ $listing->state }}</span>
                        </p>
                    </div>
                </div>
                @if (($filters['cidade'] ?? null) !== null && (int) $listing->getAttribute('region_score') === 1)
                    {{-- Based elsewhere: it appears because its published service region mentions the municipality. --}}
                    <p class="served">Sediada em {{ $listing->city }} - {{ $listing->state }}; atende {{ $filters['cidade'] }} conforme a região informada pela empresa.</p>
                @endif
                @if (is_array($matched))
                    <p class="match">{!! $icon($icons['check']) !!}Oferece: {{ $matched['name'] }}</p>
                @endif
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
                        @if (count($listing->services) > 5)
                            <li>+{{ count($listing->services) - 5 }} serviços</li>
                        @endif
                    </ul>
                @endif
                <div class="actions">
                    <a class="btn primary" href="{{ $base }}/solicitar?{{ $portalQuery($listing) }}{{ $serviceQuery }}">Solicitar atendimento<span class="sr-only"> a {{ $listing->business_name }}</span></a>
                    <a class="btn secondary" href="{{ $base }}?{{ $portalQuery($listing) }}{{ $serviceQuery }}">Ver profissional<span class="sr-only">: {{ $listing->business_name }}</span></a>
                </div>
            </article>
        </li>
    @endforeach
</ul>
