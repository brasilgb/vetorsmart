# VETORSMART-BACKEND-04.1 — Correção e ativação do módulo de Serviços

**Status: CONCLUÍDO COM PENDÊNCIAS.** O fluxo completo foi executado no navegador, em desktop (1440 px) e mobile (390 px): cadastrar serviço → associar profissional → cadastrar cliente → criar solicitação → triar → agendar → iniciar → concluir, com os dados persistidos e exibidos. Foram encontrados e corrigidos cinco defeitos reais. **Um conflito de permissão precisa da sua decisão** (seção 6), e **um erro de console de desenvolvimento permanece** na tela de Serviços (seção 7).

Data: 11/11/2026. Diretório: `gateway/vetor-smart/`. Referências: `correio.md` (BACKEND-04.1), `docs/VETORSMART-BACKEND-04.md`.

---

## 1. Diagnóstico

Método: teste automatizado de ponta a ponta do módulo (`tests/Feature/Operations/ServicesModuleTest.php`), leitura do `storage/logs/laravel.log`, e roteiros de navegador em Chromium (Playwright) com login real, em duas larguras.

| # | Sintoma observado | Causa identificada | Situação |
|---|---|---|---|
| D1 | Não havia como associar profissionais a um serviço pela tela de Serviços | A associação só existia na tela do profissional; faltavam rota e interface no lado do serviço | Corrigido |
| D2 | Serviço desativado depois da triagem ainda podia ser agendado | `AppointmentScheduler::schedule` não checava `active` do serviço (só a triagem checava) | Corrigido |
| D3 | Triagem não era salva (situação continuava "Solicitado") | A tela de detalhe enviava a prioridade como rótulo ("Normal") em vez do valor ("normal"); a validação recusava e a operação não persistia | Corrigido |
| D4 | Tela de detalhe da solicitação falhava (erro 500) quando já havia histórico | `ServiceRequestEvent` não tinha o relacionamento `user`, que a tela carrega | Corrigido |
| D5 | Agenda aberta pelo menu dava erro 500 | `AgendaController::parseDate` chamava `createFromFormat` com texto vazio (o link do menu não envia data) | Corrigido |
| D6 | Busca de serviços só olhava o nome | Escopo da consulta limitado a `name` | Corrigido: nome e descrição |
| D7 | Operador não consegue cadastrar nem editar serviços, como pede o brief | Política `ServiceCatalogPolicy` restringe escrita a Owner e Admin, e testes existentes (`TenantScreensTest`, `OperationalPermissionsTest`) afirmam isso de forma explícita | **Não alterado.** Ver seção 6 |

Verificado sem defeito: listagem com ativos e inativos, cadastro com todos os campos, persistência e recarga da edição, validação de preço por tipo, desativação, filtro de ativos, isolamento entre tenants na edição, e a triagem oferecendo apenas serviços ativos.

## 2. Correções

**2.1 Associação de profissionais ao serviço (D1).**
- `ServiceService::syncProfessionals`: substitui o conjunto de profissionais do serviço. Só aceita profissionais do próprio tenant e ativos. Grava auditoria `service.professionals_synced` com o que foi adicionado e removido.
- `ServiceController::syncProfessionals` e rota `PUT /servicos/{service}/profissionais` (`tenant.services.professionals`), com autorização `update`.
- `SyncServiceProfessionalsRequest`: valida a lista de ids.
- `resources/js/pages/tenant/services/form.tsx`: bloco "Profissionais habilitados" na edição, com caixas de seleção e botão "Salvar profissionais". Na criação, informa que é preciso salvar o serviço antes.

**2.2 Serviço desativado não é agendado (D2).** `AppointmentScheduler::schedule` recusa com erro em `service_id` quando o serviço está inativo, mesmo que a solicitação já esteja triada. A solicitação permanece na triagem.

**2.3 Triagem salva (D3).** `ServiceRequestController::show` passa `priority_value` (valor do enum). `show.tsx` usa esse valor como padrão do formulário. O rótulo continua sendo exibido no resumo.

**2.4 Histórico renderiza (D4).** `ServiceRequestEvent::user()` adicionado.

**2.5 Agenda abre sem parâmetros (D5).** `parseDate` usa `createFromFormat('!Y-m-d')` dentro de `try`, com fallback para hoje, tanto para ausência de data quanto para texto inválido.

**2.6 Pesquisa (D6).** `ServiceController::index` busca em nome **ou** descrição.

## 3. Arquivos alterados nesta etapa

- `app/Services/AppointmentScheduler.php`: checagem de serviço ativo na reserva.
- `app/Services/ServiceService.php`: `syncProfessionals`.
- `app/Http/Controllers/Tenant/ServiceController.php`: busca, dados da edição e nova ação.
- `app/Http/Requests/Tenant/Operations/SyncServiceProfessionalsRequest.php` (novo).
- `app/Http/Controllers/Tenant/ServiceRequestController.php`: `priority_value`.
- `app/Http/Controllers/Tenant/AgendaController.php`: `parseDate`.
- `app/Models/ServiceRequestEvent.php`: `user()`.
- `routes/tenant.php`: rota `tenant.services.professionals`.
- `resources/js/pages/tenant/services/form.tsx`: seção de profissionais.
- `resources/js/pages/tenant/requests/show.tsx`: `priority_value`.
- `tests/Feature/Operations/ServicesModuleTest.php` (novo, 11 testes).
- `tests/Feature/Operations/ServiceRequestWorkflowTest.php`: +1 teste (histórico renderiza).
- `tests/Feature/Operations/AgendaTest.php`: +1 teste (agenda sem data e com data inválida).

## 4. Testes

- `php artisan test --compact`: **262 testes, 259 aprovados, 0 falhas, 3 pulados** (2FA desativado).
- Testes novos desta etapa: 13. Todos passam.
- `vendor/bin/pint --dirty`: aprovado. `npx tsc --noEmit`: 0 erros. `npm run build`: aprovado.

Os testes de isolamento cobrem: edição de serviço de outro tenant (404), associação de profissional de outro tenant (erro de validação, nenhum vínculo gravado), e agenda que não mostra dados de outro tenant.

## 5. Evidências do fluxo operacional (navegador)

Roteiro `operation-flow.cjs`, com login real, em 1440 e 390 px:

1. Cadastro de serviço "Op desktop/mobile <id>" em `/servicos/novo`: persistido e listado.
2. Edição do serviço, marcação da profissional "Ana Técnica" e "Salvar profissionais": vínculo gravado.
3. Solicitação criada para o cliente de homologação, em `/solicitacoes/nova`.
4. Triagem com o serviço criado: situação "Em triagem".
5. Agendamento para a próxima segunda-feira às 09:00, com a profissional vinculada: situação "Agendado".
6. "Iniciar atendimento": situação "Em andamento".
7. "Concluir atendimento": situação "Concluído".
8. `/agenda?view=week` mostra o atendimento com a profissional.

Resultado: `horizontalOverflowPx` = 0 nas duas larguras; nenhuma resposta HTTP 4xx ou 5xx nas duas larguras.

Captura: `scratchpad/homolog/operacao-desktop.png` e `scratchpad/homolog/operacao-mobile.png`. As capturas foram geradas, mas **não foram inspecionadas visualmente por mim**.

Verificação do roteiro de serviços: `services-flow.cjs` (criação, pesquisa, edição, vínculo, persistência após recarga, descrição, overflow). Passou nas duas larguras.

## 6. Decisão pendente: permissão do operador

O brief pede que o operador "consiga" cadastrar, editar e ativar serviços. Hoje:

- `ServiceCatalogPolicy`: escrita restrita a Owner e Admin; Operador lê.
- Testes existentes afirmam esse comportamento: `TenantScreensTest` ("an operator … cannot manage services") e `OperationalPermissionsTest` ("an operator can read the catalog but cannot change it").

Não alterei a regra, porque ela é uma decisão de segurança testada em etapas anteriores e o brief entra em conflito com ela. **Preciso que você escolha:**

- (a) manter Owner e Admin gerenciando o catálogo e o operador só consultando (estado atual); ou
- (b) permitir que o operador também cadastre, edite e desative serviços (exige ajustar `ServiceCatalogPolicy` e os dois testes acima).

## 7. Pendências reais

1. **Erro de console em `/servicos`** (`TypeError: Cannot convert undefined or null to object`), ocorre na montagem da tela. A pilha aponta para o wrapper de `useState` do Inertia dev (`@inertiajs_react.js`, `mountStateImpl`), chamado de `ServicesIndex`. O fluxo não é interrompido. Não consegui confirmar se ocorre no build de produção: o ambiente local serve pelo Vite dev server. Não o corrigi porque a causa não está no código da tela (outras telas com `useState` não reproduzem).
2. **Decisão de permissão do operador** (seção 6).
3. **Revisão visual humana** em 1440 e 390 px. As capturas foram geradas, não inspecionadas por mim.
4. **Mensagens de validação na triagem** não foram confirmadas visualmente. A recusa anterior (D3) ocorria sem texto visível no formulário, então o componente de erro do campo precisa de verificação.
5. **Dados de homologação no banco local** (tenant id 4, "[HOMOLOG] Servicos 04.1"): usuário `homolog.servicos@vetorsmart.test`, cliente "Cliente Homolog Servicos", profissional "Ana Técnica", serviços "Op …" e "desktop-…"/"mobile-…", solicitações 3 a 7, e disponibilidades de segunda a sexta. Devem ser removidos por você ou por um comando de limpeza `[HOMOLOG]`.
6. Pendências anteriores do BACKEND-04 continuam: fuso horário fixo em UTC, teste de concorrência com duas conexões, MySQL 8, histórico consolidado no cadastro do cliente, motivo livre no cancelamento pela agenda.
7. Licenças de `public/images/workshop.png` e `public/images/landing/hero-plumber.webp` continuam sem registro em `docs/VETORSMART-IMAGE-CREDITS.md`.

## 8. Próxima etapa recomendada

1. Decidir a permissão do operador (seção 6).
2. Revisão visual em 1440 e 390 px das telas de Serviços, Solicitações e Agenda.
3. Confirmar o erro de console em build de produção (`npm run build` servido por Laravel, sem Vite dev).
4. Limpeza dos dados `[HOMOLOG]` do banco local.

Não houve commit, push, merge ou deploy.
