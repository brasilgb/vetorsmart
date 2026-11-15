# VETORSMART-RELEASE-06 — Fechamento do código e preparação para implantação

Data: 14/11/2026. Diretório: `gateway/vetorsmart/`. Branch: `main`.

**Situação**: três commits criados, na ordem pedida, sobre a base `6399bd4`. **Nenhum deploy, nenhuma migração.** O push não fez parte desta etapa e ocorreu depois (ver atualização abaixo). Todos os resultados desta release são os da BACKEND-05.7, reproduzidos com os commits no lugar.

> **Atualização de 15/11/2026.** Os três commits (`18a7c2a`, `b7587be`, `f2b898b`) foram enviados a `origin/main` em 14/11/2026 às 14:57, conforme o reflog (`update by push`). Em 15/11/2026 também foi enviado `285a0bd` (ajuste da landing aos recursos entregues). Continua sem deploy e sem migração.

## 1. SHAs dos commits

| Ordem | SHA | Mensagem |
|---|---|---|
| base | `6399bd4` | Parcial vetorsmart (anterior à release) |
| 1 | `18a7c2a` | feat(atendimento): estabiliza atendimento, agenda e isolamento multi-tenant |
| 2 | `b7587be` | fix(qualidade): corrige tipagem, localização e confirmações de ações |
| 3 | `f2b898b` | chore(deps): atualiza dependências auditadas do Composer |

Cada mensagem termina com o trailer `Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>`.

## 2. Arquivos incluídos por commit

### Commit 1 — `18a7c2a` (23 arquivos, +2133 / −35)

Atendimento, concorrência, timezone, segurança multi-tenant, guard de banco, desempenho da agenda, testes e documentação.

- Serviços: `app/Services/AppointmentScheduler.php` (horário local no histórico e verificação de conflito com lock); `app/Services/Assistant/AssistantFlow.php` (trecho da referência de falha no agendamento); `app/Services/Assistant/AssistantConversations.php` (trechos de retenção do token).
- Controllers: `app/Http/Controllers/Site/AssistantController.php` (token do visitante só como string); `app/Http/Controllers/Tenant/AgendaController.php` (consulta única de leitura).
- Guard e configuração: `app/Support/DatabaseGuard.php` (novo); `app/Providers/AppServiceProvider.php`; `config/platform.php`; `phpunit.xml`.
- Testes: `tests/Concurrency/` (novo, 2 arquivos); `tests/Feature/Operations/MysqlConcurrencyTest.php`; `tests/Feature/Operations/TimezoneTest.php` (novo); `tests/Feature/Tenancy/CrossTenantWebTest.php` (novo); `tests/Feature/Assistant/VisitorTokenSecurityTest.php` (novo); `tests/Feature/Assistant/BookingFailureRuleTest.php` (novo); `tests/Feature/Assistant/AssistantTest.php`; `tests/Support/VisitorSite.php` (novo); `tests/Unit/DatabaseGuardTest.php` (novo).
- Documentação: `docs/VETORSMART-BACKEND-05-ATENDIMENTO-IA.md`; `docs/VETORSMART-BACKEND-05.5.md`, `05.6.md`, `05.7.md` (novos).

### Commit 2 — `b7587be` (16 arquivos, +141 / −38)

Tipagem do PHPStan, localização, Fortify e confirmações de ações destrutivas.

- Models com tipos declarados: `app/Models/Appointment.php`, `app/Models/AssistantConversation.php`, `app/Models/ServiceRequest.php`.
- Controllers e services com ajuste de tipo: `app/Http/Controllers/RootAdmin/TenantController.php`, `app/Http/Controllers/Tenant/AssistantController.php`, `app/Http/Controllers/Tenant/CustomerController.php`, `app/Http/Controllers/Tenant/ServiceRequestController.php`, `app/Services/Assistant/AssistantText.php`, e os trechos de tipagem de `AssistantConversations.php` e `AssistantFlow.php`.
- Fortify: `app/Providers/FortifyServiceProvider.php`.
- Localização: `lang/pt_BR/auth.php` (novo), `lang/pt_BR/validation.php`, `tests/Feature/Localization/` (novo).
- UX (confirmação de ações destrutivas): `resources/js/pages/tenant/requests/show.tsx`, `resources/js/pages/tenant/assistant/index.tsx`.

### Commit 3 — `f2b898b` (1 arquivo, +136 / −134)

- `composer.lock`. Verificado antes do commit: exatamente os 15 pacotes auditados na 05.6 (`brianium/paratest`, `doctrine/deprecations`, `filp/whoops`, `guzzlehttp/guzzle`, `guzzlehttp/psr7`, `larastan/larastan`, `laravel/fortify`, `laravel/framework`, `laravel/mcp`, `laravel/pail`, `laravel/pint`, `laravel/sail`, `laravel/serializable-closure`, `laravel/tinker`, `nunomaduro/collision`). Nenhuma versão nova.

### Separação por trecho (staging seletivo)

Três arquivos misturavam responsabilidades. Foram divididos com `git add -p` e arquivos inteiros, conforme o diff de cada trecho:

- `AssistantFlow.php`: 1 trecho no commit 1 (referência de falha); 2 trechos no commit 2 (tipagem do provedor de IA).
- `AssistantConversations.php`: 2 trechos no commit 1 (retenção do token); 2 trechos no commit 2 (`messagesAfter` e `summarize`, tipagem).
- `AgendaController.php`, `Site/AssistantController.php`, `AppointmentScheduler.php`: todos os trechos são do commit 1.

Nenhum outro arquivo misturava responsabilidades.

### Fora dos commits

- `correio.md` e `executed.md`: arquivos de controle, não integram nenhuma release funcional.
- `docs/VETORSMART-RELEASE-06.md` (este relatório): fora dos três commits da release; registrado em commit documental separado.

## 3. Resultado dos testes

Executados após os três commits, no MySQL 8.4.11 isolado (`vetorsmart_test`, container próprio em `127.0.0.1`, validado antes com `php artisan db:show`).

| Verificação | Resultado |
|---|---|
| Suíte completa com MySQL e concorrência (`VETORSMART_CONCURRENCY=1`) | **435 de 435**, 2574 asserções (a 05.7 tinha 434; +1 é o teste de regressão do guard para SQLite em produção) |
| Testes de concorrência real, processos separados | 3 de 3 |
| PHPStan nível 7 (gate `composer types:check`) | **0 erros** |
| Pint (`--test`) | aprovado |
| TypeScript (`tsc --noEmit`) | aprovado |
| Build de produção (`npm run build`) | aprovado |
| Sintaxe PHP em cada snapshot de commit | `18a7c2a`: 320 arquivos, 0 erros; `b7587be`: 322, 0 erros; `f2b898b`: 322, 0 erros |
| Integridade do repositório (`git fsck`) | sem erros |

Limitação: a suíte completa foi rodada só no estado final (`f2b898b`), que é a árvore que será implantada. Os commits intermediários foram verificados por sintaxe, não pela suíte inteira. Não rodei a suíte em `18a7c2a` isoladamente.

## 4. Regularização dos caches

Etapa 1 do correio, na ordem.

1. **Diretório e usuário**: `/home/anderson/projects/laravel/infra-abrasil/gateway/vetorsmart`, usuário `anderson`.
2. **Três caches de root identificados**: `bootstrap/cache/config.php`, `bootstrap/cache/routes-v7.php` e `bootstrap/cache/events.php`, todos de `root`, gerados em 14/11 às 13:42 por um `config:cache` rodado em container.
3. **Remoção**: os três arquivos foram removidos por caminho explícito (`rm -f -- ...`). Nenhum outro arquivo foi tocado. O diretório pertence a `anderson`, então não foi preciso alterar permissões nem usar privilégios globais.
4. **Limpeza pelo usuário correto**: `php artisan optimize:clear` com `CACHE_STORE=file` e `SESSION_DRIVER=file` por variável de ambiente, sem criar `.env`. Resultado: config, cache, compiled, events, routes e views com `DONE`.
5. **Ausência de configuração antiga de produção**: `bootstrap/cache/` ficou só com `.gitignore`. Os manifestos `packages.php` e `services.php` foram removidos pelo `optimize:clear` e não guardavam configuração de banco (conferido antes com grep: nenhuma ocorrência de `DB_DATABASE`, `APP_ENV` ou banco de desempenho).
6. **Validação da conexão MySQL**: `php artisan config:show database.connections.mysql` e `php artisan db:show` com o ambiente de teste: host `127.0.0.1`, porta `33107`, banco `vetorsmart_test`, MySQL 8.4.11, conexão aberta.

**Achado durante a regularização**: a regra de guard do SQLite recusava qualquer comando de console quando `APP_ENV=production`. Como não há `.env` no host, o padrão é `production`, e isso bloqueava até `optimize:clear`. Era um erro meu na implementação. Corrigido: destrutivos são recusados em produção, testes em produção são recusados, e comandos comuns passam. Coberto por teste de regressão, que está no commit 1.

**Por que não criei `.env`**: não criei `.env` no repositório. Para os comandos desta execução usei variáveis de ambiente, que não deixam rastro em arquivo.

## 5. Estado final do Git

- Branch `main`, três commits locais à frente de `6399bd4`.
- `git status`: somente `correio.md` e `executed.md` modificados; `docs/VETORSMART-RELEASE-06.md` untracked.
- No fechamento desta etapa nada havia sido enviado ao remoto (`github.com/brasilgb/vetorsmart`). O push ocorreu depois, em 14/11/2026 às 14:57.
- `git fsck`: sem erros.

## 6. Riscos residuais

1. **Desempenho medido em ambiente de laboratório.** A matriz da 05.7 foi rodada com nginx, PHP-FPM e MySQL no mesmo host, sem TLS, com dataset no limite de ocupação da agenda. O critério foi atendido nesse cenário; capacidade de produção não está comprovada. Recomendado repetir a carga na infraestrutura de produção antes de declarar capacidade.
2. **`SESSION_SECURE_COOKIE` precisa ser `true` em produção.** O laboratório rodou com `false` porque não tinha TLS. Em produção, o cookie de sessão e o cookie do visitante devem ser enviados só por HTTPS. Confirmar na configuração de produção antes de implantar.
3. **Expectativa alterada em `AssistantTest.php`** (falha de agendamento): a regra de produto "a solicitação de triagem é preservada" foi pedida na BACKEND-05.7 e está formalizada no commit 1. Continua sendo uma mudança de comportamento observável pelo operador; vale registrar na comunicação da release.
4. **Retenção do token passou a ser aplicada na leitura.** Conversas com `retention_until` vencido deixam de ser acessíveis imediatamente, e não só depois da purga. Efeito desejado, mas muda o comportamento para visitantes antigos.
5. **Commit 1 mistura documentação e código**, incluindo `docs/VETORSMART-BACKEND-05-ATENDIMENTO-IA.md`, que traz uma seção antiga (05.3). Não é um problema funcional, mas o histórico ficou com documentação de execuções anteriores.
6. **Commits intermediários não passaram pela suíte completa**, só pela verificação de sintaxe (seção 3).
7. **Sem `.env` no host.** Qualquer `php artisan` no host sem variáveis explícitas cai no padrão `production` com SQLite. Não causa dano, porque o guard recusa operações perigosas, mas os comandos de manutenção precisam de ambiente explícito.
8. **Sem migrações nesta release.** Nenhum arquivo em `database/` mudou desde `6399bd4`. Se houver migração pendente no banco de produção de releases anteriores, ela deve ser avaliada separadamente.

## 7. Plano proposto de implantação

Nenhum passo foi executado. Requer autorização explícita.

1. **Preparação**: confirmar que a versão em produção é `6399bd4` ou anterior, e que não há commits de outra origem no servidor.
2. **Backup**: dump verificável do banco de produção (`mysqldump --single-transaction`), com teste de restauração em banco separado antes da implantação.
3. **Código**: `git fetch` e checkout de `f2b898b` no servidor (ou da tag que for criada para ela).
4. **Dependências**: `composer install --no-dev --optimize-autoloader` a partir do `composer.lock` de `f2b898b`. `npm ci && npm run build`.
5. **Configuração**: verificar `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `VETORSMART_TEST_DATABASE` e `VETORSMART_DESTRUCTIVE_DATABASE` ausentes em produção.
6. **Caches**: `php artisan optimize:clear`, depois `config:cache`, `route:cache`, `event:cache` e `view:cache`, como o usuário da aplicação.
7. **Migrações**: nenhuma nesta release. Conferir com `php artisan migrate:status` que não há pendências.
8. **Reinício**: recarregar o PHP-FPM para o OPcache carregar o código novo (com `validate_timestamps=0`, isso é obrigatório).
9. **Verificação pós-implantação**: smoke test nas cinco rotas autenticadas e no atendimento do site público; um agendamento de teste em empresa de teste; conferir logs por erros nos primeiros 30 minutos.
10. **Janela**: fora do horário comercial do atendimento, porque a troca de código afeta conversas abertas.

## 8. Plano de rollback

O rollback é seguro porque a release não tem migrações.

1. **Gatilho**: erro sistemático em atendimento, agenda ou login, ou falha de smoke test.
2. **Código**: voltar o checkout para `6399bd4` (ou para a tag anterior). Como não há migração, o banco continua compatível.
3. **Dependências**: reinstalar a partir do `composer.lock` de `6399bd4` (`composer install --no-dev --optimize-autoloader`), para desfazer as atualizações do commit 3.
4. **Caches e reinício**: `optimize:clear`, recriar caches e recarregar o PHP-FPM.
5. **Dados criados durante a janela**: agendamentos e solicitações criados enquanto a release estava no ar continuam válidos no banco antigo, porque o schema não mudou. Conferir o histórico de conversas que estavam abertas e reencaminhar ao atendimento humano se necessário.
6. **Verificação**: mesmo smoke test da seção 7.
7. **Registro**: anotar o motivo do rollback e abrir uma tarefa para o próximo ciclo.

Alternativa para um problema localizado (sem rollback completo): reverter só o commit 1 (`git revert 18a7c2a`) se o problema estiver na concorrência ou no guard; o commit 2 é de tipos e pode permanecer, porque não muda comportamento.

## 9. Ações não realizadas

- Nenhum push durante esta etapa (o push ocorreu depois, em 14/11/2026 às 14:57).
- Nenhum deploy, nenhuma migração em produção.
- Nenhuma alteração em Docker compartilhado, VetorSales, VetorOS ou VetorLeads.
- Nenhuma dependência além do lockfile aprovado.
