# VETORSMART-INTEGRATION-01 — Integração operacional entre portal, sites e painel do assinante

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem push, deploy, VPS ou DNS.
- **Sem migrations.**
- Sem Efí, cobrança ou autenticação alteradas.
- Sem MFA.
- Sem dependências.
- Sem redesign.

**Consolidação da PORTAL-04:** commit `50f3321`.

## Parecer final

**Concluída. Fluxo integrado de ponta a ponta, com dependências estruturais e externas identificadas.**

Uma solicitação enviada pelo Portal Regional percorre aviso à equipe, triagem, agendamento, remarcação, execução e conclusão (ou cancelamento justificado), com histórico completo e reflexo correto em "Meu Crescimento". O caminho completo foi demonstrado em um teste de ponta a ponta.

A auditoria encontrou e corrigiu **uma falha real de integração**: uma falha de aviso depois do registro transformava um pedido salvo em erro e permitia duplicá-lo.

**Não estão plenamente homologados:**
- a entrega de e-mails (depende do SMTP de produção);
- o portal em produção (DNS e certificado);
- a receita atribuível (depende de migration);
- "assumir" o atendimento por usuário (depende de migration).

| # | Critério | Situação |
|---|---|---|
| 1 | PORTAL-04 consolidada | `50f3321` |
| 2 | Inventário funcional | Seção 1 |
| 3 | Fluxo integrado de ponta a ponta | Atendido (seção 2, teste de ponta a ponta) |
| 4 | Lacunas corrigíveis sem migration | Resolvidas (seção 3) |
| 5 | Histórico e indicadores coerentes | Atendido (cancelamentos incluídos; nenhum evento contado antes da transição) |
| 6 | Experiência do assinante | Preservada e melhorada (atalho para a agenda, cancelamentos em "Meu Crescimento") |
| 7 | Isolamento e permissões | Mantidos (testes de isolamento e de permissões) |
| 8 | Dependências estruturais identificadas | Seção 6 |
| 9 | Sem alteração indevida em cobrança ou autenticação | Atendido |
| 10 | Sem push ou deploy | Atendido |

## 1. Inventário por módulo

| Módulo | Classificação | Evidência no código e observações |
|---|---|---|
| Portal regional | **Funcional**; em produção, **bloqueado por dependência** externa (DNS e certificado) | `PortalController`, `PortalSearch`, `PortalDirectory` (PORTAL-01 a 04) |
| Site individual | **Funcional** | `SiteController`; versões imutáveis; serviço e origem preservados |
| Formulário de solicitação | **Funcional** | `SiteRequestController`: empresa só pelo endereço, serviço validado, token de envio e trava, confirmação só após o registro |
| Atendimento com IA e encaminhamento humano | **Funcional**; a interpretação por IA depende do provedor externo configurado. Sem ele, o fluxo segue por regras determinísticas e encaminha para humano | `AssistantFlow` (agenda só após persistir), `AssistantConversations::handoff`, aviso `awaitingAttendance`, `identifyConversation` (vira solicitação na triagem) |
| Triagem | **Funcional** | `ServiceRequestService::triage` (transação, trava de linha, evento no histórico) |
| Agenda | **Funcional** | `AppointmentScheduler` (conflitos, bloqueios, jornada, fuso), remarcação e cancelamento em `AgendaController` |
| Execução | **Funcional** | `start` → em andamento; `notPerformed` com motivo |
| Conclusão e cancelamento | **Funcional** | `complete`; `cancel` com motivo obrigatório (`ReasonRequest`), que cancela também o agendamento |
| Histórico operacional | **Funcional** | `service_request_events` em cada transição, mais `AuditLogger` |
| Notificações | **Painel: funcional. E-mail: parcial** (fila e deduplicação ok; entrega depende do SMTP de produção). Falha de envio **não bloqueava** só no suporte; **corrigido para todos os avisos** (seção 3.1) | `OperationalNotifier`, `notification_deliveries` |
| "Assumir" o atendimento | **Parcial** | Ver o pedido e encaminhá-lo funcionam (lista, filtros, detalhe; o responsável operacional é o **profissional** definido na triagem ou no agendamento). Não existe responsável por **usuário**: exige coluna nova (migration) |
| Painel inicial | **Funcional** | Pedidos em aberto, agenda do dia, em andamento e concluídos/cancelados em 30 dias (`DashboardController`) |
| Meu Crescimento | **Funcional**; receita **bloqueada por dependência** (migration) | `GrowthMetrics`: recebidas, agendamentos realizados, concluídas, **cancelamentos (novo)**, origem e evolução mensal |

## 2. Fluxo operacional verificado

**Teste de ponta a ponta:** `IntegrationJourneyTest`, executado com o operador e conferido pelo proprietário.

1. **Receber:** o visitante envia pelo site com `origem=portal`. O pedido é criado na empresa do endereço com o serviço publicado, origem "Portal Regional" e status "Triada".
2. **Notificar:** os avisos no painel chegam a toda a equipe ativa (proprietário e operador).
3. **Visualizar:** o operador encontra o pedido em Solicitações, com o filtro de origem.
4. **Triagem:** automática pelo serviço escolhido; a triagem manual continua disponível (testes existentes).
5. **Agendar e remarcar:** agendamento pelo pedido e remarcação pela agenda, respeitando a disponibilidade. O detalhe aponta para a nova data na agenda.
6. **Executar e concluir:** iniciar e concluir.
7. **Cancelar com justificativa:** caminho alternativo testado (cancelamento após agendamento).
8. **Histórico:** `requested → triaged → scheduled → in_progress → completed`, na ordem.
9. **Indicadores:** "Meu Crescimento" mostra o pedido do portal como recebido, agendado e concluído. No caminho de cancelamento, aparece cancelado e **nunca concluído**. O operador não acessa "Meu Crescimento" (permissão existente, Proprietário e Administrador).

## 3. Correções executadas

### 3.1 Falha de aviso tratada para todos os avisos (falha real de integração)

**Problema comprovado:** os avisos rodam depois do commit (`DB::afterCommit`) e, se a fila ou o e-mail falhavam, a exceção subia depois de o pedido já estar salvo. Em seguida:
- o visitante via erro, embora o pedido existisse;
- o token de envio não chegava a ser marcado, então o reenvio do mesmo formulário **criava um segundo pedido**.

**Correção:** em `OperationalNotifier::afterCommit`, a falha de um aviso é registrada em log (`Operational notice not delivered.`, só com a chave do evento, o id da empresa e a classe do erro, sem dados pessoais) e não chega ao chamador. Vale para pedidos, agendamentos, conclusões, atendimento IA, suporte e cobrança.

**Teste:** com o envio forçado a falhar, o pedido é confirmado uma vez e o reenvio não duplica. Rodado **sem a correção, o teste falha**, o que comprova a regressão.

### 3.2 Trava contra reenvio sob concorrência

- O tempo de espera da trava passou a ser configurável (`platform.site_request_lock_wait`, padrão 10 s).
- **Teste:** com a trava ocupada por um envio em curso, o segundo envio do mesmo formulário **não confirma e não cria nada** ("Sua solicitação ainda está sendo registrada…"). Liberada a trava, o reenvio registra uma única vez.

### 3.3 Cancelamentos em "Meu Crescimento"

- Novo indicador **"Cancelados ou não realizados"** no mês, contado pela data de encerramento, com comparação ao mês anterior. Um aumento aparece em vermelho.
- A tabela de origem ganhou a coluna **"Canceladas ou não realizadas"**.
- Esse caminho nunca conta como conversão nem como conclusão.

### 3.4 Navegação entre atendimento e agenda

Cada agendamento no detalhe da solicitação ganhou o link **"Ver na agenda"**, que abre a agenda no dia do agendamento, no fuso da empresa. O caminho inverso, da agenda para a solicitação, já existia.

## 4. Arquivos alterados

- `app/Services/OperationalNotifier.php`: falha de aviso registrada sem bloquear.
- `app/Http/Controllers/Site/SiteRequestController.php` e `config/platform.php`: espera da trava configurável.
- `app/Services/Growth/GrowthMetrics.php`: `cancelled` no mês e por origem.
- `app/Http/Controllers/Tenant/GrowthController.php` e `resources/js/pages/tenant/growth/index.tsx`: indicador e coluna de cancelamentos, com cores invertidas no comparativo.
- `app/Http/Controllers/Tenant/ServiceRequestController.php` e `resources/js/pages/tenant/requests/show.tsx`: "Ver na agenda".
- `tests/Feature/Operations/IntegrationJourneyTest.php` (novo, 4 testes).
- `tests/Feature/Websites/RegionalPortalTest.php`: asserção da linha de origem com `cancelled`.

## 5. Testes direcionados

| Verificação | Resultado |
|---|---|
| `IntegrationJourneyTest` (novo) | **4 de 4**: jornada completa, cancelamento, falha de aviso sem erro e sem duplicata, reenvio concorrente |
| Testes diretamente afetados (`Notifications`, `OperationalPanelsTest`, `GrowthAndSupportTest`, `ServiceRequestWorkflowTest`, `AgendaTest`, `RegionalPortalTest`) | **115 de 115** (uma asserção da PORTAL-04 atualizada para o novo campo) |
| RootAdmin, que também usa `GrowthMetrics` (`PlatformAdministrationTest`) | Passou na mesma execução, junto com `GrowthAndSupportTest`: **26 de 26** |
| PHPStan (arquivos alterados) / Pint / TypeScript / build | 0 erros / ok / ok / ok |
| Visual ("Meu Crescimento" e Solicitações; celular e computador, claro e escuro) | **8 de 8** |

**Cobertura e limitações:**
- **Concorrência:** a trava foi testada com bloqueio simulado no mesmo processo, sem requisições paralelas reais. Em produção, o cache usa o banco, que suporta travas atômicas.
- **E-mail:** a entrega não foi verificada, por depender do SMTP.
- **Escopo:** não foram repetidas a suíte completa, as baterias da PORTAL-01 a 04 (além do arquivo afetado) e os testes visuais do portal.

## 6. Pendências e riscos

**Dependências estruturais (migration) e externas**
1. **Receita atribuível:** não há valor cobrado no atendimento. Exige um campo de valor efetivo na conclusão, ou um registro de cobrança vinculado.
2. **Responsável por usuário ("assumir"):** hoje a atribuição é por profissional. Um responsável por pessoa da equipe exige uma coluna nova.
3. **SMTP de produção:** os e-mails estão enfileirados e deduplicados, mas a entrega não foi homologada.
4. **DNS e certificado** do portal, e as 19 migrations pendentes da implantação.
5. **Provedor de IA:** a interpretação livre no atendimento depende do provedor externo configurado (o fluxo determinístico funciona sem ele).

**Riscos**
1. **Aviso perdido sem nova tentativa:** um aviso que falha fica só no log. Com a fila de e-mail ativa, falhas de entrega têm novas tentativas (3); o aviso no painel não. Monitorar o log `Operational notice not delivered.`
2. **Trava em cache volátil:** com um cache sem travas atômicas (ex.: `array` ou `file` em vários servidores), a proteção contra reenvio enfraquece. Manter `CACHE_STORE=database` (ou Redis) em produção.
3. **Origem declarativa:** "Portal Regional" vem do link do portal. Não altera a empresa nem o serviço e não muda após a criação.

## 7. Git

- `50f3321`: PORTAL-04.
- **INTEGRATION-01 sem commit**, aguardando autorização.
- `correio.md` e `executed.md` fora dos commits.
- **Sem push e sem deploy.**

Nenhuma outra etapa foi iniciada.
