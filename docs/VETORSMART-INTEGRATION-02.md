# VETORSMART-INTEGRATION-02 — Gestão operacional, receita atribuível e notificações confiáveis

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem push, deploy ou VPS.
- **Duas migrations locais** (MySQL), aplicadas somente no ambiente de desenvolvimento e no banco de testes.
- Sem Efí, contratos, assinaturas, bloqueios financeiros, login ou MFA alterados.
- Sem dependências novas e sem nova infraestrutura (fila e banco existentes).
- Sem redesign: só as telas do fluxo (Solicitações, detalhe, conclusão e Meu Crescimento).

**Consolidação da INTEGRATION-01:** commit `e0e3fc7`.

## Parecer final

**Concluída no código e nos testes.** Responsáveis atribuíveis e transferíveis com histórico, receita declarada auditável, notificações com estado de processamento e reprocessamento idempotente, e Meu Crescimento com filtros de período, origem e responsável.

**Pendente:** a verificação visual no navegador. Não há usuário local com senha conhecida, e criar ou alterar um usuário exige autorização. Os tipos (TypeScript) e o build estão aprovados.

| # | Critério de aceite | Situação | Evidência |
|---|---|---|---|
| 1 | Responsáveis atribuídos e transferidos | Atendido | `an owner assigns and transfers the responsible…` |
| 2 | Histórico preservado | Atendido | Evento `assigned` com o responsável anterior e o novo, o autor e a data; `AuditLog service_request.assigned` |
| 3 | Receita registrada e auditada | Atendido | `the realized amount is recorded when completing and corrected…` |
| 4 | Meu Crescimento coerente por origem | Atendido | `Meu Crescimento sums known amounts by origin…` |
| 5 | Cancelamentos sem receita | Atendido | `cancelled or unperformed requests never take an amount` |
| 6 | Notificações não interrompem solicitações | Atendido | `a failing notice does not block the assignment…` |
| 7 | Reprocessamento sem duplicidade | Atendido | Duas execuções de `notifications:reprocess` geram um único aviso |
| 8 | Isolamento multi-tenant | Atendido | Pedido de outra empresa → 404; responsável de outra empresa → recusado |
| 9 | Telas utilizáveis em desktop e mobile | **Visual pendente** | Grades responsivas e tabelas roláveis acessíveis por teclado; tipos e build ok |
| 10 | Testes direcionados aprovados | Atendido | 27 de 27, mais 267 testes afetados |

## 1. Responsável pelo atendimento

- Novo campo `service_requests.assigned_user_id`, com `assigned_at`. É distinto de `professional_id`, o profissional do agendamento.
- **Permissões:**
  - **Owner e Admin** atribuem, transferem ou removem o responsável (`ServiceRequestPolicy::assign`), via `PATCH /solicitacoes/{id}/responsavel`.
  - **Operador:** recebe 403 nessa ação e não vê o formulário.
- **Validação no serviço:** o responsável precisa ser membro **ativo** da **mesma empresa** e ter usuário ativo. Só solicitações em aberto mudam de responsável.
- **Histórico e aviso:**
  - Cada mudança gera um evento ("Responsável: X → Y", com autor e data) e um registro de auditoria.
  - O novo responsável é avisado no painel e, conforme as preferências, por e-mail (categoria "Solicitação atribuída a você"). Quem fez a mudança não é avisado.
- **Listagem:**
  - Nova coluna "Responsável".
  - Filtros: "Atribuídas a mim", "Sem responsável" ou uma pessoa da equipe, e período de chegada ("de" e "até"), no fuso horário da empresa.
- **Detalhe:** mostra o responsável e o cartão "Responsável pelo atendimento".
- **Compatibilidade:** solicitações antigas ficam sem responsável (`NULL`). A chave estrangeira usa `nullOnDelete`.

## 2. Receita atribuível

- **Armazenamento:**
  - `realized_amount_cents` (inteiro sem sinal, em centavos), com `realized_amount_recorded_at` e `realized_amount_recorded_by`.
  - **`NULL` significa desconhecido**, diferente de zero.
  - Registros históricos não recebem valor inventado.
- **Entrada:**
  - O valor pode ser informado ao **concluir** o atendimento (campo opcional) ou depois, em "Valor realizado", via `PATCH /solicitacoes/{id}/valor`.
  - `App\Support\Money` converte "1.234,56", "1234.56" etc. em centavos usando apenas strings e inteiros. Valores inválidos são recusados com mensagem clara.
- **Regras:**
  - Só solicitações **concluídas** aceitam valor. Canceladas e não realizadas são recusadas.
  - Um valor por solicitação: a correção substitui o anterior e nunca soma duas vezes.
  - Quem atende pode informar o primeiro valor. **Corrigir** um valor existente é só para Owner e Admin.
  - Toda alteração gera um evento ("Valor realizado: R$ 1.300,00 (antes: R$ 1.250,50)") e um registro de auditoria com os valores anterior e novo.
- **Natureza:** a receita é rotulada nas telas como **declarada pela empresa, não comprovante de pagamento**. Efí, contratos e assinaturas não foram tocados.

## 3. Notificações operacionais confiáveis

A tabela existente `notification_deliveries`, chave de idempotência da INTEGRATION-01, passou a guardar o estado de cada aviso:

- **Estado (`status`):** `processing`, `processed`, `failed` ou `skipped`. Linhas anteriores ficam como `processed`.
- **Dados para reconstruir o aviso:** `event`, `subject_id`, `actor_id` e `attempts`.
- **E-mails do aviso:**
  - `mail_queued`: enfileirados;
  - `mail_sent`: contado **somente** quando o canal de e-mail confirma o envio (evento `NotificationSent`);
  - `mail_failed`: contado quando o job esgota as tentativas (`OperationalMail::failed`).
- **`last_error`:** somente a classe do erro, nunca a mensagem, que poderia conter dados pessoais.

Comportamento:

- **Envio:**
  - Os avisos rodam **após o commit** da transação (`DB::afterCommit`).
  - Os e-mails vão para a **fila** (`ShouldQueue`, `afterCommit`, 3 tentativas com espera).
  - A conclusão de uma solicitação não depende do SMTP.
- **Falha:** marca o aviso como `failed`, registra o log `Operational notice not delivered.` sem dados pessoais e **não** chega ao usuário.
- **Reprocessamento (`php artisan notifications:reprocess [--limit=100]`):**
  - Cada aviso falho é reivindicado por uma atualização condicional (`failed → processing`). Duas execuções, ou uma execução concorrente com um evento novo, nunca avisam duas vezes.
  - Um aviso que não pode mais ser reconstruído é marcado `skipped`: assunto removido, agendamento remarcado (já tem aviso próprio) ou atribuição superada por transferência posterior.
- **E-mails recusados pelo servidor:** seguem o caminho padrão do Laravel (`failed_jobs` e `queue:retry`). Nenhuma entrega é simulada.

## 4. Meu Crescimento

- **Filtros:**
  - Período: este mês, mês anterior, 3, 6 ou 12 meses, comparado com o período anterior de mesma duração;
  - Origem;
  - Responsável (uma pessoa ou "Sem responsável").
- **Calendário:** todos os limites seguem o fuso da empresa. A tabela dos "Últimos seis meses" passou a agrupar os meses também no fuso da empresa (antes usava UTC).
- **Indicadores:** solicitações recebidas, agendamentos realizados, serviços concluídos, cancelados ou não realizados, conversão (a partir de 5 solicitações) e **receita declarada**.
- **Receita:**
  - Soma em centavos feita pelo banco.
  - Os serviços concluídos sem valor são contados à parte ("N serviços concluídos sem valor informado"), nunca como zero.
  - Sem valor conhecido, o painel mostra "Não informada".
  - A comparação com o período anterior só aparece quando os dois períodos têm valores conhecidos.
- **Detalhamentos:** a tabela "Receita declarada por origem" mostra concluídos, receita informada e serviços sem valor, e a evolução mensal ganhou a coluna "Receita informada".
- **Consultas:** agregadas (`SUM`/`CASE` agrupados), sem N+1. Novos índices: `(tenant_id, status, closed_at)` e `(tenant_id, assigned_user_id)`.
- **Estados vazios:** há texto explicativo para a receita por origem. Todos os textos usam os tokens `vs-*`, válidos no modo claro e no escuro.

## 5. Arquivos

**Migrations (locais):**
- `2026_11_19_173650_add_assignee_and_realized_amount_to_service_requests.php`
- `2026_11_19_173651_add_processing_status_to_notification_deliveries.php`

**Novos:**
- `app/Support/Money.php`
- `app/Console/Commands/ReprocessOperationalNotices.php`
- `app/Http/Requests/Tenant/Operations/AssignServiceRequestRequest.php`
- `app/Http/Requests/Tenant/Operations/RealizedAmountRequest.php`
- `tests/Feature/Operations/OperationalManagementTest.php`

**Alterados:**
- `app/Enums/NotificationCategory.php`
- `app/Http/Controllers/Tenant/GrowthController.php`
- `app/Http/Controllers/Tenant/ServiceRequestController.php`
- `app/Models/ServiceRequest.php`
- `app/Notifications/OperationalMail.php`
- `app/Policies/ServiceRequestPolicy.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/Growth/GrowthMetrics.php`
- `app/Services/OperationalNotifier.php`
- `app/Services/ServiceRequestService.php`
- `routes/tenant.php`
- `resources/js/pages/tenant/growth/index.tsx`
- `resources/js/pages/tenant/requests/index.tsx`
- `resources/js/pages/tenant/requests/show.tsx`

## 6. Testes e verificações

| Verificação | Resultado |
|---|---|
| `OperationalManagementTest` (novo; atribuição, transferência, permissões, isolamento, receita, correção, cancelamentos, dupla contagem, falha e reprocessamento, consistência dos indicadores, conversão de valores) | **27 de 27** |
| Testes afetados (Operations, Notifications, GrowthAndSupport, PlatformAdministration, RegionalPortal) | **258 aprovados, 9 ignorados (já ignorados antes), 0 falhas** |
| PHPStan (configuração do projeto) | 0 erros |
| Pint | ok |
| TypeScript (`tsc --noEmit`) e build (Vite) | ok |
| Verificação visual (desktop e mobile, claro e escuro) | **Pendente**: sem usuário local com senha conhecida |

## 7. Limitações e riscos

1. A receita é **declarada**, não conciliada com pagamentos.
2. O reprocessamento é manual (`notifications:reprocess`). Agendar no `schedule` exige decisão de operação.
3. Avisos de categorias não reconstruíveis (chat, suporte, cobrança) continuam só com log e estado `failed`. Não são reprocessados automaticamente.
4. `mail_sent` depende do worker da fila em execução. Sem worker, os e-mails ficam enfileirados (`mail_queued`), sem marcar falsamente como enviados.
5. A concorrência do reprocessamento foi testada de forma sequencial (atualização condicional), sem execuções paralelas reais.
6. Operadores não escolhem responsável e só veem o filtro "Atribuídas a mim" e os demais da listagem.

## 8. Pendências para homologação na VPS

1. Aplicar as migrations pendentes na VPS (as anteriores e as 2 novas), com backup e autorização.
2. Manter um worker de fila ativo (`queue:work`) e o SMTP de produção configurado.
3. Validar visualmente as telas alteradas (Solicitações, detalhe, conclusão, Meu Crescimento) em celular e computador, nos modos claro e escuro.
4. Decidir se `notifications:reprocess` entra no agendador.

## 9. Git

- `e0e3fc7`: INTEGRATION-01.
- **INTEGRATION-02 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**

Nenhuma outra etapa foi iniciada.
