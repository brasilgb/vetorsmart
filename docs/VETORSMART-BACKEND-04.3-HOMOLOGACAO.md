# VETORSMART-BACKEND-04.3 — Homologação final e preparação para IA

**Status: CONCLUÍDO COM BLOQUEIOS.** A homologação autenticada no navegador foi feita para os três perfis (Owner, Admin, Operador), em 1440 px e 390 px. Não há erros de console nem overflow horizontal nas telas percorridas. Ficam abertos: o erro JavaScript de `/servicos` (não reproduzido), a homologação em MySQL 8 (o ambiente só tem MariaDB 11.8), a remoção dos dados `[HOMOLOG]`, o crédito de atribuição da foto da landing e a imagem `workshop.png`. Não há homologação integral. Nenhum commit, push, merge ou deploy.

Data: 11/11/2026. Diretório: `gateway/vetorsmart/`. Referências: `correio.md` (04.3), `docs/VETORSMART-BACKEND-04.2-ESTABILIZACAO.md`.

Evidências visuais: `/tmp/claude-1000/-home-anderson-projects-laravel-infra-abrasil-gateway-vetorsmart/119c73b2-3724-4db1-ab84-b825fc4ca73e/scratchpad/homolog04/` (capturas `*-1440.png` e `*-390.png`, scripts `*.cjs` do mesmo diretório).

---

## 1. Acesso de teste (seção 2)

Mecanismos existentes usados, sem alterar senhas de usuários reais:

- Cadastro de empresa pela tela de registro (`/register`, Fortify): criou `[HOMOLOG] Empresa 04.3` (tenant 5) com o Owner `homolog.owner043@example.test`.
- Gestão de equipe pela tela `/equipe` (o próprio Owner criou os perfis): `homolog.admin043@example.test` (Admin) e `homolog.operador043@example.test` (Operador).

Senhas: uso exclusivo de homologação, definidas nos próprios cadastros desta etapa. Nenhuma autenticação foi contornada. Uma tentativa anterior de redefinir a senha de um usuário existente foi negada pelo controle de permissões e não foi executada.

## 2. Erro JavaScript em `/servicos` (seção 3)

Resultado: **não reproduzido** em dev nem em build de produção.

- Dev (Vite): Owner e Admin sem erros de console nas páginas de serviços, busca, edição, profissionais, clientes, solicitações e agenda, incluindo navegação client-side pelo menu. Operador: dois erros 403 do navegador, correspondentes aos acessos negados de propósito (`/servicos/novo`, `/servicos/11/editar`).
- Build de produção: `public/hot` foi movido temporariamente para servir `public/build`; a mesma varredura não encontrou `TypeError`. `public/hot` foi restaurado (aponta para `http://[::1]:5174`).

Causa original: não identificada. O erro registrado nas etapas anteriores ocorreu em um estado que não reapareceu com os dados e o código atuais. Não houve mascaramento de exceções.

Achados correlatos corrigidos nesta etapa:

- **Filtro "Situação" vazio excluía tudo:** `ServiceController::index` e `ProfessionalController::index` usavam `$request->has('active')`. Com "Todos" (`active=`), a listagem retornava zero registros, porque o filtro passava a buscar apenas inativos. Corrigido para `filled('active')`. Teste de regressão: `tests/Feature/Operations/ServiceActiveFilterTest.php` (2 testes).

Observação de associação de profissionais: o vínculo serviço → profissionais foi salvo com sucesso (`PUT` retornou 303 e o auditor registrou `service.professionals_synced`). Em uma execução anterior, um salvamento com lista vazia removeu os quatro vínculos do serviço 11 (evento de auditoria às 12:45:08). A causa não foi reproduzida. Hipótese não confirmada: o estado local dos checkboxes é inicializado uma vez a partir das props (`useState(linked_professional_ids)`), e um estado defasado pode enviar lista vazia. Sugestão: ressincronizar o estado quando as props mudarem e exigir confirmação para remover todos os vínculos.

## 3. Homologação no navegador (seção 4)

Roteiro executado com Playwright (Chromium headless_shell 1243 do cache local), em 1440 px e 390 px.

| Item | 1440 px | 390 px | Observação |
|---|---|---|---|
| 1. Login | OK | OK | Owner, Admin, Operador |
| 2. Dashboard | OK | OK | Sem erros |
| 3. Serviços (criar, buscar, editar) | OK | OK | Busca por nome e descrição; ver filtro corrigido acima |
| 4. Associação de profissionais | OK | OK | Pela tela do serviço, "Salvar profissionais" |
| 5. Clientes (cadastro) | OK | OK | Ver item de validação de e-mail (seção 6) |
| 6. Solicitações (criar) | OK | OK | |
| 7. Triagem | OK | OK | Serviço e prioridade persistidos |
| 8. Agendamento | OK | OK | Horário digitado em `America/Sao_Paulo`, gravado em UTC (09:00 local = 12:00 UTC) |
| 9. Agenda dia/semana/mês | OK | OK | Abrem sem erro; ver observação sobre concluídos |
| 10. Início e conclusão | OK | OK | Status `completed` no banco |
| 11. Histórico do cliente | OK | OK | Exibe solicitação e último evento |
| 12. Cancelamento com motivo | OK | — | Botão de confirmação desabilitado sem motivo; motivo, autor e horário gravados (teste em 1440 px) |

Situações de erro e acesso:

- Serviço sem nome: mensagem visível "Informe o nome. Os demais campos são opcionais." (390 px).
- Cliente com e-mail inválido: o navegador bloqueia o envio e mostra a mensagem nativa em inglês ("Please include an '@'…"). Não há mensagem do servidor visível. **Pendência de legibilidade.**
- Agendamento fora da jornada: "Horário fora da jornada do profissional." visível.
- Registros inexistentes ou de outro tenant (Operador, tenant 5): `/clientes/2/editar`, `/clientes/1/editar`, `/solicitacoes/1`, `/solicitacoes/2`, `/servicos/1/editar`, `/profissionais/1/editar`, `/solicitacoes/999999`, `/clientes/999999/editar`, `/servicos/999999/editar`: todos 404.
- Overflow horizontal: nenhum em dashboard, serviços, profissionais, clientes, solicitações, agenda (semana e mês), detalhe de solicitação e edição de cliente, em 1440 px e 390 px.

Observação de agenda: a agenda lista apenas agendamentos com status `Scheduled`, por desenho do código. Atendimentos concluídos ou cancelados não aparecem nela; aparecem no histórico do cliente.

Teste manual que não foi feito: a triagem com alerta de erro (`show.tsx`, alerta da seção 3.2 da etapa 04.2) não foi disparada na tela; a verificação é por leitura de código e de testes anteriores.

## 4. Permissões homologadas (seção 2 e 4)

Verificadas no navegador, com o mesmo tenant:

| Ação | Owner | Admin | Operador |
|---|---|---|---|
| Acessar `/servicos` | 200 | 200 | 200 (somente consulta) |
| "Novo serviço" / editar serviço | Sim | Sim | Não: `/servicos/novo` e `/servicos/11/editar` retornam 403 |
| `POST /servicos` direto | 200 (criou) | 200 (criou) | 403 |
| `PUT /servicos/11/profissionais` direto | 405 (não explicado) | 405 (não explicado) | 403 |
| Associar profissional pela tela | Sim (303) | Sim | Não |
| Equipe `/equipe` | 200 | 200 | 403 |
| Configurações da empresa | 200 editável | 200 editável | 200 somente leitura (`canEdit: false`) |
| Clientes, solicitações, agenda, triagem, agendamento | Sim | Sim | Sim (verificado no fluxo do Owner e leitura do Operador) |

Pontos para decisão:

- **Operador vê configurações em somente leitura.** Não é escrita, mas é acesso a dados administrativos. O brief pede não conceder acesso administrativo ao operador; a decisão é sua.
- **Duas linhas da tabela não estão explicadas:** o `PUT` direto com `fetch` retornou 405 para Owner e Admin, enquanto a tela de associação funciona (303). A rota existe no `route:list`. Não investiguei a causa do 405 por ser um teste de sonda; o caminho de produção (tela) funciona.
- **Efeito colateral do teste:** o `POST /servicos` direto criou dois serviços "Hack" no tenant 5 (ids 13 e 14). Estão em `[HOMOLOG]` e serão removidos com a empresa.

## 5. Banco de dados e concorrência (seção 5)

Banco efetivamente usado localmente: MariaDB 11.8.6 (Debian), via driver `mysql`. **Produção: não identificada.** O repositório não documenta o banco nem a versão de produção (apenas `DB_CONNECTION=mysql` no `.env` local e de exemplo). Preciso que você informe o motor e a versão de produção.

- Os 5 testes de concorrência foram preservados e passam em MariaDB 11.8 (`VETORSMART_CONCURRENCY=1`).
- Duas sessões reais: `mysql_first` (mantém locks em transação) e `mysql_second` (executa o `AppointmentScheduler`, com `innodb_lock_wait_timeout = 1`). As sessões são conexões separadas porque o `RefreshDatabase` mantém uma transação externa na conexão padrão. Descoberta desta etapa: com uma sessão de teste na mesma conexão, `DB::transaction` vira savepoint e os locks nunca são liberados.
- Não executado: MySQL 8. Não afirmo compatibilidade integral com MySQL 8.

## 6. Limpeza de homologação (seção 6)

Auditoria de `homolog:purge` (`app/Services/HomologPurgeService.php`, `app/Console/Commands/PurgeHomologData.php`):

- **Tabelas omitidas no plano e na remoção:** `service_requests`, `service_request_events`, `appointments`, `professional_blocks`. Todas têm chave estrangeira para `tenants` (confirmado no `information_schema`). A remoção real falharia nas empresas que têm operação, e os testes atuais não criam esses dados. **Não corrigido:** tentei uma correção, mas a alteração foi rejeitada na sessão. Aguardo sua decisão.
- **Padrão de usuário:** `homolog.%@example.test`. Não casa com `homolog.servicos@vetorsmart.test` (usuário do BACKEND-04.1).
- **Remoção só com confirmação digitada** ("EXCLUIR HOMOLOG") e terminal interativo. Não executei.

Inventário (prévia do comando, sem alterações):

| Empresa | Tenant | Origem | Observação |
|---|---|---|---|
| `[HOMOLOG] Empresa Alfa` | 1 | BACKEND-04 | Inclui membro `operator` (user 4) |
| `[HOMOLOG] Empresa Beta` | 2 | BACKEND-04 | |
| `[HOMOLOG] Servicos 04.1` | 4 | BACKEND-04.1 | Dono `homolog.servicos@vetorsmart.test` (fora do padrão) |
| `[HOMOLOG] Empresa 04.3` | 5 | BACKEND-04.3 | Criada nesta etapa; inclui os serviços "Hack" 13 e 14 e os dados de teste |
| `Clima Total` | 3 | Não é homologação | Não tocar |

Contagens do tenant 5 (antes da remoção): clientes, profissionais, serviços, solicitações (10 a 12), agendamentos (3 e 4) e bloqueios. Para remover, a correção de seção 6 precisa ser aplicada antes do `--execute`.

Usuários criados nesta etapa (homolog, `example.test`): ids 7, 8 e 9. Usuário 6 (`vetorsmart.test`) precisa de decisão.

## 7. Horários antigos (seção 7)

Registros afetados: agendamentos e bloqueios gravados antes da correção de fuso (etapa 04.2). Eles foram interpretados como UTC.

Inventário de identificação: o instante exato da publicação da correção de fuso não foi registrado no repositório e precisa ser confirmado antes da execução. Com base no `created_at`, existem hoje dois agendamentos do tenant 4 (ids 1 e 2, registros de homologação da etapa 04.1, anteriores à correção) e os agendamentos do tenant 5 (ids 3 e 4, criados após a correção e portanto **não** afetados).

Procedimento proposto (não aplicado):

1. Inventário somente leitura por `tenant_id` e `created_at` anterior à data da correção, e por `timezone` do tenant.
2. Para cada registro comprovadamente afetado: `starts_at` e `ends_at` deslocados em -3 horas (fuso fixo sem horário de verão), com verificação de que o tenant é `America/Sao_Paulo`.
3. Idempotência: marcar cada linha corrigida em tabela de auditoria (`audit_logs`), e pular as já marcadas.
4. Execução em transação, com contagem prévia e posterior.

Nenhum deslocamento genérico foi aplicado, conforme o brief.

## 8. Imagens e licenciamento (seção 8)

- `public/images/landing/hero-plumber.webp`: registro em `docs/VETORSMART-IMAGE-CREDITS.md` com licença CC BY 2.0, autor e origem (Wikimedia Commons via Openverse). **Pendente:** a licença exige atribuição visível e a página não a exibe. Não adicionei o crédito, porque o brief pede preservar a aparência aprovada e a inclusão exigiria alterar a landing. Comentário desatualizado em `resources/js/components/landing/hero-section.tsx` (afirma que a licença não está registrada).
- `public/images/workshop.png`: referenciada em `hero-section.tsx`, **sem registro de origem ou licença**. Não encontrei comprovante de uso comercial. Substituição não feita: não há recurso autorizado disponível no repositório. Precisa de decisão e de fonte autorizada.

## 9. Preparação técnica para BACKEND-05 (seção 9)

Interfaces internas existentes (sem IA implementada):

| Necessidade | Situação | Onde |
|---|---|---|
| Serviços ativos do tenant | Existe | `ServiceController::index` (web) e `GET /api/v1/tenant/services` (API, `tenant.resolve`) |
| Descrições e preços | Existe | Mesmos recursos; `price_type` e `price` |
| Profissionais habilitados | Existe | Relação `Service::professionals()`; `GET /api/v1/tenant/professionals` |
| Disponibilidade real | Existe apenas via web JSON | `GET /agenda/disponibilidade` (`AgendaController::slots`) → `AppointmentScheduler::availableSlots()`. **Não há endpoint de API** |
| Criar solicitação | Existe apenas via web | `POST /solicitacoes` → `ServiceRequestService::create()` |
| Encaminhar à triagem | Existe apenas via web | `PATCH /solicitacoes/{id}/triagem` → `ServiceRequestService::triage()` |
| Criar agendamento com validação transacional | Existe apenas via web | `POST /solicitacoes/{id}/agendar` → `AppointmentScheduler::schedule()` (transação, `lockForUpdate`) |
| Status de solicitações | Existe apenas via web | `GET /solicitacoes/{id}` (inclui histórico) |

Autorização: todas as rotas operacionais passam por `tenant.resolve` e policies (`ServiceRequestPolicy`, `AppointmentPolicy`, `ServiceCatalogPolicy`). Isolamento por tenant verificado nos testes e no navegador (404 para ids de outro tenant).

Recomendação técnica: para a IA, faltam contratos de API (versionados, com `tenant.resolve`) para disponibilidade, criação de solicitação, triagem, agendamento e consulta de status. Não criei esses endpoints nesta etapa, conforme o brief. O contrato de autorização deve ser o mesmo das policies.

## 10. Validação (seção 10)

| Comando | Resultado |
|---|---|
| `php artisan test --compact` | **282 testes, 274 aprovados, 0 falhas, 8 pulados** (3 de 2FA desativado; 5 de concorrência sem `VETORSMART_CONCURRENCY=1`) |
| `VETORSMART_CONCURRENCY=1 php artisan test … MysqlConcurrencyTest.php` | **5 aprovados** (MariaDB 11.8) |
| `vendor/bin/pint --dirty` | Aprovado |
| `npx tsc --noEmit` | 0 erros |
| `npm run build` | Aprovado (executado duas vezes nesta etapa) |

Testes de navegador: roteiros Playwright executados (seção 3). Não são parte da suíte automatizada.

## 11. Arquivos alterados nesta etapa

- `app/Http/Controllers/Tenant/ServiceController.php`: filtro `active` com `filled()`.
- `app/Http/Controllers/Tenant/ProfessionalController.php`: mesmo ajuste.
- `tests/Feature/Operations/ServiceActiveFilterTest.php` (novo): 2 testes de regressão.
- `docs/VETORSMART-BACKEND-04.3-HOMOLOGACAO.md` (este relatório) e `executed.md`.

Dados de teste criados no ambiente local (homolog): empresa `[HOMOLOG] Empresa 04.3` (tenant 5), usuários 7, 8 e 9, serviços, profissionais, clientes, solicitações 8 a 12, agendamentos 3 e 4, e os serviços "Hack" (13 e 14). Ainda não removidos.

## 12. Pendências e riscos

1. Erro JavaScript em `/servicos`: não reproduzido; causa não identificada.
2. MySQL 8: não testado. Produção: banco e versão não identificados.
3. `homolog:purge`: não remove `service_requests`, `service_request_events`, `appointments` e `professional_blocks` (falharia por chave estrangeira). Padrão não cobre `homolog.servicos@vetorsmart.test`. Aguardo decisão.
4. Dados `[HOMOLOG]` não removidos.
5. Horários antigos: procedimento documentado, não aplicado.
6. Crédito CC BY da foto da hero não exibido; `workshop.png` sem licença.
7. Validação de e-mail do cliente mostra mensagem nativa em inglês.
8. Operador consegue abrir configurações em modo somente leitura.
9. Associação de profissionais: lista vazia pode remover todos os vínculos (causa não confirmada).
10. Probe `PUT` direto retorna 405 para Owner/Admin (não explicado; caminho da tela funciona).
11. Agenda não mostra atendimentos concluídos ou cancelados (por desenho).

## 13. Recomendação de prontidão para o BACKEND-05

**Não recomendo iniciar o BACKEND-05 ainda.** O núcleo operacional funciona no fluxo completo, com isolamento e permissões verificados, mas há bloqueios: a causa do erro de `/servicos` não foi confirmada, o banco de produção não foi identificado, a remoção de dados de homologação está quebrada por chaves estrangeiras, e as imagens de terceiros não estão regularizadas. Esses itens não impedem o desenvolvimento da IA, mas devem fechar antes de qualquer publicação. Se você decidir prosseguir, o próximo passo técnico é criar os contratos de API operacionais listados na seção 9.

Restrições atendidas: sem commit, push, merge ou deploy; sem IA implementada; identidade visual e landing não alteradas.
