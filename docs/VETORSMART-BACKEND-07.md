# VETORSMART-BACKEND-07 — Operação completa e padronização visual

Data: 16/11/2026. Diretório: `gateway/vetorsmart/`. Referência inicial: commit `d2aec27`. Ambiente: máquina local de desenvolvimento, MySQL local (banco de testes `vetorsmart_test`).

## 1. Inventário das funcionalidades existentes

| Funcionalidade | Situação | Evidência |
|---|---|---|
| Cadastro de clientes | Implementada | `Tenant/CustomerController`, arquivamento restrito a proprietário/administrador (`CustomerPolicy`) |
| Cadastro de serviços | Implementada | `Tenant/ServiceController`, vínculo com profissionais, desativação |
| Cadastro de profissionais | Implementada | `Tenant/ProfessionalController`, jornada semanal, ativação/desativação |
| Solicitações e triagem | Implementada | `ServiceRequestService::create/triage`, transações com trava de linha |
| Agendamentos e disponibilidade | Implementada | `AppointmentScheduler` (jornada, bloqueios, conflitos, trava do profissional), remarcação e cancelamento na agenda |
| Execução, conclusão e cancelamento | Implementada | `start`, `complete`, `markNotPerformed`, `cancel`; motivo obrigatório (`ReasonRequest`: `required`, até 500 caracteres) |
| Validação de transições | Implementada | `RequestStatus::allowedTransitions` + `ServiceRequest::transitionTo` (rejeita mudanças fora do fluxo) |
| Histórico de atendimento | Implementada | Eventos por solicitação (`ServiceRequestEvent`) e histórico paginado por cliente (`CustomerController::history`) |
| Dashboard operacional | Defeituosa → corrigida | Contagem de "agendamentos de hoje" e horários dos próximos agendamentos usavam UTC (ver seção 2) |
| Atendimento humano e recepcionista IA | Implementada | Assumir, devolver, responder, identificar e encerrar conversas; agendamento pela IA só é confirmado após gravação na mesma transação (BACKEND-05.x) |
| Modo escuro | Defeituoso → corrigido | Ver seções 2 e 4 |
| Permissões por perfil | Implementada | Catálogo e arquivamentos só para proprietário/administrador; fluxo operacional disponível a todos os membros ativos do tenant (operadores incluídos, por desenho) |

## 2. Defeitos encontrados e corrigidos

### Fuso horário (backend)

Instantes são gravados em UTC, mas algumas telas exibiam o valor UTC sem converter para o fuso da empresa. Em São Paulo, isso mostrava horários três horas adiantados.

| Defeito | Arquivo | Correção |
|---|---|---|
| "Agendamentos de hoje" contava pelo dia UTC (`whereDate(..., today())`) | `app/Http/Controllers/DashboardController.php` | Intervalo do dia local da empresa (`startOfLocalDay` até +1 dia) |
| Próximos agendamentos do dashboard em UTC | `app/Http/Controllers/DashboardController.php` | `$tenant->toLocal(...)` |
| Data de criação na lista de solicitações em UTC | `app/Http/Controllers/Tenant/ServiceRequestController.php` | `toLocal` com fuso da empresa (relação `tenant` carregada junto, sem N+1) |
| Nota de cancelamento de agendamento no histórico registrava a hora UTC | `app/Services/AppointmentScheduler.php` | Hora local da empresa |
| Horário das mensagens no painel de atendimento em UTC | `app/Http/Controllers/Tenant/AssistantController.php` | `toLocal` |
| Datas de publicação, atualização e verificação de domínio do site em UTC | `app/Services/Websites/WebsitePresenter.php` | `toLocal` |

Notas de histórico gravadas antes desta correção não foram reescritas.

### Modo escuro (frontend)

Causa principal: as classes da marca `text-vs-text`, `text-vs-muted`, `border-vs-border`, `bg-vs-surface` e `bg-white` tinham cores fixas do tema claro e eram usadas em mais de 400 pontos das telas autenticadas. No tema escuro, o texto principal ficava com contraste de **1,08:1** (praticamente invisível), o texto secundário com **2,29:1** e os links com **3,35:1**.

Outros defeitos:

- Bordas de campos (`select`, `textarea`) com **1,48:1** no tema claro e **1,19:1** no escuro (mínimo WCAG 1.4.11: 3:1).
- Mensagens de erro e ações destrutivas em `text-red-600` sem variante escura.
- Seletor de aparência com cores neutras fixas, sem papel acessível de grupo de opções.
- Fundo inicial do HTML (`app.blade.php`) diferente do fundo do tema, causando um lampejo de cor ao carregar.

## 3. Funcionalidades implementadas

Nenhum módulo novo. A auditoria não encontrou funcionalidade do fluxo **Cliente → Solicitação → Triagem → Agendamento → Execução → Conclusão → Histórico** ausente. O trabalho foi de correção.

## 4. Telas corrigidas nos modos claro e escuro

### Tokens (`resources/css/app.css`)

Os tokens `vs-text`, `vs-muted`, `vs-border` e `vs-surface` passaram a ler variáveis que mudam com o tema. Foram criados `vs-link`, `vs-danger` e `vs-danger-soft`. As cores de marca usadas como preenchimento (`vs-blue`, `vs-ink`, `vs-petrol`, `vs-cyan`) continuam fixas, porque passam nos dois temas com texto branco (branco sobre `#2563EB`: 5,17:1).

| Token | Claro | Contraste (sobre branco) | Escuro | Contraste (sobre card `#111A2E`) |
|---|---|---|---|---|
| `vs-text` | `#0B1220` | 18:1 | `#E2E8F0` | 13,6:1 |
| `vs-muted` | `#475569` | 7,6:1 | `#A3B3C9` | 7,6:1 (6,9:1 sobre `#1E293B`) |
| `vs-link` | `#1D4ED8` | 6,7:1 | `#60A5FA` | 6,8:1 |
| `vs-danger` | `#B91C1C` | 6,5:1 | `#F87171` | 6,3:1 |
| `--input` (borda de campo) | `#8291A7` | 3,2:1 | `#64748B` | 3,6:1 |
| `--border` (linha decorativa) | `#CBD5E1` | — | `#334155` | — |

Hierarquia preservada: texto principal, secundário (`vs-muted`) e links têm tons distintos. O texto não foi simplesmente trocado para branco.

A landing (`.vs-light`) e as telas de autenticação (`.vs-auth`) continuam sempre claras: os tokens são fixados nos valores claros nessas áreas, e a variante `dark:` não se aplica a elas.

### Telas e componentes alterados

- Componentes globais: cabeçalho da barra lateral, seletor de empresa, `Field`/`selectClass`, `InputError`, seletor de aparência.
- Tenant: agenda, atendimento IA, clientes (lista e formulário), profissionais (lista e formulário), serviços (lista e formulário), solicitações (lista, nova e detalhe), equipe, configurações, meu site.
- RootAdmin: dashboard, segmentos, empresas (lista e detalhe), usuários.

Campos de formulário passaram a usar `border-input bg-card`; textos de erro, `text-vs-danger`; links, `text-vs-link`; caixas de alerta, `bg-vs-danger-soft` com `border-vs-danger/30`.

### Aparência

- `appearance-tabs.tsx`: usa os tokens do tema, expõe `role="radiogroup"`/`role="radio"` com `aria-checked` e tem foco visível.
- Persistência: a preferência fica em `localStorage` e no cookie `appearance` (365 dias), lido por `HandleAppearance` para aplicar a classe `dark` já no HTML do servidor. Como nenhum dos dois é apagado no logout, a preferência se mantém após navegação e novo login. Verificado por leitura de código; não testado no navegador.

## 5. Resultados dos testes

| Verificação | Resultado |
|---|---|
| Suíte antes das mudanças | 413 testes: 404 aprovados, 9 pulados, 0 falhas |
| `php artisan test --compact` (após) | **418 testes: 409 aprovados, 9 pulados, 0 falhas** |
| Novos testes `tests/Feature/Operations/LocalTimeDisplayTest.php` | 5 de 5 aprovados (falhavam antes das correções) |
| `VETORSMART_CONCURRENCY=1 … MysqlConcurrencyTest.php` | 9 de 9 aprovados (MySQL) |
| `vendor/bin/phpstan analyse` | 0 erros |
| `npx tsc --noEmit` | 0 erros |
| `npm run build` | Aprovado |
| `vendor/bin/pint --dirty` | Aprovado |

Transições operacionais e isolamento entre empresas estão cobertos pelos testes existentes (`tests/Feature/Operations`, `tests/Feature/Tenancy/CrossTenantWebTest.php`, `TenantIsolationTest.php`), todos aprovados nesta execução.

Os testes rodaram no MySQL local (`vetorsmart_test`), conforme `phpunit.xml`.

## 6. Pendências reais

1. **Validação visual: PENDENTE.** Não havia servidor local em execução nem contas de teste no banco de desenvolvimento, e criar contas exige autorização. Não houve capturas de tela em desktop ou mobile, nem nos temas claro e escuro. As telas exigidas pelo correio (login, aparência, dashboard, clientes, serviços, solicitações, agenda, atendimento e RootAdmin) **não foram verificadas visualmente**. A aprovação visual não é declarada com base no build.
2. Os contrastes foram calculados para os pares de cores principais (tabela da seção 4). Combinações pontuais com opacidade (por exemplo `border-vs-border/70`) e componentes `ui/*` herdados do kit não foram medidos um a um.
3. Os registros de auditoria do RootAdmin continuam exibindo horários em UTC. Como são de nível de plataforma e não de uma empresa, não foram alterados; vale decidir se devem mostrar o fuso de Brasília.
4. O componente `delete-user.tsx` e os layouts de autenticação não usados (`auth-split-layout`, `auth-card-layout`) mantêm as cores originais do kit.

## 7. Riscos técnicos

- A mudança de tokens é global. Qualquer tela que dependia de `text-vs-text` ser sempre escuro sobre um fundo claro fixo, fora de `.vs-light`/`.vs-auth`, passa a ficar clara no tema escuro. Não encontramos casos assim na varredura, mas sem validação visual o risco permanece.
- As bordas de campos ficaram mais visíveis no tema claro (`#8291A7`), o que altera levemente a aparência dos formulários.
- As alterações estão **sem commit**.

## 8. Próxima etapa recomendada

1. Validação visual autorizada: subir o ambiente local (ou de homologação) com contas de teste das empresas A e B e capturar as telas da seção 6 em 1440 px e 390 px, nos dois temas.
2. Revisar o resultado visual e então commitar.
3. Decidir o fuso de exibição da auditoria do RootAdmin.

## Restrições respeitadas

Nenhum deploy, migração, alteração de infraestrutura compartilhada, mudança em outros SaaS, alteração de política comercial ou reintrodução de MFA. Nenhum commit foi criado.
