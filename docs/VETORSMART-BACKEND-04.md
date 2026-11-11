# VETORSMART-BACKEND-04 — Núcleo Operacional

**Status: CONCLUÍDO COM PENDÊNCIAS.** Clientes, serviços, profissionais e equipe já existiam (BACKEND-02/03) e foram reaproveitados. Foram implementados: solicitações com fluxo e histórico, agendamentos com conflito e bloqueios, agenda em dia, semana e mês, dashboard com indicadores reais e as telas correspondentes. Validação: 249 testes (246 aprovados, 0 falhas, 3 pulados por 2FA desativado), `tsc` sem erros, build aprovado. **A homologação em navegador não foi executada** (ver seção 10).

Data: 11/11/2026. Diretório: `gateway/vetor-smart/`. Referências: `correio.md` (BACKEND-04), `docs/VETORSMART-BACKEND-02.md`, `docs/VETORSMART-BACKEND-03.md`. O relatório anterior de BACKEND-04 (gestão operacional e regularização) foi renomeado para `docs/VETORSMART-BACKEND-04-GESTAO-OPERACIONAL.md`, sem alteração de conteúdo.

---

## 1. Auditoria antes da implementação

Já existiam e foram reutilizados, sem duplicação:

- Clientes: cadastro, busca por nome/e-mail/telefone, arquivamento (`customers.status`), unicidade de documento por tenant.
- Serviços e categorias: duração, preço, situação ativo/inativo.
- Profissionais: cadastro sem login, ativação/desativação, vínculo N:N com serviços (`professional_services`), janelas de trabalho por dia da semana (`professional_availabilities`).
- Equipe, configurações, site, auditoria (`AuditLogger`), eventos de crescimento (`GrowthEventRecorder`).

Faltavam: solicitações, agendamentos, bloqueios de agenda, histórico de solicitações, indicadores operacionais e as telas de solicitação e agenda. Foram criados.

## 2. Cadastro de clientes

Sem alteração de código nesta etapa. Já atendia: nome, telefone, WhatsApp, e-mail, documento, endereço, observações, busca rápida, validação de duplicidade e arquivamento no lugar de exclusão.

**Pendência:** o brief pede o "histórico de solicitações e serviços" dentro do cadastro do cliente. Não foi implementado na tela de edição do cliente. O histórico existe por solicitação (seção 4) e pode ser filtrado pelo cliente, mas não há visão consolidada no cliente.

## 3. Catálogo de serviços e profissionais

Sem alteração de código. O agendamento só aceita profissional vinculado ao serviço (`AppointmentScheduler::ensureBookable`), e serviço sem duração não agenda.

**Profissionais e disponibilidade**, implementados:

- Janelas de trabalho: já existiam. Intervalos (ex.: almoço) são representados por duas janelas no mesmo dia, sem interseção (validado por `AvailabilityRules`).
- Bloqueios e indisponibilidades: nova tabela `professional_blocks`, criada e removida pela agenda. Um bloqueio não pode cobrir agendamentos ativos.
- Profissional inativo não recebe agendamento.
- Profissional não precisa de usuário de acesso.

## 4. Solicitações de atendimento

Fluxo implementado em `App\Enums\RequestStatus`:

```
Solicitado → Em triagem → Agendado → Em andamento → Concluído
                ↑            │            │
                └────────────┘            ├→ Não realizado
                 (cancelamento do       └→ Cancelado (de qualquer aberto)
                  agendamento)
```

- Triagem define serviço e prioridade. A primeira triagem move de "Solicitado" para "Em triagem"; as seguintes são registradas no histórico sem mudar a situação.
- Cada mudança de situação grava um evento em `service_request_events` (append-only), com situação anterior e nova, nota e usuário.
- Não realizado e cancelamento exigem motivo. Ao cancelar uma solicitação agendada, o agendamento ativo também é cancelado.
- Origem: o enum tem `panel`, `public_site`, `ai` e `api`, mas só `panel` é usada. As demais estão reservadas e não há integração implementada.
- Solicitações de outro tenant retornam 404 (IDOR bloqueado por `forTenant` + `whereKey`), e cliente, serviço e profissional são validados contra o tenant ativo, além das chaves estrangeiras compostas.

## 5. Agenda

- Visualizações: dia, semana (grade de 4 colunas em telas grandes) e mês. Navegação anterior, hoje e próximo.
- Novo agendamento a partir de uma solicitação triada, com horários livres consultados no servidor (`GET /agenda/disponibilidade`). A confirmação é validada novamente no servidor.
- Remarcação e cancelamento na própria agenda.
- Bloqueio de horário e remoção de bloqueio.

**Regras de conflito** (`App\Services\AppointmentScheduler`):

1. A operação abre transação e trava a linha do profissional (`lockForUpdate`). Duas reservas para o mesmo profissional são serializadas; a segunda vê a primeira.
2. O horário deve ser futuro e cair inteiro em uma janela de trabalho do mesmo dia.
3. Não pode sobrepor agendamento ativo nem bloqueio. Na remarcação, o próprio agendamento é excluído da verificação.
4. A disponibilidade exibida usa as mesmas regras, com passo de 30 minutos.

**Limitações da agenda:**

- Horários são interpretados no fuso da aplicação (`config/app.php`, UTC). Não há conversão para o fuso de cada empresa.
- O cancelamento pela agenda usa o motivo fixo "Cancelado pela agenda". Não há campo para digitar o motivo nesse ponto (o motivo é obrigatório no backend e está pré-preenchido).
- A visão de mês mostra os dias do mês em grade, sem paginação dos compromissos.

## 6. Dashboard operacional

Indicadores calculados a partir de dados reais do tenant (`DashboardController::operations`), sem dados mockados:

- Solicitações abertas, agendamentos de hoje, em andamento, concluídos e cancelados nos últimos 30 dias.
- Conversão em 30 dias: solicitações criadas no período que chegaram a "Agendado" ou posterior, sobre o total criado no período. Exibida como "—" quando não há solicitações no período.
- Próximos 5 compromissos.

Os indicadores de contagem linkam para a lista filtrada. Os cards de clientes, serviços e profissionais permanecem.

## 7. Interface e identidade visual

- Menu lateral na ordem operacional: Painel, Clientes, **Solicitações**, **Agenda**, Serviços, Profissionais, Usuários e permissões, Meu Site, Configurações.
- Formulários com os componentes existentes (`Field`, `Input`, `selectClass`, `Card`, `Button`), sem novo sistema visual.
- Situações são sempre texto, não só cor.
- A landing não foi alterada nesta etapa.

**Limitação:** a agenda usa grade em telas grandes e pilha de cartões em telas pequenas, por classes Tailwind (`sm:`, `xl:`). Não foi verificado visualmente.

## 8. Segurança

- Isolamento por tenant em todas as consultas (`forTenant`), com chaves estrangeiras compostas `(tenant_id, id)` em solicitações, eventos, agendamentos e bloqueios.
- Autorização por policy: `ServiceRequestPolicy` e `AppointmentPolicy`. Qualquer membro opera; não-membros recebem 403.
- Validação no backend em todos os formulários (FormRequests), inclusive a existência das referências dentro do tenant.
- Transações e travas nas operações críticas (agendar, remarcar, cancelar, bloquear, mudar situação).
- Auditoria em `service_request.*`, `appointment.*` e `professional_block.*`.
- Nenhuma dependência nova; nenhum uso de PostgreSQL.

## 9. Arquivos, migrations e rotas

**Migrations** (executadas no banco local):

- `2026_11_11_110237_create_service_requests_table`
- `2026_11_11_110238_create_service_request_events_table`
- `2026_11_11_110239_create_appointments_table`
- `2026_11_11_110240_create_professional_blocks_table`

**Enums:** `RequestStatus`, `RequestPriority`, `RequestOrigin`, `AppointmentStatus`.

**Modelos e factories:** `ServiceRequest`, `ServiceRequestEvent`, `Appointment`, `ProfessionalBlock`; factories `ServiceRequestFactory` e `AppointmentFactory`.

**Serviços:** `ServiceRequestService`, `AppointmentScheduler`.

**Controladores:** `Tenant\ServiceRequestController`, `Tenant\AgendaController`; ajuste em `DashboardController`.

**FormRequests** (`app/Http/Requests/Tenant/Operations/`): `StoreServiceRequestRequest`, `TriageServiceRequestRequest`, `ScheduleAppointmentRequest`, `RescheduleAppointmentRequest`, `ReasonRequest`, `StoreProfessionalBlockRequest`.

**Policies:** `ServiceRequestPolicy`, `AppointmentPolicy`.

**Telas:** `resources/js/pages/tenant/requests/{index,create,show}.tsx`, `resources/js/pages/tenant/agenda/index.tsx`, `resources/js/pages/dashboard.tsx`, `resources/js/components/app-sidebar.tsx`.

**Rotas** (`routes/tenant.php`, todas com `auth` e `tenant.resolve`):

| Método | Caminho | Ação |
|---|---|---|
| GET | `/solicitacoes` | Lista com filtro de situação |
| GET | `/solicitacoes/nova` | Formulário |
| POST | `/solicitacoes` | Cria |
| GET | `/solicitacoes/{id}` | Detalhe, histórico e ações |
| PATCH | `/solicitacoes/{id}/triagem` | Define serviço e prioridade |
| POST | `/solicitacoes/{id}/agendar` | Agenda com profissional |
| POST | `/solicitacoes/{id}/iniciar` | Em andamento |
| POST | `/solicitacoes/{id}/concluir` | Concluído |
| POST | `/solicitacoes/{id}/nao-realizada` | Não realizado (motivo) |
| POST | `/solicitacoes/{id}/cancelar` | Cancelado (motivo) |
| GET | `/agenda` | Agenda por `view` e `date` |
| GET | `/agenda/disponibilidade` | Horários livres (JSON) |
| PATCH | `/agenda/agendamentos/{id}` | Remarca |
| POST | `/agenda/agendamentos/{id}/cancelar` | Cancela agendamento |
| POST | `/agenda/bloqueios` | Cria bloqueio |
| DELETE | `/agenda/bloqueios/{id}` | Remove bloqueio |

## 10. Testes e evidências

**Testes novos** (`tests/Feature/Operations/`):

- `ServiceRequestWorkflowTest` (7): fluxo completo com histórico; transição inválida recusada; cancelamento com motivo obrigatório; cancelamento cancela o agendamento; solicitação de outro tenant retorna 404; cliente de outro tenant é recusado; não-membro recebe 403.
- `AgendaTest` (11): horário dentro da janela aceito; fora da janela recusado; sobreposição recusada; bloqueio impede agendamento; profissional sem o serviço recusado; profissional inativo recusado; solicitação não triada recusada; remarcação ignora o próprio agendamento; cancelamento volta à triagem; horários livres calculados no servidor; agenda não mostra dados de outro tenant.

**Ajuste em teste existente:** `tests/Feature/Tenancy/SegmentSeederTest.php` esperava o segmento "Beleza e estética". Atualizado para "Energia solar", em consequência da mudança já feita no seeder.

**Resultados:**

- `php artisan test --compact`: **249 testes, 246 aprovados, 0 falhas, 3 pulados** (2FA desativado).
- `vendor/bin/pint --dirty`: aprovado.
- `npx tsc --noEmit`: 0 erros.
- `npm run build`: aprovado.

**Não executado:**

- **Homologação em navegador real** (desktop e mobile): não feita. Não havia sessão autenticada preparada para captura. As telas foram verificadas apenas por tipos e build.
- **Concorrência real:** os testes são sequenciais. A garantia depende da trava `lockForUpdate` no profissional e não foi exercitada com duas conexões simultâneas.
- **MySQL 8:** o banco local é MariaDB. As migrations usam `CHECK` e chaves compostas, não validadas em MySQL 8.

## 11. Pendências e limitações

1. Histórico de solicitações e serviços na tela do cliente (seção 2).
2. Homologação em navegador, desktop e mobile, com captura de tela (seção 10).
3. Teste de concorrência com duas conexões reais.
4. Fuso horário por empresa. Hoje tudo é UTC.
5. Motivo livre no cancelamento pela agenda.
6. Integrações de origem (site público, IA, API): reservadas, não implementadas, como pede o brief.
7. Licença de `public/images/workshop.png` e `public/images/landing/hero-plumber.webp`: não registradas em `docs/VETORSMART-IMAGE-CREDITS.md`. Não afeta este módulo, mas bloqueia a publicação da landing.
8. Pendências de BACKEND-03/04 anteriores (MySQL 8, rotação de segredos, `robots.txt`, RootAdmin real) continuam abertas.

## 12. Próxima etapa recomendada

1. Homologação em navegador com um tenant de teste: cadastrar cliente, configurar serviço e profissional, agendar, iniciar, concluir e conferir o dashboard, em 1440 e 390 px.
2. Teste de concorrência com duas conexões simultâneas no mesmo horário.
3. Fuso horário por empresa e campo de motivo no cancelamento pela agenda.
4. Histórico consolidado no cadastro do cliente.

Não houve commit, push, merge ou deploy.
