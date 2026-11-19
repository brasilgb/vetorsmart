# VETORSMART-BILLING-01.3 — Fechamento financeiro local e preparação de release

Data: 18 e 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem push nem deploy.
- Sem cobranças reais nem compras de créditos.
- Nenhuma alteração na VPS.
- Landing page, portal regional e sites individuais não foram tocados.

## Parecer final

**Desenvolvimento financeiro local encerrado. APROVADO para preparação de release, com cobrança e compras bloqueadas.**

- **Regras:** as financeiras estão fechadas, e as vagas seguem uma política segura (nenhuma vaga ativa antes de paga).
- **Permissões:** as 17 operações financeiras do RootAdmin estão verificadas.
- **Testes:** 674 de 674.
- **Homologação Efí:** continua uma etapa separada, dependente de credenciais e de infraestrutura pública.
- **Liberação comercial:** cobrança real e venda de créditos dependem de aprovação comercial e dessa homologação.

## 1. Estado do Git

**Inicial:**
- `main` em `a06ed7d` (BILLING-01.1).
- BILLING-01.2 sem commit.
- Arquivos alterados fora do escopo: `correio.md`, `executed.md` e `pnpm-lock.yaml`.

**Commits realizados (locais, sem push):**

| Commit | Descrição |
|---|---|
| `78fa693` | `feat(billing): vagas de usuários separadas do cadastro e homologação do RootAdmin (BILLING-01.2)` |
| `3427e74` | `docs(release): registra BILLING-01.2 e proposta técnica de créditos de IA` |

Antes do commit, a BILLING-01.2 foi validada de novo: 630 de 630 testes (MySQL, concorrência), PHPStan 0, Pint, `tsc` e build ok, e nenhuma credencial no diff.

O histórico foi preservado: nenhum rebase, amend ou descarte. O `correio.md` e o `executed.md` ficaram fora dos commits. O `pnpm-lock.yaml`, alterado antes destas etapas, foi mantido como está.

**Final:** a BILLING-01.3 está **sem commit**, aguardando autorização. Arquivos:
- `app/Services/Billing/SeatService.php`;
- `app/Services/Billing/SubscriptionService.php`;
- `app/Http/Controllers/Tenant/SubscriptionController.php`;
- `resources/js/pages/tenant/subscription/index.tsx`;
- `tests/Feature/Billing/SeatTest.php` e `BillingConcurrencyTest.php`;
- `tests/Feature/RootAdmin/BillingPermissionsTest.php` (novo);
- este relatório.

## 2. Regras financeiras implementadas

### Plano e contratos (BILLING-01.1, mantidos)

- **Plano único, três ciclos:** mensal R$ 139,90, semestral R$ 719,40 e anual R$ 1.318,80. São **valores provisórios**: sem a aprovação no RootAdmin, nada é emitido em produção.
- **Usuários:** 3 incluídos, R$ 19,90 por mês por vaga adicional (provisório), 14 dias de teste.
- **Condições congeladas na contratação:** preço do ciclo, preço do adicional e promoção.
- **Troca de ciclo:** sem proporcional; recusada com cobrança aberta; agendada quando há período pago.
- **Uma cobrança por período:** índice único e lock.

### Vagas adicionais (revisadas nesta etapa)

**Problema da BILLING-01.2:** uma vaga a mais ficava ativa na hora, mas só era paga na próxima cobrança. Num ciclo anual, isso podia significar até 12 meses de uso sem pagamento.

**Política nova:** como **não existe cobrança complementar homologada**, uma vaga nunca fica ativa antes de ser paga.

| Situação | Aumento de vagas | Redução de vagas |
|---|---|---|
| Em teste, sem cobrança aberta | Vale na hora; entra na primeira cobrança (o teste é gratuito para tudo) | Vale na hora, se os ativos couberem |
| Em teste com cobrança aberta, ou com período pago | **Agendado:** entra na próxima cobrança gerada e **ativa só quando ela for paga** | Agendada para a cobrança seguinte; enquanto espera, as ativações respeitam o limite reduzido |
| Pagamento pendente, suspensa ou cancelada | **Recusado**: regularizar primeiro | Recusada |

**Demais regras:**
- **Gratuidade:**
  - trocar um usuário por outro dentro das vagas não gera cobrança;
  - usuários inativos nunca são cobrados.
- **Limite de usuários:** cadastrar ou reativar acima do limite é recusado, com o motivo, no painel e na API.
- **Cobranças abertas e contratos em andamento** nunca são alterados retroativamente (testado).
- **Ativação pelo pagamento:** a vaga passa a valer em `SubscriptionService::periodPaid`. As vagas ativas passam a ser as da cobrança paga, e a mudança agendada sai da fila quando a cobrança que a inclui é paga. Uma renovação não paga (inadimplência) nunca ativa as vagas.
- **Concorrência:** alterações de vagas e ativações de usuários usam lock da empresa (testado em MySQL real); a quitação usa lock da cobrança e da assinatura.
- **Idempotência:** repetir o mesmo pedido não grava nada novo, e a rotina diária executada duas vezes gera uma cobrança só (testado).
- **Clareza para o assinante:** "Minha Assinatura" mostra a regra que vale **agora** para a empresa ("vale na hora", "entra na próxima cobrança e vale após o pagamento" ou "não pode ser alterada"), as vagas incluídas, adicionais, em uso e agendadas, e a ausência de proporcional. O formulário some quando a alteração está bloqueada.

### Créditos de IA (revisados, arquitetura preservada)

- **Compra:** continua desligada. Não há franquia definitiva (o campo segue vazio).
- **Confirmação manual de compras:** existe **só no RootAdmin**. Assinantes recebem 403 (testado).
- **Validações:**
  - isolamento entre empresas;
  - estorno só do saldo não usado, com valor proporcional e recusa quando tudo foi consumido;
  - conciliação de saldo com o histórico;
  - débito concorrente uma vez por operação;
  - **nova nesta etapa:** duas confirmações simultâneas da mesma compra creditam uma vez só (MySQL real).
- **Recomendações para a integração com o provedor de IA:**
  1. Gravar os tokens **reais** (entrada e saída) devolvidos pelo provedor, em vez da estimativa por caracteres, que subestima o contexto enviado.
  2. Usar uma referência de idempotência por chamada ao provedor (hoje é conversa mais última mensagem). Se houver nova tentativa automática, reutilizar a mesma referência.
  3. Preencher `assistant.cost_per_million_tokens_cents` com o preço contratado, para o custo estimado aparecer no histórico.
  4. Aplicar um tempo limite por chamada e tratar a falha do provedor sem debitar crédito (já é o comportamento atual).
  5. Ajustar `max_provider_tokens_per_call` e a taxa de tokens por crédito depois de 30 dias de medição (ver `docs/VETORSMART-BILLING-01.2-CREDITOS-IA.md`).

## 3. RootAdmin e segurança

- **Permissões:** um teste percorre as **17 rotas financeiras** do RootAdmin: assinaturas, condições, prorrogação, cancelamento, baixa manual, sincronização, plano, Efí, teste de conexão, pacotes e compras (confirmar, cancelar e estornar).
  - Visitante → redirecionado ao login.
  - Proprietário de empresa → 403, e nada muda: nem a cobrança, nem a compra, nem a auditoria.
- **Auditoria de valores:** plano, regras, pacotes e Efí registram o valor anterior e o novo; credenciais aparecem só como "alterado". Vagas, ciclos, condições, baixas e compras também são auditados.
- **Aprovação e revogação da tabela comercial:** testadas com auditoria (aprovação marcada e depois desmarcada). Uma mudança de preço revoga a aprovação (BILLING-01.1).
- **Conta RootAdmin temporária** `homolog.root.billing012@example.test` (só no banco local):
  - **desativada**: perdeu o acesso root, foi suspensa, recebeu senha aleatória nova e teve 8 sessões encerradas;
  - o arquivo com a senha foi apagado do scratchpad;
  - não foi excluída, para preservar a ligação com os registros de auditoria;
  - as outras 3 contas root não foram tocadas.
- **Credenciais nos commits:** nenhuma credencial, chave, certificado ou senha real nos commits `78fa693` e `3427e74` (só os valores fictícios dos testes, como `secret-teste`).

## 4. Testes executados

| Verificação | Resultado |
|---|---|
| Pest completo (MySQL real, `VETORSMART_CONCURRENCY=1`) | **674 de 674**, 3987 asserções |
| Vagas (`SeatTest`, 16) | Recusa acima do limite; troca gratuita; reativação recusada; inativos não cobrados; aumento imediato no teste; **renovação mensal, semestral e anual** com a vaga cobrada × meses e ativa só após o pagamento; cobrança aberta inalterada; **renovação não paga não ativa as vagas e alterações com pagamento pendente são recusadas**; **pedido duplicado gera um registro e uma cobrança**; redução aplicada na renovação paga; redução abaixo dos ativos recusada; só o proprietário altera; empresa antiga sem limite; regra exibida conforme a situação |
| Concorrência (MySQL real, 6) | Abrir cobrança, período único, confirmação dupla de pagamento, débito de créditos, **troca de vagas sob lock da empresa** e **confirmação dupla de compra de créditos** |
| Permissões do RootAdmin (35) | 17 rotas × visitante e proprietário, e aprovação/revogação auditadas |
| PHPStan, Pint, TypeScript e build | 0 erros, ok, ok, ok |
| Visual de "Minha Assinatura" (mudou nesta etapa) | **8 de 8** (Chromium e Firefox, desktop e celular, claro e escuro), sem violações de axe |

**Defeito corrigido na homologação:** o cartão de vagas descrevia a regra pela situação da assinatura e ignorava uma cobrança já aberta. Agora o servidor informa a regra que vale no momento.

## 5. Checklist de implantação

**Não executado. Nenhuma alteração na VPS.**

### 5.1 Dependências entre versões

A versão em produção é anterior à PANELS-02. As etapas pendentes são cumulativas e devem subir juntas, na ordem dos commits:

PANELS-02 (`b1b1bfd`) → PANELS-03 (`32daf42`) → PANELS-04 (`ef89f9a`) → BILLING-01 (`1abad90`) → BILLING-01.1 (`dc0341f`) → BILLING-01.2 (`78fa693`) → BILLING-01.3 (sem commit).

- A BILLING-01 depende da PANELS-04 (avisos de cobrança pela central de notificações).
- A BILLING-01.2 depende da BILLING-01.1 (preço do adicional congelado).
- **Confirmar na VPS** com `php artisan migrate:status` quais migrations já rodaram.

### 5.2 Migrations pendentes, em ordem (`php artisan migrate --force`)

1. `2026_11_18_134100_add_is_homolog_to_tenants_table` (PANELS-02)
2. `2026_11_18_134101_mark_existing_users_as_verified` (PANELS-02)
3. `2026_11_18_160243_create_tenant_notifications_table` (PANELS-04)
4. `2026_11_18_160244_create_notification_deliveries_table` (PANELS-04)
5. `2026_11_18_160245_add_notification_preferences_to_tenant_users_table` (PANELS-04)
6. `2026_11_18_163905_create_plans_table` (BILLING-01): cria o plano sem preço
7. `2026_11_18_163906_create_billing_settings_table` (BILLING-01): ambiente de homologação
8. `2026_11_18_163908_create_subscriptions_table` (BILLING-01)
9. `2026_11_18_163909_create_subscription_events_table` (BILLING-01)
10. `2026_11_18_163910_create_billing_charges_table` (BILLING-01)
11. `2026_11_18_163911_create_billing_webhook_events_table` (BILLING-01)
12. `2026_11_18_182113_add_billing_cycles_to_plans_and_subscriptions` (BILLING-01.1): preços provisórios **não aprovados**
13. `2026_11_18_182114_create_ai_credit_tables` (BILLING-01.1): compras desligadas, nenhum pacote
14. `2026_11_18_194419_add_extra_seats_to_subscriptions_table` (BILLING-01.2)

Todas têm `down`. Rollback e reaplicação foram testados localmente nas das BILLING-01.1 e 01.2.

### 5.3 Variáveis de ambiente

Apenas os nomes; os valores ficam no `.env` da VPS.

| Variável | Necessidade |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` | Obrigatórias. **Não trocar a `APP_KEY`:** as credenciais da Efí são criptografadas com ela (se mudar, preserve a antiga em `APP_PREVIOUS_KEYS`) |
| `APP_URL=https://vetorsmart.com.br` | Obrigatória: os links dos e-mails da fila e a assinatura dos links usam esse host |
| `PLATFORM_PUBLIC_URL`, `PLATFORM_CENTRAL_HOST`, `PLATFORM_TENANT_BASE_DOMAIN`, `PLATFORM_PUBLIC_SCHEME`, `PLATFORM_TRUSTED_PROXIES` | Domínio público, usado também no endereço do webhook da Efí |
| `PLATFORM_SUBDOMAIN_SITES` | Manter como está até a aprovação da WILDCARD |
| `DB_*` | Banco MySQL de produção |
| `QUEUE_CONNECTION` (`database` ou `redis`) e `REDIS_*`, se usado | Fila dos e-mails e avisos |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | SMTP real, com SPF, DKIM e DMARC no domínio |
| `SESSION_*`, `CACHE_STORE` | Conforme a produção atual |
| `ASSISTANT_PROVIDER=none` | Manter até haver provedor de IA contratado |
| `ASSISTANT_RETENTION_DELETION=false` | Manter até a política de retenção ser decidida |

**Variáveis obsoletas:** `PLATFORM_PLAN_*`, `PLATFORM_ADDITIONAL_USER_MONTHLY_CENTS` e `PLATFORM_CYCLE_*` (`config/platform.php`, planos "Profissional" e "Premium") **não são usadas pelo código**. Os preços vêm do banco. Recomendo removê-las numa etapa futura, para não confundir.

**As credenciais da Efí não vão no `.env`:** ficam criptografadas no banco e são informadas pelo RootAdmin.

### 5.4 Filas, SMTP e scheduler

- **Worker:** `php artisan queue:work --tries=3 --max-time=3600` sob supervisor (systemd ou Supervisor), com reinício após o deploy (`php artisan queue:restart`).
- **Scheduler:** cron `* * * * * php /caminho/artisan schedule:run`. Tarefas: `billing:run` às 05:30 e `assistant:purge` às 06:30 (só relatório enquanto a exclusão estiver desligada), ambas com `withoutOverlapping`.
- **SMTP:** antes de abrir o cadastro, testar o envio da confirmação de e-mail e da recuperação de senha pela fila.

### 5.5 Backup e recuperação

1. Antes do deploy: `mysqldump --single-transaction --routines --triggers` do banco de produção, com cópia fora da VPS, além de uma cópia do `.env` e do diretório da aplicação (release anterior).
2. Registrar o commit atual em produção, para retorno.
3. **Se o deploy falhar antes das migrations:** voltar ao release anterior.
4. **Se falhar depois:** `php artisan migrate:rollback --step=N` (N = migrations aplicadas no deploy) ou restaurar o dump; depois, voltar o código.
5. Testar a restauração do dump numa base separada antes do deploy.

### 5.6 Verificações após o deploy

- `php artisan migrate:status`: todas as 14 rodaram.
- `php artisan about` e `config:show app.url`: produção, sem debug, URL correta.
- Login, cadastro (a empresa nova entra em teste), "Minha Assinatura" e o RootAdmin abrem sem erro.
- Envio de e-mail pela fila (confirmação de cadastro) e worker ativo.
- `php artisan schedule:list` mostra `billing:run` e `assistant:purge`.
- Rodar `php artisan billing:run` manualmente uma vez e conferir o relatório: sem emissões (ambiente de homologação, sem credenciais).
- Conferir no RootAdmin "Plano e Efí": selo **"Valores provisórios"**, ambiente **Homologação**, compra de créditos **desligada**, franquia vazia.
- Logs sem erros novos (`storage/logs`).

### 5.7 Manter cobranças e compras bloqueadas

Cada camada bloqueia sozinha; as quatro precisam continuar assim até a liberação formal:

1. **Ambiente Efí = Homologação** (padrão da migration). Produção exige uma confirmação explícita no RootAdmin.
2. **Plano "não aprovado"** (`prices_approved_at` vazio): mesmo em produção, nenhuma cobrança é emitida. Qualquer mudança de preço revoga a aprovação.
3. **Sem credenciais da Efí:** a emissão falha de forma controlada e fica "Em emissão", com o erro.
4. **Compra de créditos desligada** (`ai_credit_purchases_enabled = false`), nenhum pacote e franquia vazia.

**Conferência por consulta** (somente leitura):
- `SELECT efi_environment, ai_credit_purchases_enabled FROM billing_settings;`
- `SELECT prices_approved_at, ai_monthly_credits FROM plans;`

**Liberação:** só depois da homologação Efí concluída e da aprovação comercial registrada no RootAdmin, que fica auditada.

## 6. Pendências e riscos

1. **Aprovação comercial:** valores dos ciclos, preço e regra das vagas adicionais (sem proporcional), franquia e pacotes de IA.
2. **Homologação Efí (etapa separada):** credenciais de homologação, domínio público com HTTPS para o webhook e cenários no simulador.
3. **Cobrança complementar das vagas:** não existe. Quem precisa de vaga a mais no meio de um ciclo anual espera a renovação ou troca usuários. Pode ser uma limitação comercial; uma cobrança proporcional exige regra aprovada e homologação.
4. **Provedor de IA:** não integrado; os tokens e o custo são estimados.
5. **Pagamento das compras de créditos pela Efí:** não integrado (só confirmação no RootAdmin).
6. **Deploy acumulado** (PANELS-02 a BILLING-01.3) e as dependências de SMTP, worker, scheduler e `APP_URL`.
7. Variáveis obsoletas de planos no `config/platform.php`.
8. A conta root local de homologação ficou desativada. Pode ser excluída numa limpeza futura do banco local.
