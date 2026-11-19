# VETORSMART-RELEASE-11.0 — Consolidação Git, integridade financeira e preparação de release

Data: 19/11/2026. Ambiente: desenvolvimento local (WSL), `gateway/vetorsmart/`.

- **Sem push e sem deploy.**
- **Sem acesso à VPS.**
- **Nenhuma migration em produção.**
- **Nenhuma chamada de cobrança à Efí.**
- **Compra de créditos não ativada.**
- **Nenhuma conta administrativa criada.**
- Landing, portal regional e sites individuais não foram tocados.

## 1. Resumo executivo

A release candidata `main@<commit desta documentação>` reúne:
- as etapas já publicadas, mas ainda não implantadas: PANELS-02, 03 e 04;
- as etapas locais: BILLING-01, 01.1, 01.2 e 01.3;
- dois ajustes desta etapa.

**Situação de cada critério do correio:**

| Critério | Situação |
|---|---|
| Testes | 678 de 678 (MySQL real, concorrência), PHPStan, Pint, `tsc` e build sem erro |
| Visual de "Minha Assinatura" | 8 de 8 |
| Integridade financeira | Auditada sem defeito comprovado |
| Travas de cobrança | As quatro são fechadas por padrão e agora têm testes para configuração ausente ou inválida |
| Migrations | As 14 pendentes foram confirmadas pelo Git, contra a última versão de produção conhecida |

**Parecer: APROVADO COM RESTRIÇÕES.** A implantação depende de uma autorização posterior e das condições prévias da seção 9.

## 2. Estado do Git

| | Inicial | Final |
|---|---|---|
| Branch | `main` (rastreando `origin/main`) | `main` |
| HEAD | `3427e74` | commit `docs(release)` desta etapa (sobre `3896405`) |
| `origin/main` | `6468d1c` (PANELS-04) | `6468d1c`, sem push |
| Commits locais não publicados | 6 (BILLING-01 a 01.2) | 12 |
| Pendente sem commit | BILLING-01.3 (6 arquivos alterados, 2 novos) | nada do projeto |
| Fora dos commits | `correio.md`, `executed.md`, `pnpm-lock.yaml` | os mesmos |

**Diagnóstico:**

- O repositório é exclusivo do VetorSmart (raiz `gateway/vetorsmart`, remoto `brasilgb/vetorsmart`); não há alterações de outros projetos misturadas.
- Os commits `78fa693` (14 arquivos) e `3427e74` (2 documentos) da BILLING-01.2 estão íntegros e na sequência.
- **Última versão de produção conhecida** (pelos relatórios, sem acessar a VPS): `1ec0a5c`, implantada na WILDCARD-01.x. Desde ela:
  - 157 arquivos mudaram;
  - foram criadas exatamente as 14 migrations da seção 6.3;
  - não houve mudança em `composer.json`, `composer.lock` nem `package.json`;
  - mudaram as configurações `bootstrap/app.php`, `config/assistant.php`, `config/fortify.php`, `config/mail.php`, `config/platform.php` e `routes/console.php`.
- **`pnpm-lock.yaml`:** alterado localmente sem nenhuma mudança no `package.json` (só a resolução interna do `@babel/core`). Não é necessário ao release; foi preservado e ficou fora dos commits.
- Nenhum `reset`, `clean`, `rebase` ou `amend`. Os commits históricos não foram reescritos.

## 3. Commits consolidados

**Nesta etapa:**

| Commit | Tipo | Descrição |
|---|---|---|
| `69b6457` | Implementação e correção | `fix(billing)`: vagas adicionais só entram em operação após o pagamento (BILLING-01.3) |
| `0fc4835` | Testes | `test(billing)`: regressão de vagas, concorrência e permissões financeiras (BILLING-01.3) |
| `1952676` | Documentação | `docs(release)`: registra BILLING-01.3 e checklist de implantação |
| `41f87cd` | Configuração | `chore(config)`: remove planos obsoletos do `config/platform.php` |
| `3896405` | Testes | `test(billing)`: travas de cobrança fechadas por padrão |
| (este) | Documentação | `docs(release)`: registra a RELEASE-11.0 |

**Composição completa do release** (de `1ec0a5c`, em produção, até o HEAD), em ordem:

1. **Publicados, não implantados:**
   - `b411400` docs WILDCARD;
   - `b1b1bfd` PANELS-02 e `4424cb1` docs;
   - `32daf42` PANELS-03 e `58da0e0` docs;
   - `ef89f9a` PANELS-04 e `6468d1c` docs.
2. **Locais:**
   - `1abad90` BILLING-01 e `fb7ce62` docs;
   - `dc0341f` BILLING-01.1 e `a06ed7d` docs;
   - `78fa693` BILLING-01.2 e `3427e74` docs;
   - `69b6457`, `0fc4835` e `1952676` (BILLING-01.3);
   - `41f87cd`, `3896405` e o docs desta etapa (RELEASE-11.0).

## 4. Arquivos alterados nesta etapa

**BILLING-01.3:**
- `app/Services/Billing/SeatService.php`;
- `app/Services/Billing/SubscriptionService.php`;
- `app/Http/Controllers/Tenant/SubscriptionController.php`;
- `resources/js/pages/tenant/subscription/index.tsx`;
- `tests/Feature/Billing/SeatTest.php` e `BillingConcurrencyTest.php`;
- `tests/Feature/RootAdmin/BillingPermissionsTest.php`;
- `docs/VETORSMART-BILLING-01.3.md`.

**RELEASE-11.0:**
- `config/platform.php`: removidos os blocos `plans`, `additional_user_monthly_price_cents` e `billing_cycles`;
- `tests/Feature/Billing/BillingLocksTest.php` (novo);
- `docs/VETORSMART-RELEASE-11.0.md`.

**Nenhuma regra financeira, preço, franquia ou tela foi alterada nesta etapa.**

## 5. Resultado dos testes

| Verificação | Resultado |
|---|---|
| Pest completo, MySQL real, `VETORSMART_CONCURRENCY=1` | **678 de 678**, 3999 asserções. São os 674 da BILLING-01.3 mais os 4 novos das travas |
| Autorização financeira (`BillingPermissionsTest`) | 35 de 35 |
| Contratos e renovações (`SubscriptionLifecycleTest`, `BillingIntervalTest`, `EfiBillingTest`) | 10, 15 e 22, todos aprovados |
| Vagas adicionais (`SeatTest`) | 16 de 16 |
| Créditos e idempotência (`AiCreditTest`) | 16 de 16 |
| Travas de cobrança (`BillingLocksTest`) | 4 de 4 |
| Concorrência (`BillingConcurrencyTest`, `MysqlConcurrencyTest`) | 6 e 9, todos aprovados |
| Isolamento multi-tenant | Coberto em 11 arquivos da suíte, todos aprovados |
| PHPStan | 0 erros |
| Pint | ok |
| TypeScript (`tsc`) | ok |
| Build de produção (`vp build`) | ok |
| Visual de "Minha Assinatura" | **8 de 8** (Chromium e Firefox × desktop e celular × claro e escuro): 0 violações de axe (inclui contraste), sem rolagem horizontal e sem erros de JavaScript |

## 6. Auditoria de integridade financeira e segurança

### 6.1 Assinaturas

- **Plano e ciclos:** plano único "VetorSmart Completo", com mensal, semestral e anual a preços provisórios (`plans`).
- **Teste:** 14 dias (`trial_days`), iniciado no cadastro.
- **Renovação:** a rotina `billing:run` abre a cobrança 7 dias antes do fim do período.
- **Cancelamento:**
  - pela empresa, ao fim do período;
  - pelo RootAdmin, imediato e auditado.
- **Inadimplência:** carência de 5 dias, depois suspensão financeira e reativação pelo pagamento. Separada da suspensão administrativa.
- **Contratos existentes:** preço do ciclo, preço do adicional e promoção ficam congelados na primeira cobrança. Empresas anteriores à cobrança, sem assinatura, mantêm acesso livre. **Na produção, todas as empresas atuais estão nesse caso:** nenhuma assinatura é criada para elas pelo deploy.

### 6.2 Usuários adicionais

- 3 incluídos.
- Cobrança só pelas vagas **contratadas** (`extra_seats`); usuários inativos não contam.
- Troca de usuários gratuita.
- **Nenhuma vaga entra em operação antes do pagamento:**
  - no teste sem cobrança aberta, a vaga vale na hora e entra na primeira cobrança;
  - nos demais casos, a alteração é agendada e ativada em `periodPaid`.
- Cobrança aberta nunca é alterada.
- Lock da empresa nas alterações de vagas e ativações de usuários; pedido repetido é idempotente (testes em MySQL real).

### 6.3 Créditos de IA

- Saldo sob lock, conciliado com o histórico.
- Isolamento por empresa.
- Histórico com modelo, operação, tokens e custo.
- Débito único por referência.
- Estorno só do não usado.
- Confirmação dupla credita uma vez (MySQL real).
- **Compra desativada** e confirmação **só no RootAdmin** (assinante recebe 403).
- Franquia e pacotes **não definidos**.

### 6.4 Segurança

- **Isolamento:** as rotas da empresa leem a empresa do contexto, nunca da URL; os testes de isolamento passam.
- **Autorização:** as 17 rotas financeiras do RootAdmin estão sob `can:platform-admin`. Visitante vai ao login e proprietário recebe 403, sem efeito colateral.
- **Auditoria:**
  - plano, regras, pacotes, Efí, condições, vagas, ciclo, baixas e compras registram valor anterior e novo;
  - credenciais aparecem só como "alterado".
- **Duplicidade de cobrança:**
  - índice único por assinatura e período;
  - lock da assinatura;
  - webhooks idempotentes e sempre confirmados na Efí.
- **Credenciais:**
  - criptografadas no banco (`encrypted`) e ocultas na serialização;
  - somente escrita na tela;
  - fora do `.env`;
  - **nenhuma credencial nos commits** (diffs verificados antes de cada commit; só valores fictícios de teste).
- **Contas administrativas:** nenhuma criada. A conta temporária local da BILLING-01.2 segue desativada.

**Defeitos:** nenhum defeito comprovado que comprometa o release. O único achado foi falta de cobertura: as travas não tinham testes para configuração ausente ou inválida. O comportamento já era fechado, e os testes foram acrescentados.

### 6.5 Configuração obsoleta (seção 5 do correio)

Os blocos `plans` ("Profissional" e "Premium"), `additional_user_monthly_price_cents` e `billing_cycles` do `config/platform.php`, e as variáveis `PLATFORM_PLAN_*`, `PLATFORM_ADDITIONAL_USER_MONTHLY_CENTS` e `PLATFORM_CYCLE_*`, foram **removidos** depois de confirmadas as quatro condições:

1. Nenhuma referência no código, nem por chave, nem pelo array inteiro.
2. Os contratos usam `plans` e `subscriptions` no banco.
3. Nenhuma migration, job, teste ou tela as usa.
4. Os 678 testes passaram depois da remoção.

Se essas variáveis existirem no `.env` da VPS, passam a ser ignoradas e podem ser apagadas depois do deploy.

## 7. Situação financeira

- **Preços provisórios, não aprovados:** mensal R$ 139,90, semestral R$ 719,40, anual R$ 1.318,80, adicional R$ 19,90 por mês, 14 dias de teste.
- **Efí:** ambiente de homologação e sem credenciais.
- **Créditos de IA:** compra desligada, sem franquia e sem pacotes; nenhum provedor de IA integrado.
- **Cobrança real:** impossível no estado em que o release sobe (seção 8).

## 8. Travas de cobrança

**Comportamento fechado por padrão:** configuração ausente ou inválida nunca autoriza cobrança. **Nenhuma trava foi habilitada.**

| # | Trava (nome e localização) | Com a trava "desligada" (liberada) | Configuração ausente ou inválida | Teste que comprova | Estado seguro mantido |
|---|---|---|---|---|---|
| 1 | **Ambiente da Efí**: `billing_settings.efi_environment`; `BillingSetting::isProduction()`; `EfiPixClient::baseUrl()` e `EfiChargesClient::baseUrl()` | Só com o valor exato `production` (e confirmação explícita no RootAdmin) as chamadas vão para `pix.api.efipay.com.br` e `cobrancas.api.efipay.com.br` | Sem linha de configuração: `BillingSetting::current()` recria em `homologation`. Qualquer outro valor (por exemplo, `PRODUCAO`) usa homologação | `BillingLocksTest`: configuração ausente e ambiente inválido. `EfiBillingTest`: endereço de homologação e confirmação para produção | A migration cria em `homologation`. A tela exige "Entendo que o ambiente de produção gera cobranças reais" |
| 2 | **Aprovação comercial**: `plans.prices_approved_at`; `Plan::pricesApproved()`; guarda em `ChargeService::issue()` | Com a data preenchida e ambiente de produção, a cobrança é emitida | Vazio ou nulo, em produção: a cobrança fica "Em emissão" com "Valores comerciais ainda não aprovados", sem tentativa nem requisição. A guarda também vale para a rotina diária e para a sincronização no RootAdmin, que passam por `issue()` | `BillingIntervalTest`: nenhuma cobrança emitida em produção sem aprovação | A migration deixa vazio. Qualquer mudança de preço revoga a aprovação (testado), com auditoria (testado) |
| 3 | **Credenciais da Efí**: `billing_settings.efi_pix_*` e `efi_charges_*` (criptografadas); `BillingSetting::pixConfigured()` e `chargesConfigured()`; `EfiException::notConfigured` | Com credenciais válidas, a Efí autentica e emite | Ausentes: nenhuma requisição sai, e a cobrança fica pendente com "credenciais não configuradas" | `BillingLocksTest`: sem credenciais | A produção não tem credenciais. Elas só entram pelo RootAdmin (somente escrita) |
| 4 | **Compra de créditos de IA**: `billing_settings.ai_credit_purchases_enabled`; guarda em `AiCreditPurchaseService::request()` | Com `true`, a compra fica "Aguardando pagamento" (sem cobrança na Efí), e só o RootAdmin confirma | Ausente, nulo ou `false`: recusada, sem registro | `AiCreditTest`: compra fechada. `BillingLocksTest`: chave ausente. `BillingPermissionsTest`: confirmação restrita | A migration grava `false` e não cria pacotes nem franquia |

**Para uma cobrança real**, as travas 1, 2 e 3 precisam ser liberadas juntas e por ação explícita no RootAdmin. A trava 4 é independente.

**Conferência após o deploy** (somente leitura):
- `SELECT efi_environment, ai_credit_purchases_enabled, efi_pix_client_id IS NULL AS sem_pix, efi_charges_client_id IS NULL AS sem_cobrancas FROM billing_settings;` (o esperado é `homologation`, `0`, `1`, `1`);
- `SELECT prices_approved_at, ai_monthly_credits FROM plans;` (o esperado é `NULL`, `NULL`);
- `SELECT COUNT(*) FROM ai_credit_packages;` (o esperado é `0`).

## 9. Plano e checklist de implantação

**Não executado.** Revisa e amplia o checklist da BILLING-01.3.

### 9.1 Branch, HEAD e commits

`main` no commit `docs(release)` da RELEASE-11.0, com os commits da seção 3, a partir de `1ec0a5c` (produção). Antes, é preciso autorização para o push à `origin/main`.

### 9.2 Dependências entre versões

Sobem juntas e em ordem: PANELS-02 → PANELS-03 → PANELS-04 → BILLING-01 → 01.1 → 01.2 → 01.3 → RELEASE-11.0.

- A BILLING-01 depende da PANELS-04 (avisos de cobrança).
- A BILLING-01.2 depende da 01.1 (preço do adicional).
- **Pré-requisito da PANELS-02/03:** e-mail funcionando, porque o cadastro exige confirmação.
- Sem dependências novas de Composer ou npm. O build usa o `package.json` e o lockfile já publicados.

### 9.3 As 14 migrations pendentes, em ordem

Confirmadas pelo Git (`git diff --diff-filter=A 1ec0a5c HEAD -- database/migrations`).

| # | Migration | Etapa | Operação | Risco |
|---|---|---|---|---|
| 1 | `2026_11_18_134100_add_is_homolog_to_tenants_table` | PANELS-02 | `ALTER tenants` (coluna e índice) e `UPDATE` das empresas `[HOMOLOG]%` | Bloqueio breve de `tenants` (tabela pequena). Reversível |
| 2 | `2026_11_18_134101_mark_existing_users_as_verified` | PANELS-02 | `UPDATE users SET email_verified_at = now()` onde nulo | **Irreversível** (`down` vazio): só se desfaz pelo backup. Necessária para os usuários atuais não serem barrados pela confirmação de e-mail |
| 3 | `2026_11_18_160243_create_tenant_notifications_table` | PANELS-04 | `CREATE` | Nenhum |
| 4 | `2026_11_18_160244_create_notification_deliveries_table` | PANELS-04 | `CREATE` | Nenhum |
| 5 | `2026_11_18_160245_add_notification_preferences_to_tenant_users_table` | PANELS-04 | `ALTER tenant_users` (JSON anulável) | Bloqueio breve (tabela pequena). Reversível |
| 6 | `2026_11_18_163905_create_plans_table` | BILLING-01 | `CREATE` e `INSERT` do plano (sem preço) | Nenhum |
| 7 | `2026_11_18_163906_create_billing_settings_table` | BILLING-01 | `CREATE` e `INSERT` (homologação) | Nenhum |
| 8 | `2026_11_18_163908_create_subscriptions_table` | BILLING-01 | `CREATE` | Nenhum |
| 9 | `2026_11_18_163909_create_subscription_events_table` | BILLING-01 | `CREATE` | Nenhum |
| 10 | `2026_11_18_163910_create_billing_charges_table` | BILLING-01 | `CREATE` | Nenhum |
| 11 | `2026_11_18_163911_create_billing_webhook_events_table` | BILLING-01 | `CREATE` | Nenhum |
| 12 | `2026_11_18_182113_add_billing_cycles_to_plans_and_subscriptions` | BILLING-01.1 | `ALTER` em `plans`, `subscriptions` e `billing_charges`, inclusive `dropColumn` em `plans`; preços provisórios não aprovados | Os `dropColumn` atingem só `plans`, criada neste mesmo release (sem dado de produção). Reversível |
| 13 | `2026_11_18_182114_create_ai_credit_tables` | BILLING-01.1 | `CREATE` de 4 tabelas e `ALTER billing_settings` | Nenhum. Reversível |
| 14 | `2026_11_18_194419_add_extra_seats_to_subscriptions_table` | BILLING-01.2 | `ALTER subscriptions` e preenchimento das vagas | Sem assinaturas em produção, o preenchimento é vazio. Reversível |

**Nenhuma migration apaga tabela ou dado existente em produção.** A única irreversível é a 2, uma atualização de dados.

### 9.4 Verificação prévia de compatibilidade

Somente leitura, antes do deploy:
- `SELECT migration FROM migrations ORDER BY id DESC LIMIT 5;`: a última deve ser a da WILDCARD-01, e nenhuma das 14 deve existir.
- `SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL;`: quantas contas a migration 2 marcará como verificadas.
- `SELECT id, name FROM tenants WHERE name LIKE '[HOMOLOG]%';`: quais empresas serão marcadas como homologação. Elas entram no expurgo de homologação, então é preciso conferir que nenhuma é real.
- Conferir que não existem tabelas com os nomes novos (`plans`, `subscriptions`, `ai_credit_*` e outras).
- Versão do MySQL compatível com coluna JSON (5.7 ou superior / 8).

### 9.5 Configurações de ambiente

Apenas os nomes; os valores ficam na VPS:
- `APP_ENV=production` e `APP_DEBUG=false`.
- `APP_KEY`: **não trocar**, porque criptografa as credenciais da Efí. Se mudar, preserve a antiga em `APP_PREVIOUS_KEYS`.
- `APP_URL=https://vetorsmart.com.br`: links da fila e assinatura de URLs.
- `PLATFORM_PUBLIC_URL`, `PLATFORM_CENTRAL_HOST`, `PLATFORM_TENANT_BASE_DOMAIN`, `PLATFORM_PUBLIC_SCHEME` e `PLATFORM_TRUSTED_PROXIES`.
- `PLATFORM_SUBDOMAIN_SITES`: manter como está.
- `DB_*`, `SESSION_*`, `CACHE_STORE`, `QUEUE_CONNECTION` e `REDIS_*`, se usado.
- `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS` e `MAIL_FROM_NAME`.
- `ASSISTANT_PROVIDER=none` e `ASSISTANT_RETENTION_DELETION=false`.
- Obsoletas e agora ignoradas: `PLATFORM_PLAN_*`, `PLATFORM_ADDITIONAL_USER_MONTHLY_CENTS` e `PLATFORM_CYCLE_*`.
- **As credenciais da Efí não vão no `.env`.**

### 9.6 Filas, scheduler e SMTP

- **Worker** (Supervisor ou systemd, ou serviço Docker equivalente na VPS): `php artisan queue:work --tries=3 --max-time=3600`. Executar `php artisan queue:restart` após o deploy.
- **Scheduler:** `* * * * * php artisan schedule:run`. Tarefas: `billing:run` às 05:30 e `assistant:purge` às 06:30 (só relatório), ambas com `withoutOverlapping`.
- **SMTP:** com SPF, DKIM e DMARC. Testar a confirmação de cadastro e a recuperação de senha pela fila **antes** de abrir o cadastro.

### 9.7 Backup consistente

1. Janela de manutenção: `php artisan down --retry=60` (opcional, mas recomendado, porque as migrations 1, 2 e 5 alteram tabelas em uso).
2. Banco: `mysqldump --single-transaction --routines --triggers --hex-blob` do banco do VetorSmart, com checksum (`sha256sum`) e cópia fora da VPS.
3. Arquivos:
   - `.env` em cópia protegida;
   - `storage/app`, com os arquivos enviados pelas empresas;
   - o diretório do release anterior, ou o commit `1ec0a5c` anotado;
   - a configuração do worker e do cron.
4. Registrar `php artisan migrate:status` antes.

### 9.8 Restauração testável

- **Antes do deploy:** restaurar o dump num banco temporário separado e comparar a contagem das tabelas principais (`tenants`, `users`, `tenant_users`, `customers`, `appointments`). Apagar o banco temporário depois.
- **Em caso de necessidade:** `php artisan down`, depois restaurar o dump no banco do VetorSmart, voltar o código para `1ec0a5c`, executar `php artisan optimize:clear` e reiniciar o worker. Por fim, `php artisan up`.

### 9.9 Manutenção e retorno à versão anterior

**Rollback de código não é rollback de dados.** As migrations têm `down`, exceto a 2.

| Situação | Procedimento |
|---|---|
| Falha antes das migrations | Voltar o código para `1ec0a5c` e executar `optimize:clear`. Nada mais |
| Falha durante ou depois das migrations, **sem uso real** (nenhuma empresa nova nem assinatura) | `php artisan migrate:rollback --step=N` (N = migrations aplicadas, conferido em `migrate:status`), depois voltar o código. A migration 2 não se desfaz pelo rollback; se for indispensável desfazê-la, restaurar o dump |
| Falha depois de uso real (cadastros, assinaturas, notificações) | Avaliar antes de reverter: o `down` das tabelas novas **apaga** os dados criados depois do deploy. Preferir correção para frente. Se a reversão for inevitável, exportar as tabelas novas antes e restaurar o dump |
| Mudança incompatível de esquema | Não há mudança incompatível com o código antigo: só colunas novas com padrão ou anuláveis e tabelas novas. O código `1ec0a5c` funciona com o esquema novo, então a volta só de código é possível sem desfazer as migrations, e é a forma mais segura de voltar |

### 9.10 Homologação após o deploy

1. `php artisan migrate:status`: as 14 rodaram.
2. `php artisan about`: produção, sem debug; `config:show app.url` correto.
3. Login, painel, agenda, solicitações e "Meu Site" de uma empresa existente funcionam, e o acesso dela continua livre (sem assinatura).
4. Cadastro de teste (`[HOMOLOG]`): o e-mail de confirmação chega pela fila; a empresa entra em teste de 14 dias; "Minha Assinatura" abre.
5. Central de notificações e e-mails operacionais com o worker ativo.
6. RootAdmin (com conta legítima): Visão Geral, Assinaturas e "Plano e Efí" mostram "Valores provisórios", "Homologação" e compra desligada.
7. `php artisan schedule:list` e uma execução manual de `php artisan billing:run`: sem emissão.
8. Consultas da seção 8.
9. Logs sem erros novos; outros SaaS da VPS operando.
10. Remover a empresa `[HOMOLOG]` de teste pelo expurgo de homologação.

### 9.11 Evidências de que cobranças e compras continuarão bloqueadas

Seção 8: as quatro travas estão fechadas no estado criado pelas migrations, cada uma é coberta por teste automatizado e há consultas de conferência após o deploy. **Nenhuma ação do deploy liberaria uma trava.** Liberar exige uma ação explícita, auditada, no RootAdmin.

## 10. Riscos e pendências

| # | Item | Gravidade |
|---|---|---|
| 1 | SMTP de produção não confirmado. Sem e-mail, novos cadastros não confirmam a conta e a recuperação de senha não chega | **Condição prévia do deploy** |
| 2 | Migration 2 irreversível (marca usuários existentes como verificados) | Baixa, intencional. Só se desfaz pelo backup |
| 3 | Backup e restauração ainda não testados na VPS | Condição prévia do deploy |
| 4 | RootAdmin nunca homologado em navegador em produção (sem conta legítima disponível para teste) | Média. Fazer no passo 6 da homologação após o deploy |
| 5 | Aprovação comercial: preços, vagas, franquia e pacotes | Não crítica (as travas bloqueiam) |
| 6 | Homologação da Efí: credenciais, domínio público e simulador | Etapa separada |
| 7 | Cobrança complementar de vagas inexistente: vaga extra só a partir da próxima cobrança paga | Comercial |
| 8 | Provedor de IA não integrado; pagamento de créditos sem integração com a Efí | Funcionalidade futura |
| 9 | `pnpm-lock.yaml` local alterado (não necessário). Decidir se descarta a alteração local | Baixa |
| 10 | Conta root local de homologação desativada (só no banco local) | Baixa. Pode ser excluída numa limpeza local |

## 11. Procedimento de rollback e recuperação

Resumo da seção 9.9:
1. Manter o dump verificado e o commit `1ec0a5c`.
2. Preferir **voltar só o código**: o esquema novo é compatível com o código antigo.
3. Usar `migrate:rollback` só sem uso real.
4. Restaurar o dump quando for preciso desfazer a migration 2 ou dados.
5. Depois de qualquer retorno: `optimize:clear`, reinício do worker, conferência do cron e `php artisan up`.

## 12. Parecer final

**APROVADO COM RESTRIÇÕES.**

Em ordem:
- Git íntegro e consolidado.
- Integridade financeira auditada, sem defeito comprovado.
- Travas fechadas por padrão e testadas.
- Configuração obsoleta removida com segurança.
- 678 de 678 testes e 8 de 8 verificações visuais.

As restrições, não críticas e documentadas:
- condições prévias do deploy (SMTP, teste de restauração do backup, autorização de push);
- homologação do RootAdmin em produção.

**A implantação depende de autorização posterior.** A RELEASE-11.1 e a homologação da Efí não foram iniciadas.
