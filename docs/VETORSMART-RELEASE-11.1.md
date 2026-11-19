# VETORSMART-RELEASE-11.1 — Pré-implantação, auditoria de produção e validação de recuperação

Data: 19/11/2026. Base: RELEASE-11.0, HEAD `6530ddd`. Ambiente: desenvolvimento local (WSL).

- Sem push, merge, rebase, reset ou deploy.
- Nenhuma migration executada.
- Nenhum dado ou configuração de produção alterado.
- Nenhum usuário criado ou alterado.
- Efí e compras de créditos não habilitadas.

## Parecer final

**BLOQUEADO para solicitar a implantação.** A preparação do release está íntegra (Git, código e testes), mas há três impedimentos:

1. **Segurança: a migration `mark_existing_users_as_verified` valida e-mails sem comprovação** (seção 4.2). O correio manda interromper a aprovação nesse caso; as alternativas seguras estão na seção 4.2.
2. **Operação: empresas cadastradas depois do deploy seriam suspensas por volta do 19º dia sem conseguir pagar** (seção 6.3). As travas fecham a cobrança, mas a rotina de assinaturas continua aplicando o fim do teste e a suspensão.
3. **Inspeção da VPS não realizada:** o acesso SSH à produção foi **negado pela política de permissões desta sessão** e não foi contornado. Commit em produção, containers, recursos atuais, filas, SMTP e backups ficaram **PENDENTES** de comprovação (seção 3). Também ficou pendente o ensaio das migrations em **MySQL 8.4** (o banco local é MariaDB 11.8, seção 4.4).

O impedimento 3 não é um risco do código; é falta de evidência. Os impedimentos 1 e 2 exigem uma decisão e uma pequena alteração numa próxima etapa.

## 1. Situação Git

| Verificação | Resultado |
|---|---|
| Branch / HEAD | `main` / **`6530ddd`** (RELEASE-11.0) |
| Commits locais | **12** sobre a `origin/main` `6468d1c`. Nenhum commit remoto ausente localmente (0 atrás) |
| Histórico | `origin/main` é ancestral do HEAD: a publicação futura será **fast-forward**, sem merge |
| Integridade | `git fsck` sem erros. Suíte completa no HEAD: **678 de 678** (MySQL local, concorrência). PHPStan, Pint, `tsc` e build validados na RELEASE-11.0, sem mudança de código desde então |
| Credenciais | Varredura do diff `origin/main..HEAD` por chaves privadas, tokens, senhas e e-mails pessoais: **nenhuma ocorrência**. Só valores fictícios de teste |
| Alterações não commitadas | `correio.md`, `executed.md` (fora dos commits por regra), `pnpm-lock.yaml` e este relatório |
| Versões incluídas | PANELS-02, 03 e 04 (publicadas, não implantadas) e BILLING-01, 01.1, 01.2, 01.3 e RELEASE-11.0 (locais). Nada faltando entre `1ec0a5c` (produção) e o HEAD |
| Dependências | Nenhuma mudança em `composer.json`, `composer.lock` ou `package.json` desde `1ec0a5c` |

### `pnpm-lock.yaml` (não modificado nesta etapa)

- **O build de produção não usa o pnpm.** O `Dockerfile` roda `yarn install --frozen-lockfile` e `yarn build`.
- O `yarn.lock` está coerente com o `package.json`: os dois foram atualizados juntos em `5ead1ca` e declaram `@babel/core@^7`.
- O `pnpm-lock.yaml` **commitado está defasado**: não registra `@babel/core ^7`, que o `package.json` declara, e resolve `@babel/core` 8.1.0.
- A alteração local apenas o realinha (`@babel/core` 7.29.13).

**Recomendação:** **preservar** a alteração local e consolidá-la num commit de manutenção separado (`chore(deps): sincroniza pnpm-lock com package.json`), fora do release.
- Sem efeito em produção.
- Avaliar depois manter um único gerenciador: hoje existem `package-lock.json`, `pnpm-lock.yaml` e `yarn.lock`.

## 2. Divergências entre o código local e a produção (sem acesso à VPS)

**Evidência por rotas públicas** (requisições GET anônimas):

| Rota | Produção | Origem | Conclusão |
|---|---|---|---|
| `/`, `/login`, `/register`, `/forgot-password` | 200 | anterior | ativas |
| `/dashboard` | 302 → `/login` | anterior | ativa |
| `/up` (saúde) | 200 | anterior | ativa |
| `/email/verify` | **404** | PANELS-02 | não implantada |
| `/notificacoes` | **404** | PANELS-04 | não implantada |
| `/assinatura`, `/rootadmin/assinaturas`, `/api/webhooks/efi/…` | **404** | BILLING-01 | não implantadas |
| `https://teste.vetorsmart.com.br/` | 404 | WILDCARD | subdomínios desligados (`PLATFORM_SUBDOMAIN_SITES`) |

**Conclusão:** a produção está numa versão **anterior à PANELS-02**, compatível com o último deploy registrado, **`1ec0a5c`** (WILDCARD-01.5). Diferença para o HEAD: 157 arquivos, 14 migrations, nenhuma dependência nova. **O commit exato em produção continua PENDENTE de confirmação na VPS** (`git -C … rev-parse HEAD` e a tag das imagens).

## 3. Inspeção da VPS

**Não realizada.** A conexão SSH somente leitura com `srv1990862` foi recusada pela política de permissões desta sessão. Não houve contorno.

As inspeções anteriores foram feitas por sessões executadas na própria VPS. Os dados abaixo vêm dos relatórios mais recentes (RELEASE-10.3, de 17/11, e WILDCARD-01.5, de 18/11) e **precisam ser revalidados**.

| Item | Último dado documentado | Situação |
|---|---|---|
| Commit em produção | `1ec0a5c` (WILDCARD-01.5) | **PENDENTE** (coerente com as rotas públicas) |
| Containers | 24 de 24 saudáveis. VetorSmart = `vetorsmart`, `vetorsmart-worker` (`queue:work`) e `vetorsmart-scheduler` (`schedule:work`) | **PENDENTE** |
| PHP / Laravel / MySQL | 8.4.27 / 13.42.0 / **MySQL 8.4** | **PENDENTE**. Local: PHP 8.4.24, Laravel 13.42.0, **MariaDB 11.8** |
| CPU / RAM / swap | 4 vCPU. VetorSmart com 114 MiB (web 42, worker 36, scheduler 36). Margem de deploy de cerca de 0,4% da memória | **PENDENTE** |
| Disco | 73 de 197 GB (39%); imagens 38,4 GB; cache de build 23 GB; `backups/` 523 MB | **PENDENTE** |
| MySQL | buffer pool 512 MiB, máximo de 14 de 151 conexões, `slow_query_log` ligado, 0 consultas lentas | **PENDENTE** |
| Filas / workers | `queue:work database --tries=3`, saudável | **PENDENTE** (jobs pendentes e falhos) |
| Scheduler | `schedule:work`; `assistant:purge` às 06:30 (simulação) | **PENDENTE** |
| Redis | 18 MiB, praticamente sem uso | **PENDENTE** |
| Armazenamento | logs em `volumes/vetorsmart-logs/{web,worker,scheduler}` | **PENDENTE** (permissões) |
| HTTPS / domínio | **Verificado agora:** `https://vetorsmart.com.br` 200; `http://` e `www` → 301 para o apex; `/up` 200. Certificado válido de 11/11/2026 a 09/02/2027, com `vetorsmart.com.br` e `www` | ok. Observação: a rede local intercepta TLS (emissor visto como "Sophos SSL CA"), então a cadeia real deve ser conferida a partir da VPS ou de outra rede |
| SMTP | não registrado para o VetorSmart. Os outros SaaS usam o SMTP institucional `smtp.hostinger.com:465` | **PENDENTE e impeditivo** (seção 6) |

### Comandos de inspeção somente leitura para executar na VPS

Para a sessão autorizada, em `/opt/infra-abrasil`:

```
git -C gateway/vetorsmart rev-parse --short HEAD; git -C gateway/vetorsmart status --short | head
docker compose ps; docker stats --no-stream; free -m; df -h / /opt; uptime
docker compose exec -T vetorsmart php -v; docker compose exec -T vetorsmart php artisan --version
docker compose exec -T vetorsmart php artisan migrate:status | tail -20
docker compose exec -T vetorsmart php artisan queue:failed | tail; docker compose exec -T vetorsmart php artisan schedule:list
docker compose exec -T vetorsmart php artisan config:show mail.default; … config:show mail.mailers.smtp.host / port / scheme  (sem a senha)
docker compose exec -T mysql mysql -e "select version(); show status like 'Threads_connected'"   (credenciais pelo ambiente)
SELECT COUNT(*) FROM jobs; SELECT COUNT(*) FROM failed_jobs;   (somente leitura)
ls -la backups/ | tail; sha256sum -c backups/<último>/*.sha256
```

Nenhum desses comandos altera dados ou serviços.

## 4. Auditoria das 14 migrations

### 4.1 Tabela de riscos

As migrations rodam em ordem, num único `migrate --force`. A ordem cronológica já respeita as dependências: a 8 depende da 6 (`plan_id`); as 9, 10 e 14 dependem da 8; a 12 depende das 6, 8 e 10; a 13 depende da 7.

| # | Migration (finalidade) | Tabelas | Bloqueio / compatibilidade | Dados existentes | Reversão | Recuperação | Janela |
|---|---|---|---|---|---|---|---|
| 1 | `134100_add_is_homolog_to_tenants_table`: marca empresas de homologação | `tenants` (coluna e índice) e `UPDATE` de `[HOMOLOG]%` | `ALTER` com DDL online no MySQL 8.4 (`ADD COLUMN` e `ADD INDEX`); tabela pequena | Marca como homologação as empresas `[HOMOLOG]%`, que entram no expurgo. **Conferir a lista antes** | `down` remove coluna e índice | rollback ou dump | curta (recomendada) |
| 2 | `134101_mark_existing_users_as_verified` | `users` (`UPDATE`) | sem DDL | **Marca todas as contas não verificadas** | **Irreversível** (`down` vazio) | só pelo dump | — **ver 4.2: impeditivo** |
| 3 | `160243_create_tenant_notifications_table` | nova | nenhum | nenhum | `down` | rollback | não |
| 4 | `160244_create_notification_deliveries_table` | nova | nenhum | nenhum | `down` | rollback | não |
| 5 | `160245_add_notification_preferences_to_tenant_users_table` | `tenant_users` (JSON anulável) | `ALTER` curto | nenhum | `down` | rollback | curta |
| 6 | `163905_create_plans_table` | nova e `INSERT` | nenhum | nenhum | `down` | rollback | não |
| 7 | `163906_create_billing_settings_table` | nova e `INSERT` (homologação) | nenhum | nenhum | `down` | rollback | não |
| 8 | `163908_create_subscriptions_table` | nova (chaves para `tenants` e `plans`) | nenhum | nenhum (empresas atuais ficam sem assinatura, com acesso livre) | `down` | rollback | não |
| 9 | `163909_create_subscription_events_table` | nova | nenhum | nenhum | `down` | rollback | não |
| 10 | `163910_create_billing_charges_table` | nova | nenhum | nenhum | `down` | rollback | não |
| 11 | `163911_create_billing_webhook_events_table` | nova | nenhum | nenhum | `down` | rollback | não |
| 12 | `182113_add_billing_cycles_to_plans_and_subscriptions` | `plans`, `subscriptions` e `billing_charges` (inclui `dropColumn` em `plans`) | tabelas criadas no próprio release, vazias | nenhum dado de produção | `down` | rollback | não |
| 13 | `182114_create_ai_credit_tables` | 4 novas e `ALTER billing_settings` | nenhum | nenhum | `down` | rollback | não |
| 14 | `194419_add_extra_seats_to_subscriptions_table` | `subscriptions` e preenchimento | tabela vazia em produção | nenhum | `down` | rollback | não |

**Compatibilidade de retorno:** o código `1ec0a5c` funciona com o esquema novo (só tabelas novas e colunas com padrão ou anuláveis). A volta só do código é possível sem desfazer as migrations.

### 4.2 `mark_existing_users_as_verified`: risco de segurança (impeditivo)

**Implementação:** `UPDATE users SET email_verified_at = now() WHERE email_verified_at IS NULL`, sem nenhum critério e sem `down`.

**Justificativa registrada (PANELS-02):** a confirmação de e-mail começa nesse release, e as contas existentes são marcadas como verificadas "para que ninguém seja bloqueado no deploy".

**Por que isso permite validação indevida:**
- Na versão em produção (`1ec0a5c`), o **cadastro público está aberto** (`Features::registration()`, confirmado também pela rota `/register` 200) e o modelo `User` **não exige confirmação de e-mail**.
- A equipe de uma empresa também cria usuários com qualquer endereço.
- Toda conta existente em produção tem `email_verified_at` nulo. A migration marcaria como "verificadas" **todas**, inclusive as cadastradas por qualquer pessoa na internet com e-mail de terceiros, com erro de digitação ou de robôs.
- A tabela `users` não tem nenhum sinal de confiança (último acesso ou origem) que permita separar as contas legítimas.

**Consequências:**
- Um fato falso e permanente ("e-mail comprovado").
- Avisos operacionais e, no futuro, cobranças enviados a endereços nunca confirmados.
- Qualquer regra futura que confie em `email_verified_at` herda o erro.
- A migration não amplia o acesso atual (hoje ninguém precisa verificar), mas grava uma comprovação que não existiu e não pode ser desfeita.

**Alternativas seguras (decisão necessária):**

| Opção | Como | Efeito |
|---|---|---|
| **A (recomendada)** | Trocar o corpo da migration: **não marcar ninguém**, exceto administradores da plataforma (`is_root_admin`). As demais contas confirmam o e-mail no próximo acesso. A tela de confirmação, o reenvio e a correção do e-mail no perfil já existem (PANELS-02) | Nenhuma validação indevida. Cada usuário atual clica uma vez no link. **Depende do SMTP** (já é condição prévia) |
| B | Marcar só contas com vínculo ativo e criadas antes de uma data de corte aprovada, **e** registrar a origem (`email_verified_via = 'legacy'`), para exigir confirmação real em ações sensíveis | Menos atrito, mas a "verificação" continua sem prova para essas contas |
| C | Manter como está | **Não recomendado:** valida contas não confiáveis de forma irreversível |

Antes de escolher, consultar na VPS (somente leitura): `SELECT COUNT(*), MIN(created_at), MAX(created_at) FROM users WHERE email_verified_at IS NULL;` e quantas dessas contas têm vínculo ativo em `tenant_users`.

### 4.3 Procedimento de recuperação

- **Sem uso real após o deploy:** `php artisan migrate:rollback --step=N` (N conferido em `migrate:status`), depois as imagens `rollback-pre-<versão>`. A migration 2 só se desfaz pelo dump.
- **Com uso real:** preferir correção para frente. O `down` das tabelas novas apaga os dados criados depois do deploy. Se a reversão for inevitável, exportar as tabelas novas e restaurar o dump.
- **Retorno só do código:** possível e preferível. O esquema novo é compatível com `1ec0a5c`.

### 4.4 Ensaio em MySQL 8.4: PENDENTE

Os testes locais rodaram em **MariaDB 11.8**, mas a produção usa **MySQL 8.4**. As diferenças relevantes para estas migrations:
- `JSON` nativo, que no MariaDB é apelido de `LONGTEXT`;
- `ALTER` online;
- comportamento de lock.

O Docker não está ativo no WSL, então o ensaio não foi possível aqui. **Antes do deploy:**
1. Restaurar o dump de produção num `mysql:8.4` descartável, sem rede e em memória (mesmo método da DEPLOY-08).
2. Rodar `php artisan migrate --pretend` e depois `migrate` contra ele, a partir de uma imagem do release.
3. Conferir as contagens. Repetir também a suíte de testes contra um MySQL 8.4.

## 5. Backup e recuperação

| Ponto | Situação |
|---|---|
| Estratégia documentada | Dumps em `/opt/infra-abrasil/backups/…`, com `.sha256` e permissão 600; imagens `rollback-pre-<versão>` para web, worker e scheduler. Último dump registrado: `vetorsmart_pre_migrate_20261118_124939.sql` (WILDCARD-01.5) |
| Abrangência | Banco do VetorSmart (dump). Arquivos: `.env`, `storage/app` (uploads), `volumes/vetorsmart-logs`, compose e Nginx (cópias nos backups anteriores) — **PENDENTE** confirmar `storage/app` no backup |
| Consistência transacional | `mysqldump --single-transaction` (InnoDB). **PENDENTE** confirmar as opções usadas |
| Espaço | `backups/` com 523 MB e disco a 39% (17/11) — **PENDENTE** revalidar |
| Integridade | `sha256sum -c` dos dumps — **PENDENTE** |
| Segurança | permissão 600, dentro da VPS — **PENDENTE** confirmar cópia fora da VPS |
| Restauração | Ensaio já feito na DEPLOY-08 e na RELEASE-10 (MySQL 8.4 descartável, sem rede, em memória, contagens iguais) — **PENDENTE repetir** com o dump pré-deploy |

**Ensaio de restauração proposto** (precisa de autorização, porque cria um container temporário na VPS):

```
docker run --rm --network none --tmpfs /var/lib/mysql -e MYSQL_ALLOW_EMPTY_PASSWORD=1 mysql:8.4
```

Carregar o dump novo, comparar as contagens de `tenants`, `users`, `tenant_users`, `customers`, `appointments` e `migrations`, e descartar o container. Nenhum dado sai da VPS.

## 6. SMTP, filas e scheduler

### 6.1 SMTP: PENDENTE e impeditivo

- O release passa a **exigir e-mail**: confirmação de cadastro (PANELS-02, e para todos os usuários atuais se for adotada a opção A da seção 4.2), recuperação de senha e avisos operacionais (PANELS-04).
- Não há registro de `MAIL_*` configurado para o VetorSmart em produção. Os outros SaaS usam o SMTP institucional (`smtp.hostinger.com:465`, SSL).
- **Verificar na VPS sem enviar:** `config:show mail.default` e o host, a porta e o esquema do SMTP. Conferir que a senha está definida, sem exibi-la, e o SPF, DKIM e DMARC do domínio remetente.
- **Envio de teste:** só com autorização e para um destinatário controlado.

### 6.2 Filas

| Ponto | Situação |
|---|---|
| Transporte e worker | `database`, `vetorsmart-worker` com `queue:work --tries=3` (documentado) |
| Jobs da versão em produção | Só `TenantScopedJob` (base). Não há e-mails em fila na versão antiga, então não existe job antigo incompatível com o novo código |
| Jobs novos | Os e-mails de confirmação e de senha (PANELS-03) e os avisos operacionais vão pela fila |
| Duplicidade | Os avisos têm chave única por evento (`notification_deliveries`) e são disparados só após o commit. Os e-mails de autenticação saem uma vez por ação |
| Risco após o deploy | **Baixo:** a recriação do worker não reenvia nada. **PENDENTE** conferir `jobs` e `failed_jobs` antes do deploy |

### 6.3 Scheduler e tarefas financeiras automáticas: impeditivo operacional

**A nova tarefa `billing:run`** (diária, às 05:30, `withoutOverlapping`) faz:
1. a conciliação de cobranças com a Efí;
2. a passagem do fim do teste para "pagamento pendente";
3. a suspensão no fim da carência;
4. a abertura de renovações;
5. o aviso de fim do teste.

**Empresas existentes:** sem assinatura, nenhuma é afetada.

**Empresas cadastradas depois do deploy:**
- Dia 0: o cadastro inicia o teste de 14 dias (`TenantRegistrationService`).
- Dia 11: aviso "escolha a forma de pagamento".
- Dia 14: "pagamento pendente".
- Dia 19: **suspensão financeira, com o painel bloqueado (402)**.

Com a Efí em homologação, sem credenciais e com os valores não aprovados, **não há como pagar**: "Gerar cobrança" cria uma cobrança que fica "Em emissão", com a mensagem de falha. O comportamento está coberto por testes, portanto é determinístico.

A rotina não gera cobrança real nem compra; as travas funcionam. **O problema é o bloqueio de clientes reais.**

**Alternativas (decisão necessária; alteração numa próxima etapa):**

| Opção | Como | Efeito |
|---|---|---|
| **1 (recomendada)** | Uma chave de "cobrança em vigor", desligada por padrão. Enquanto estiver desligada, o cadastro **não inicia** assinatura (a empresa fica como as atuais, com acesso livre) e a rotina não aplica fim de teste nem suspensão | Nenhum cliente bloqueado. A cobrança passa a valer só na liberação comercial |
| 2 | Manter o teste, mas a rotina não suspende enquanto a Efí não estiver em produção com os valores aprovados | Clientes veem "Em teste" e "pagamento pendente" sem poder pagar (confuso) |
| 3 | Operacional: o RootAdmin prorroga os testes manualmente | Frágil, depende de acompanhamento diário |

### 6.4 Outras tarefas

`assistant:purge` (06:30): continua só simulando enquanto `ASSISTANT_RETENTION_DELETION=false`. Não muda com o release.

## 7. Bloqueio financeiro (as quatro travas da RELEASE-11.0)

| Trava | Estado após as migrations | Ausente ou inválida | Teste |
|---|---|---|---|
| 1. `billing_settings.efi_environment` | `homologation` | recriada em homologação; valor inválido usa homologação | `BillingLocksTest`, `EfiBillingTest` |
| 2. `plans.prices_approved_at` | nulo, sem emissão em produção | nulo bloqueia (inclusive na rotina e na sincronização) | `BillingIntervalTest` |
| 3. Credenciais da Efí | ausentes | nenhuma requisição sai; cobrança pendente com o motivo | `BillingLocksTest` |
| 4. `ai_credit_purchases_enabled` | `false`, sem pacotes nem franquia | ausente ou nulo recusa | `AiCreditTest`, `BillingLocksTest` |

**Situação após o deploy:**
- Cobranças reais bloqueadas, compras de créditos bloqueadas e operações da Efí restritas à homologação.
- Configuração ausente ou inválida não autoriza nada.
- **Nenhuma trava foi alterada.**

A ressalva é a seção 6.3: as travas impedem a cobrança, mas não o ciclo de vida da assinatura.

## 8. Procedimento de deploy (para depois da resolução dos impedimentos)

Segue o padrão já validado na VPS (RELEASE-10.3 e WILDCARD-01.5). **Não executar sem autorização.**

1. **Preparação e publicação:** aplicar as decisões das seções 4.2 e 6.3 numa etapa própria, com testes. Depois, revisar e autorizar o push fast-forward de `main` para a `origin/main`.
2. **Conferência da versão em produção:** na VPS, `rev-parse` do checkout, tag das imagens em execução, `migrate:status` (as 14 migrations ausentes) e as consultas da seção 4.2.
3. **Backup:** `mysqldump --single-transaction --routines --triggers --hex-blob` com `.sha256` e permissão 600; cópia de `.env`, `storage/app`, compose e Nginx; cópia fora da VPS; retag das imagens atuais como `rollback-pre-11.x`.
4. **Teste de recuperação:** restaurar o dump num `mysql:8.4` descartável (seção 5) **e rodar as 14 migrations nele** (seção 4.4).
5. **Janela de manutenção:** recomendada, curta (minutos), por causa das migrations 1, 2 e 5. `php artisan down --retry=60` no web.
6. **Implantação do código:** `git pull --ff-only` no checkout; depois `docker compose build vetorsmart vetorsmart-worker vetorsmart-scheduler`.
7. **Verificação de configurações:** `.env` com `APP_URL`, `MAIL_*`, `QUEUE_CONNECTION=database` e `ASSISTANT_PROVIDER=none`; `APP_KEY` inalterada; variáveis obsoletas `PLATFORM_PLAN_*` e `PLATFORM_CYCLE_*` agora ignoradas.
8. **Migrations controladas:** `php artisan migrate --force` uma vez, com saída registrada; depois `migrate:status`.
9. **Workers (quando autorizado):** `up -d --no-deps` de web, worker e scheduler; conferir a saúde.
10. **Scheduler:** `schedule:list` mostra `assistant:purge` e `billing:run`; execução manual de `billing:run` sem emissões.
11. **Smoke tests:** `/up`, `/login`, `/register`; login de usuário existente (na opção A, a tela de confirmação de e-mail); envio do e-mail de confirmação pela fila; `php artisan up`.
12. **Painel do assinante:** roteiro da seção 9.
13. **RootAdmin:** roteiro da seção 9, com uma conta legítima.
14. **Logs e monitoramento:** `volumes/vetorsmart-logs/{web,worker,scheduler}`, `failed_jobs` e `docker stats`, por 24 horas; conferir a primeira execução de `billing:run` às 05:30.
15. **Recuperação em caso de falha:** seção 4.3. Retag `rollback-pre-11.x` → `latest` e `up -d --no-deps`; `migrate:rollback --step=N` sem uso real, ou restauração do dump.

## 9. Homologação planejada em navegador

Sem alterar dados de produção. Usar as contas e empresas reais só em leitura, mais uma empresa nova cadastrada para o teste e marcada com `homolog:mark`.

**Matriz:** Chromium e Firefox × desktop (1440×900) e celular (390×844) × claro e escuro.

**Em cada tela, verificar:**
- HTTP 200;
- sem rolagem horizontal e sem erro de JavaScript;
- axe WCAG 2.1 AA, inclusive contraste;
- campos, botões e mensagens legíveis;
- foco por teclado.

**Painel do assinante (empresa de teste):**
1. Login e sessão: login, persistência, logout e confirmação de e-mail com reenvio.
2. Painel (dashboard).
3. Clientes: lista e criação.
4. Serviços.
5. Agenda: dias, bloqueio e disponibilidade.
6. Atendimento (IA desligada: fluxo convencional e conversas).
7. Minha Assinatura:
   - situação "Em teste";
   - cartões de ciclo e composição;
   - cartão de vagas com a regra vigente;
   - créditos ("A definir" e compra não liberada);
   - **não clicar em "Gerar cobrança"**.
8. Notificações e preferências.

**RootAdmin (conta legítima, somente leitura):**
1. Login e autorização: um usuário de empresa recebe 403 em `/rootadmin`.
2. Visão Geral (indicadores e empresas de homologação fora).
3. Empresas e usuários.
4. Assinaturas: lista, detalhe, vagas e créditos.
5. "Plano e Efí":
   - selo "Valores provisórios";
   - ambiente "Homologação";
   - compra desligada;
   - **não salvar nada**.
6. Auditoria (filtros).
7. Isolamento: o detalhe de uma empresa não expõe dados operacionais de outra.

**Automação:** os roteiros Playwright e axe usados localmente (matriz de "Minha Assinatura" e do RootAdmin) servem de base. O endereço base vira `https://vetorsmart.com.br` e as credenciais são informadas na hora, nunca gravadas em arquivo do repositório.

## 10. Recursos da KVM 4

Último dado documentado (17/11), **PENDENTE revalidação**:
- 4 vCPU;
- disco com 124 GB livres;
- VetorSmart com 114 MiB de RAM;
- margem de deploy de cerca de 0,4% da memória;
- build da imagem como o momento mais pesado;
- MySQL com folga de conexões (14 de 151).

O release não traz serviço novo, nem dependência nova, nem processo contínuo novo (só a tarefa diária `billing:run`, leve). **Não há indício de incompatibilidade de infraestrutura.**

## 11. Bloqueios e pendências

| # | Item | Tipo |
|---|---|---|
| 1 | Migration 2 valida e-mails sem comprovação: decidir entre as opções A, B e C e ajustar | **Impeditivo (segurança)** |
| 2 | Cadastros novos seriam suspensos sem conseguir pagar: decidir entre as opções 1, 2 e 3 e ajustar | **Impeditivo (operação)** |
| 3 | Inspeção somente leitura da VPS (seção 3), por uma sessão autorizada na VPS ou com permissão de SSH | **Impeditivo (evidência)** |
| 4 | SMTP de produção configurado e testado com destinatário controlado | Impeditivo |
| 5 | Backup novo, integridade e ensaio de restauração com as 14 migrations em MySQL 8.4 | Impeditivo |
| 6 | Commit do `pnpm-lock.yaml` em manutenção separada (sem efeito no deploy) | Não impeditivo |
| 7 | Conferir a cadeia TLS real fora da rede com inspeção Sophos | Não impeditivo |
| 8 | Homologação do RootAdmin em produção (seção 9) | Após o deploy |
| 9 | Aprovação comercial, homologação da Efí, provedor de IA | Etapas futuras |

## 12. Checklist de implantação (resumo)

- [ ] Decisões das pendências 1 e 2 aplicadas e testadas (nova etapa)
- [ ] Inspeção somente leitura da VPS concluída (seção 3)
- [ ] SMTP verificado
- [ ] `jobs` e `failed_jobs` conferidos
- [ ] Backup novo com `.sha256`, cópia fora da VPS e imagens `rollback-pre-11.x`
- [ ] Restauração e as 14 migrations ensaiadas em MySQL 8.4 descartável
- [ ] Push fast-forward autorizado
- [ ] Janela de manutenção, build, `migrate --force`, recriação de web, worker e scheduler
- [ ] Smoke tests, painel do assinante e RootAdmin (seção 9)
- [ ] Travas conferidas por consulta (RELEASE-11.0, seção 8)
- [ ] Monitoramento por 24 h e primeira execução de `billing:run`

## 13. Confirmação

- **Nenhum push, merge, rebase, reset ou deploy.**
- **Nenhuma migration executada.**
- **Nenhum acesso à VPS:** a tentativa de SSH foi negada pela política de permissões e não foi contornada.
- **Nenhum dado ou configuração de produção alterado.**
- **Nenhum usuário criado ou alterado.**
- **Nenhuma cobrança, chamada à Efí ou compra de créditos.**
- O `pnpm-lock.yaml` não foi modificado.
- A RELEASE-11.2 não foi iniciada.
- Únicos acessos externos: requisições GET anônimas ao site público.
