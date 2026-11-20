<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    // Municipality field: registry suggestions while typing, and "Usar minha localização" (see municipality-field).
    (function () {
        var fields = document.querySelectorAll('[data-municipality-field]');
        Array.prototype.forEach.call(fields, function (field) {
            var input = field.querySelector('[data-municipality-input]');
            var hidden = field.querySelector('[data-municipality-id]');
            var options = field.querySelector('[data-municipality-options]');
            var button = field.querySelector('[data-locate-button]');
            var buttonLabel = field.querySelector('[data-locate-label]');
            var status = field.querySelector('[data-locate-status]');
            var warning = field.querySelector('[data-coverage-warning]');
            var coverage = [];
            try { coverage = JSON.parse(field.getAttribute('data-coverage') || '[]'); } catch (error) { coverage = []; }
            var known = {};
            var timer = null;

            var normalize = function (text) { return (text || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim(); };
            var checkCoverage = function () {
                if (!warning) { return; }
                var id = parseInt(hidden.value, 10);
                warning.hidden = !(id && coverage.length > 0 && coverage.indexOf(id) === -1);
            };
            var choose = function (municipality) {
                input.value = municipality.input;
                hidden.value = String(municipality.id);
                known[normalize(municipality.input)] = municipality;
                checkCoverage();
                input.dispatchEvent(new CustomEvent('municipality-chosen', { bubbles: true, detail: municipality }));
            };
            var sync = function () {
                var match = known[normalize(input.value)];
                hidden.value = match ? String(match.id) : '';
                checkCoverage();
            };
            var suggest = function () {
                var text = input.value.trim();
                if (text.length < 2) { return; }
                fetch(field.getAttribute('data-suggest-url') + '?busca=' + encodeURIComponent(text), { headers: { 'Accept': 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : { municipalities: [] }; })
                    .then(function (data) {
                        options.innerHTML = '';
                        (data.municipalities || []).forEach(function (municipality) {
                            known[normalize(municipality.input)] = municipality;
                            var option = document.createElement('option');
                            option.value = municipality.input;
                            options.appendChild(option);
                        });
                        sync();
                    })
                    .catch(function () {});
            };
            if (input.value && hidden.value) { known[normalize(input.value)] = { id: parseInt(hidden.value, 10), input: input.value }; }
            input.addEventListener('input', function () { sync(); window.clearTimeout(timer); timer = window.setTimeout(suggest, 200); });
            input.addEventListener('change', sync);
            checkCoverage();

            if (!button || !('geolocation' in navigator) || !window.isSecureContext) { return; }
            button.hidden = false;
            var idle = buttonLabel.textContent;
            var busy = function (on) { button.disabled = on; button.setAttribute('aria-busy', on ? 'true' : 'false'); buttonLabel.textContent = on ? 'Localizando…' : idle; };
            var say = function (text) { status.innerHTML = ''; var p = document.createElement('p'); p.textContent = text; status.appendChild(p); };
            var manual = ' Escolha o município digitando o nome.';
            var failed = function (text) { busy(false); say(text + manual); input.focus(); };

            button.addEventListener('click', function () {
                busy(true);
                say('Localizando… Se o navegador perguntar, permita o acesso à localização.');
                navigator.geolocation.getCurrentPosition(function (position) {
                    var body = JSON.stringify({ latitude: Math.round(position.coords.latitude * 1000) / 1000, longitude: Math.round(position.coords.longitude * 1000) / 1000 });
                    var controller = 'AbortController' in window ? new AbortController() : null;
                    var abort = controller ? window.setTimeout(function () { controller.abort(); }, 12000) : null;
                    fetch(field.getAttribute('data-locate-url'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: body, signal: controller ? controller.signal : undefined, credentials: 'omit' })
                        .then(function (response) { return response.json().catch(function () { return { status: 'unavailable' }; }); })
                        .then(function (data) {
                            if (abort) { window.clearTimeout(abort); }
                            busy(false);
                            if (data.status !== 'found' || !data.municipality) {
                                failed(data.status === 'outside' ? 'Sua localização parece estar fora do Brasil.' : (data.status === 'ambiguous' ? 'Não conseguimos identificar o município com segurança.' : 'A localização automática não está disponível agora.'));
                                return;
                            }
                            var municipality = data.municipality;
                            status.innerHTML = '';
                            var p = document.createElement('p');
                            p.textContent = 'Encontramos ' + municipality.label + '.';
                            var confirm = document.createElement('button');
                            confirm.type = 'button'; confirm.className = 'link-button'; confirm.textContent = 'Confirmar';
                            var change = document.createElement('button');
                            change.type = 'button'; change.className = 'link-button'; change.textContent = 'Escolher outro';
                            confirm.addEventListener('click', function () { choose(municipality); say('Município confirmado: ' + municipality.label + '. Você pode alterá-lo a qualquer momento.'); });
                            change.addEventListener('click', function () { status.innerHTML = ''; input.focus(); input.select(); });
                            p.appendChild(document.createTextNode(' '));
                            p.appendChild(confirm);
                            p.appendChild(document.createTextNode(' '));
                            p.appendChild(change);
                            status.appendChild(p);
                            confirm.focus();
                        })
                        .catch(function () { if (abort) { window.clearTimeout(abort); } failed('A localização demorou para responder.'); });
                }, function (error) {
                    failed(error.code === 1 ? 'Você não permitiu o acesso à localização.' : (error.code === 3 ? 'A localização demorou para responder.' : 'Não foi possível obter sua localização.'));
                }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
            });
        });
    })();
</script>
