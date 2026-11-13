<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Atendimento — {{ $companyName }}</title>
    <style>
        :root {
            --accent: {{ $accent }};
            --text: #0F172A;
            --muted: #475569;
            --border: #E2E8F0;
            --surface: #F8FAFC;
            --danger: #B91C1C;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: var(--text);
            background: var(--surface);
            overflow-x: hidden;
        }
        .shell { max-width: 720px; margin: 0 auto; min-height: 100%; display: flex; flex-direction: column; background: #fff; }
        header { padding: 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        header h1 { font-size: 1.05rem; margin: 0; line-height: 1.3; overflow-wrap: anywhere; }
        header p { margin: 2px 0 0; font-size: .85rem; color: var(--muted); }
        .pill { font-size: .8rem; padding: 4px 10px; border-radius: 999px; background: var(--surface); border: 1px solid var(--border); color: var(--muted); white-space: nowrap; }
        .pill[data-state="human"] { border-color: var(--accent); color: var(--accent); }
        a.back { color: var(--accent); font-size: .9rem; text-decoration: none; white-space: nowrap; }
        main { flex: 1; padding: 16px; display: flex; flex-direction: column; gap: 10px; overflow-y: auto; }
        .msg { max-width: 85%; padding: 10px 12px; border-radius: 12px; font-size: .95rem; line-height: 1.45; white-space: pre-line; overflow-wrap: anywhere; }
        .msg small { display: block; font-size: .75rem; color: var(--muted); margin-bottom: 2px; }
        .msg.visitor { align-self: flex-end; background: var(--accent); color: #fff; }
        .msg.visitor small { color: rgba(255,255,255,.85); }
        .msg.assistant, .msg.operator { align-self: flex-start; background: var(--surface); border: 1px solid var(--border); }
        .msg.system { align-self: center; font-size: .8rem; color: var(--muted); background: transparent; }
        .notice { font-size: .9rem; color: var(--muted); padding: 10px 12px; border: 1px dashed var(--border); border-radius: 12px; }
        .error { color: var(--danger); font-size: .9rem; padding: 0 16px; min-height: 0; }
        .error:empty { display: none; }
        form { display: flex; gap: 8px; padding: 12px 16px 16px; border-top: 1px solid var(--border); }
        input[type="text"] { flex: 1; min-width: 0; height: 44px; border: 1px solid var(--border); border-radius: 10px; padding: 0 12px; font: inherit; }
        input[type="text"]:focus-visible, button:focus-visible, a:focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }
        button { height: 44px; padding: 0 16px; border: 0; border-radius: 10px; background: var(--accent); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        button:disabled { opacity: .6; cursor: default; }
        .contacts { display: flex; flex-wrap: wrap; gap: 8px; }
        .contacts a { padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border); color: var(--text); text-decoration: none; }
        .loading { color: var(--muted); font-size: .9rem; }
        @media (max-width: 420px) { .msg { max-width: 92%; } }
    </style>
</head>
<body>
<div class="shell">
    <header>
        <div>
            <h1>{{ $companyName }}</h1>
            <p>Atendimento virtual</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <span id="state" class="pill" role="status" aria-live="polite">Conectando…</span>
            <a class="back" href="{{ $siteUrl }}">Voltar ao site</a>
        </div>
    </header>

    @if ($available)
        <main id="messages" aria-live="polite" aria-label="Conversa">
            <p class="loading" id="loading">Iniciando atendimento…</p>
        </main>
        <p class="error" id="error" role="alert"></p>
        <form id="composer" autocomplete="off">
            <label for="body" class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Sua mensagem</label>
            <input id="body" name="body" type="text" maxlength="{{ (int) config('assistant.max_visitor_message_chars', 500) }}" placeholder="Digite sua mensagem" required disabled>
            <button id="send" type="submit" disabled>Enviar</button>
        </form>
    @else
        <main>
            <p class="notice">O atendimento virtual não está disponível agora. Fale com a empresa pelos contatos abaixo.</p>
            <div class="contacts">
                @if (filled($contact['phone'] ?? null))
                    <a href="tel:{{ preg_replace('/\D+/', '', $contact['phone']) }}">Telefone: {{ $contact['phone'] }}</a>
                @endif
                @if (filled($contact['whatsapp'] ?? null))
                    <a href="https://wa.me/{{ preg_replace('/\D+/', '', $contact['whatsapp']) }}" rel="noopener">WhatsApp: {{ $contact['whatsapp'] }}</a>
                @endif
                @if (filled($contact['email'] ?? null))
                    <a href="mailto:{{ $contact['email'] }}">E-mail: {{ $contact['email'] }}</a>
                @endif
            </div>
        </main>
    @endif
</div>

@if ($available)
<script>
(function () {
    'use strict';

    var messagesEl = document.getElementById('messages');
    var stateEl = document.getElementById('state');
    var errorEl = document.getElementById('error');
    var form = document.getElementById('composer');
    var input = document.getElementById('body');
    var sendBtn = document.getElementById('send');
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var lastId = 0;
    var timer = null;

    function showError(text) { errorEl.textContent = text || ''; }

    function setState(label, human) {
        stateEl.textContent = label;
        stateEl.setAttribute('data-state', human ? 'human' : 'ai');
    }

    function render(messages) {
        var loading = document.getElementById('loading');
        if (loading) { loading.remove(); }

        messages.forEach(function (message) {
            if (message.id > lastId) { lastId = message.id; }

            var bubble = document.createElement('div');
            bubble.className = 'msg ' + (message.role === 'visitor' ? 'visitor' : message.role);

            if (message.role !== 'system' && message.role !== 'visitor') {
                var who = document.createElement('small');
                who.textContent = message.role_label;
                bubble.appendChild(who);
            }

            bubble.appendChild(document.createTextNode(message.body));
            messagesEl.appendChild(bubble);
        });

        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function request(method, url, payload) {
        var options = {
            method: method,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        };

        if (payload) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }

        return fetch(url, options).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok) {
                    var error = new Error(data.message || 'Não foi possível concluir. Tente novamente.');
                    error.status = response.status;
                    throw error;
                }
                return data;
            });
        });
    }

    function applyStatus(data) {
        if (data.awaiting_human) {
            setState('Aguardando atendente', true);
        } else {
            setState('Recepcionista virtual', false);
        }
    }

    function poll() {
        request('GET', '/atendimento/mensagens?after=' + lastId).then(function (data) {
            render(data.messages || []);
            applyStatus(data);
        }).catch(function () { /* the next poll tries again */ });
    }

    function startTimer() {
        if (timer === null) { timer = window.setInterval(poll, 5000); }
    }

    function enable(enabled) {
        input.disabled = !enabled;
        sendBtn.disabled = !enabled;
    }

    request('POST', '/atendimento/conversa').then(function (data) {
        render(data.messages || []);
        applyStatus(data);
        enable(true);
        input.focus();
        startTimer();
    }).catch(function (error) {
        var loading = document.getElementById('loading');
        if (loading) { loading.textContent = error.message; }
        setState('Indisponível', false);
    });

    // A resend of the same text (after a lost response) reuses its id, so the server answers it only once.
    var pendingSend = null;

    function newClientId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') { return window.crypto.randomUUID(); }
        return Date.now().toString(36) + Math.random().toString(36).slice(2);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var body = input.value.trim();

        if (body === '') { return; }

        if (pendingSend === null || pendingSend.body !== body) {
            pendingSend = { body: body, clientId: newClientId() };
        }

        enable(false);
        showError('');

        request('POST', '/atendimento/mensagens', { body: body, after: lastId, client_id: pendingSend.clientId }).then(function (data) {
            pendingSend = null;
            input.value = '';
            render(data.messages || []);
            applyStatus(data);
        }).catch(function (error) {
            showError(error.message);
        }).finally(function () {
            enable(true);
            input.focus();
        });
    });
})();
</script>
@endif
</body>
</html>
