# VETORSMART-RELEASE-11.2 — Correções pré-implantação, verificação de e-mail e segurança do ciclo financeiro

Data: 19/11/2026. Base: HEAD `6530ddd`. Ambiente: desenvolvimento local (WSL).

- Sem push, deploy ou migrations em produção.
- Sem cobrança real e sem envio de e-mail real.
- Nenhum acesso à VPS.

## Parecer final

**Tecnicamente aprovada localmente.** Os dois defeitos impeditivos da RELEASE-11.1 foram eliminados, e todos os critérios de encerramento foram atendidos:

| Critério (seção 11) | Situação |
|---|---|
| Nenhum usuário indevidamente marcado como verificado | Atendido. A migration não atribui confirmação a ninguém (verificado no banco local: 25 contas confirmadas antes e 25 depois) |
| Usuários existentes sem bloqueio inesperado | Atendido. Acesso legado ao painel e à API do tenant; prazo opcional e, quando inválido, sem bloqueio |
| Novos tenants não suspensos com a cobrança desligada | Atendido. `billing:run` não faz nada, e o middleware libera o acesso |
| Nenhuma operação financeira real habilitada | Atendido. Chave desligada por padrão; as quatro travas foram mantidas |
| As quatro travas anteriores funcionando | Atendido (`BillingLocksTest`, `BillingIntervalTest`, `AiCreditTest`) |
| Regressão aprovada | **713 de 713** testes |

**Esta aprovação não autoriza a implantação.** Continuam pendentes as condições de infraestrutura da RELEASE-11.1:
- inspeção da VPS;
- SMTP de produção;
- backup e ensaio de restauração;
- migrations em **MySQL 8.4**: a compatibilidade final **não está homologada**, porque o banco local é MariaDB 11.8.

## 1. Causa-raiz dos dois defeitos

1. **Verificação indevida.**
   - A PANELS-02 introduziu a confirmação de e-mail e, para não bloquear quem já usava o sistema, criou uma migration que gravava `email_verified_at = now()` em toda conta não confirmada.
   - Isso confundiu "manter o acesso" com "comprovar o endereço", num sistema cujo cadastro público era aberto e sem confirmação.
   - **A correção separa as duas coisas:** existe uma marca de acesso legado, e a confirmação só pode ser real.
2. **Suspensão sem cobrança em vigor.**
   - A BILLING-01 acoplou o ciclo de vida da assinatura (teste, carência e suspensão) ao cadastro e à rotina diária, sem nenhuma condição de que a cobrança estivesse operacional.
   - As travas impediam a cobrança real, mas não as consequências do ciclo.
   - **A correção introduz uma chave global de cobrança em vigor**, separada das travas, que desliga o ciclo inteiro.

## 2. Política de verificação de e-mail

| Situação da conta | Painel e API do tenant | RootAdmin, gestão de equipe e ações de assinatura |
|---|---|---|
| Confirmada (`email_verified_at`) | liberado | liberado |
| **Legada** (existia antes do release, sem confirmação: `email_verification_legacy_at`) | **liberado, com aviso** "Confirme seu e-mail" e botão de envio do link | **exige confirmação real** (`verified.strict`): vai para a tela de confirmação |
| Nova e não confirmada | tela de confirmação (como na PANELS-02) | tela de confirmação |

- **Migration** `2026_11_18_134101_flag_existing_unverified_users_as_legacy`:
  - substitui a `mark_existing_users_as_verified`, que nunca rodou em produção;
  - adiciona `users.email_verification_legacy_at` e marca só as contas sem confirmação;
  - **não grava `email_verified_at`** e não remove confirmações existentes;
  - é reversível (o `down` remove a coluna).
- **RootAdmin:** o papel não comprova o endereço. Uma conta legada com `is_root_admin` não entra no RootAdmin nem na API administrativa até confirmar. O `platform:root-admin` deixou de marcar a confirmação e passa a enviar o link.
  - A exceção para administradores com titularidade comprovada é o próprio link: não há marcação automática.
- **Fim do acesso legado:**
  - ao confirmar o e-mail;
  - ao **trocar o e-mail**: o endereço novo sempre precisa ser confirmado;
  - ou após `PLATFORM_LEGACY_EMAIL_GRACE_UNTIL` (AAAA-MM-DD), opcional. Vazio ou inválido significa sem prazo, para ninguém ser bloqueado enquanto o SMTP não estiver operacional.
- **Fluxo de confirmação:**
  - link assinado (assinatura HMAC com a `APP_KEY` e hash do e-mail), com expiração de 60 minutos (`auth.verification.expire`);
  - reenvio limitado a **3 por minuto e 10 por hora** por conta (limitador `verification`);
  - link expirado, adulterado ou de outra conta **não confirma nada** e volta à tela de confirmação com a mensagem "Este link de confirmação é inválido, expirou ou pertence a outra conta".
- **Login universal e multi-tenant:** sem mudança. A confirmação é por pessoa, não por empresa. Uma conta legada mantém o acesso a todas as empresas de que já participa, e operações sensíveis exigem a confirmação em qualquer empresa.

## 3. Política de cobrança em vigor

- **Configuração:** `PLATFORM_BILLING_ENFORCEMENT_ENABLED` (`config('platform.billing_enforcement_enabled')`). Liga **só** com o literal `true`; `false`, `yes`, `1`, vazio ou ausente mantêm desligada (testado).
- **Em vigor** somente com a chave `true` **e** `billing_settings.enforcement_starts_at` gravado e já alcançado (`BillingEnforcement::active()`). **Mudar só a variável não liga a cobrança.**
- **Responsabilidade separada das quatro travas:**
  - esta chave decide se o ciclo de vida da assinatura vale;
  - as travas (ambiente Efí, aprovação comercial, credenciais e chave de compra) decidem se uma cobrança real chega à Efí.

| Com a cobrança fora de vigor (padrão) | Implementação |
|---|---|
| Novos tenants usam o sistema normalmente | O cadastro registra o teste (histórico), e o middleware `subscription.access` libera sempre |
| Vencimento dos 14 dias e carência não suspendem | `BillingCycle::run()` retorna sem processar nada |
| Empresas existentes acessíveis | Sem assinatura, ou com qualquer situação, liberadas |
| Nenhuma cobrança | `ChargeService::openFor()` recusa ("A cobrança ainda não está em vigor…") e `issue()` não emite nem uma pendência antiga |
| Nenhuma vaga paga concedida | `SeatService::changeExtraSeats()` recusa; o limite fica nos 3 usuários incluídos para quem tem assinatura |
| Compras de créditos bloqueadas | `AiCreditPurchaseService::request()` exige a cobrança em vigor, além da chave própria |
| Auditoria e histórico preservados | Nada é apagado nem alterado |
| Tela do assinante | "Período sem cobrança", valores informativos, sem contratação nem vagas |
| RootAdmin | Cartão "Cobrança em vigor: desligada" com os pré-requisitos pendentes, somente leitura |

**Com a cobrança em vigor**, as regras aprovadas das BILLING-01 a 01.3 valem sem alteração (todos os testes de cobrança passam com `enableBillingEnforcement()`).

## 4. Comportamento do scheduler

Revisados `billing:run` (`BillingCycle`), `RunBillingCycle`, `ChargeService`, `SubscriptionService::periodPaid` e `SeatService`. O VetorSmart não tem listeners financeiros nem jobs de cobrança; os e-mails seguem pela fila.

| Garantia | Situação |
|---|---|
| 1. Idempotência | Cada passo continua idempotente (índice único por período, locks, chaves de aviso). Fora de vigor, a execução não muda nada, não importa quantas vezes rode |
| 2. Não suspende com a cobrança desabilitada | Retorno antecipado (testado: 40 dias depois do cadastro, a empresa continua "Em teste", com acesso, sem cobrança e sem aviso) |
| 3. Sem cobranças indevidas | Fora de vigor, nada é aberto nem emitido. Em vigor, as quatro travas continuam |
| 4. Contratos históricos | Não há escrita fora de vigor. Em vigor, o comportamento da BILLING-01.3 é mantido |
| 5. Vagas não pagas | Continuam ativadas só em `periodPaid`; fora de vigor, nem podem ser pedidas |
| 6. Cobrança real sem as travas | Impossível: são três camadas independentes (vigor, travas da Efí e aprovação) |
| 7. Registro de decisões | Cada execução fora de vigor gera no log "Rotina de assinaturas sem efeito: a cobrança não está em vigor", com o estado da chave e o total de assinaturas. O relatório do comando traz `enforcement 0/1`. A transição gera auditoria `billing.enforcement_scheduled` e eventos `enforcement_transition` |

## 5. Transição comercial futura (preparada, não executada)

**Comando `billing:start-enforcement`** (testado, **nunca executado**):

```
billing:start-enforcement --starts-at=AAAA-MM-DD [--adaptation-days=N] [--include-legacy] [--execute]
```

- **Prévia por padrão.** `--execute` exige:
  - terminal interativo;
  - confirmação de que a homologação da Efí foi concluída;
  - a frase digitada "INICIAR COBRANCA".
- **Recusa** com pré-requisitos pendentes:
  - chave `true`;
  - valores aprovados;
  - Efí em produção;
  - credenciais Pix e Cobranças.
- **Primeira data de vencimento** = início + dias de adaptação.

**Estratégia por grupo:**

| Grupo | Tratamento |
|---|---|
| Cadastrados durante o período gratuito (em teste) | Teste prorrogado até o primeiro vencimento |
| Teste já encerrado no período sem cobrança | Continuam "Em teste" (a rotina não os moveu) e são prorrogados: **sem suspensão retroativa** (testado) |
| Teste em andamento além do primeiro vencimento | Mantido |
| Contratos históricos (ativos) | Mantidos, com as condições congeladas |
| Carência, suspensas ou canceladas | Listadas na prévia para revisão manual |
| Empresas sem assinatura (antigas) | Não incluídas, exceto com `--include-legacy`: começam o teste com o mesmo primeiro vencimento |

**Antes da execução:**
- comunicação prévia aos assinantes (e-mail operacional e aviso no painel) com a data de início, o período de adaptação e o primeiro vencimento;
- definição dos valores aprovados, que **não foram definidos nesta etapa**.

Toda a transição fica no histórico de cada assinatura e na auditoria.

## 6. SMTP e notificações

| Item | Situação no código |
|---|---|
| Confirmação de endereço | `VerifyEmailNotification`: fila, 3 tentativas (espera de 60 s e 300 s), em português, tema VetorSmart |
| Recuperação de senha | `ResetPasswordNotification`: idem |
| Notificações operacionais | `OperationalMail` via `OperationalNotifier`: fila, 3 tentativas, disparo após o commit |
| Filas e retentativas | Conexão `database`; falhas vão para `failed_jobs` |
| Links públicos | Gerados a partir de `APP_URL` e do domínio da plataforma (PANELS-03 e 04); em produção, exigem `APP_URL=https://vetorsmart.com.br` |
| Templates em português | `lang/pt_BR`, tema `vetorsmart` |
| Envio duplicado | Chave única por evento (`notification_deliveries`); o reenvio de confirmação tem limite |

**SMTP de produção: PENDENTE** (sem acesso à VPS). Nenhuma mensagem real foi enviada.

**Teste controlado** (executar só com autorização, na VPS, com destinatário controlado e sem gravar senha em arquivo):
1. `php artisan config:show mail.default` e `config:show mail.mailers.smtp` (conferir host, porta e esquema; a senha aparece mascarada).
2. `php artisan tinker --execute 'Mail::raw("Teste controlado VetorSmart", fn ($m) => $m->to("<destinatário autorizado>")->subject("Teste SMTP"));'`
3. Conferir a entrega, o SPF, o DKIM e o DMARC no cabeçalho.
4. Testar pela fila: o "Enviar link de confirmação" de uma conta de teste, com `queue:work` ativo, e conferir `failed_jobs`.

## 7. Testes executados

| Grupo | Resultado |
|---|---|
| **Pest completo** (banco SQL compatível: MariaDB 11.8 local, `VETORSMART_CONCURRENCY=1`) | **713 de 713**, 4114 asserções, 0 pulados. São os 678 de referência mais 35 novos |
| Verificação de e-mail e usuários legados (`LegacyEmailVerificationTest`, 13) | Painel com aviso sem confirmação falsa; conta confirmada sem aviso; conta nova vai à confirmação; equipe, assinatura e vagas exigem confirmação; RootAdmin (web e API) não abre só pelo papel; confirmação pelo link; link expirado ou adulterado recusado com explicação; reenvio limitado a 3; troca de e-mail encerra o legado; prazo vencido bloqueia, e prazo inválido não |
| Login e permissões | `Auth/*`, `CreateRootAdminCommandTest` (novo administrador confirma antes de entrar), `BillingPermissionsTest` (35) e `RegistrationTest` (link de outra conta não confirma nada) |
| Multi-tenant | Os 11 arquivos de isolamento passaram |
| Scheduler com a cobrança ligada e desligada (`BillingEnforcementTest`, 22) | Matriz de ativação; somente `true` liga; fora de vigor não suspende nem avisa; suspensa continua acessível; cadastro registra o teste; sem cobrança, vagas ou compra; pendência antiga não é emitida; tela informa; em vigor, o ciclo volta a valer |
| Transição futura | Recusa sem pré-requisitos; prévia não altera; execução prorroga os testes, inclui as antigas com opção, é auditada e não suspende retroativamente; sem a frase digitada, nada muda |
| Teste gratuito, carência e suspensão | `SubscriptionLifecycleTest` e `EfiBillingTest` (em vigor) |
| Quatro travas | `BillingLocksTest`, `BillingIntervalTest` e `AiCreditTest` |
| Vagas adicionais | `SeatTest` (16), `BillingEnforcementTest` |
| Concorrência e idempotência | `BillingConcurrencyTest` (6) e `MysqlConcurrencyTest` (9) |
| PHPStan / Pint / TypeScript / build | 0 erros / ok / ok / ok |
| Visual de "Minha Assinatura" | **8 de 8** (Chromium e Firefox × desktop e celular × claro e escuro), 0 violações de axe; o aviso "Período sem cobrança" foi conferido na captura |
| Migration corrigida (banco local, `down` e `up` com contas temporárias, depois removidas) | Confirmada preservada; pendente sem confirmação e marcada como legada; 25 confirmadas antes e 25 depois |
| **MySQL 8.4** | **PENDENTE.** Sem Docker no WSL; compatibilidade final não homologada |

## 8. Commits locais (sem push)

| Commit | Descrição |
|---|---|
| `ee35c41` | `fix(auth)`: nenhuma conta é marcada como verificada sem comprovação |
| `b33bed7` | `feat(billing)`: chave global de cobrança em vigor, desligada por padrão |
| `b80e8e3` | `chore(deps)`: sincroniza `pnpm-lock.yaml` com o `package.json`. Validado: a versão commitada anterior falhava em `pnpm install --frozen-lockfile` (`ERR_PNPM_OUTDATED_LOCKFILE`) e a nova passa. Sem efeito no deploy (yarn) |
| (docs) | `docs(release)`: registra RELEASE-11.1 e RELEASE-11.2 |

O histórico foi preservado (sem `reset`, `rebase` ou `amend`). O `correio.md` e o `executed.md` ficaram fora. Não há segredos nos diffs (verificado).

## 9. Pendências de VPS e backup

Os comandos de diagnóstico somente leitura estão na RELEASE-11.1, seção 3. O SSH continua bloqueado pela política da sessão; não houve contorno.

## 10. Checklist de implantação (atualizado)

1. [ ] **Inspeção somente leitura da VPS** por um operador autorizado (comandos da RELEASE-11.1, seção 3): commit e imagens em produção, containers, `migrate:status`, `jobs` e `failed_jobs`, scheduler e logs.
2. [ ] **Recursos:** `free -m`, `df -h`, `docker stats --no-stream` (referência de 17/11: disco a 39% e VetorSmart com 114 MiB).
3. [ ] **SMTP de produção:** configuração conferida e teste controlado (seção 6), com autorização.
4. [ ] **Backup consistente:** `mysqldump --single-transaction --routines --triggers --hex-blob` com `.sha256` e permissão 600, cópia fora da VPS, `.env`, `storage/app`, compose e Nginx, e imagens `rollback-pre-11.2`.
5. [ ] **Ensaio isolado de restauração:** `mysql:8.4` descartável, sem rede e em memória; comparar as contagens.
6. [ ] **Migrations em MySQL 8.4 de teste:** as **16** pendentes sobre o dump restaurado (`migrate --pretend` e depois `migrate`):
   - as 14 da RELEASE-11.1, com a 2 agora `flag_existing_unverified_users_as_legacy`;
   - mais `2026_11_19_112804_add_billing_enforcement_to_billing_settings_table`.
   - Ao todo, 16 arquivos novos desde `1ec0a5c`.
7. [ ] **Verificação da migration corrigida no ensaio:** `SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL` igual antes e depois; `email_verification_legacy_at` preenchido só nas contas sem confirmação.
8. [ ] **Cobrança desabilitada:**
   - `PLATFORM_BILLING_ENFORCEMENT_ENABLED` ausente ou `false` no `.env`;
   - `SELECT enforcement_starts_at FROM billing_settings` nulo;
   - "Plano e Efí" mostra "Desligada";
   - `billing:run` manual com `enforcement 0`.
9. [ ] **Homologação dos painéis:** roteiro da RELEASE-11.1, seção 9, mais:
   - aviso de confirmação para uma conta legada;
   - RootAdmin exige confirmação de uma conta root legada;
   - "Período sem cobrança" em "Minha Assinatura".
10. [ ] **Plano de recuperação:** RELEASE-11.1, seção 4.3. A migration 2 agora é **reversível** (o `down` remove a coluna). O retorno só do código para `1ec0a5c` continua compatível, porque só há colunas e tabelas novas.

**Variáveis novas** (só os nomes):
- `PLATFORM_BILLING_ENFORCEMENT_ENABLED` (deixar ausente ou `false`);
- `PLATFORM_LEGACY_EMAIL_GRACE_UNTIL` (deixar vazia até haver decisão).

## 11. Riscos residuais

1. **MySQL 8.4 não ensaiado** (MariaDB local): risco baixo (coluna `timestamp` e chaves comuns), mas a compatibilidade final não está homologada.
2. **Dependência do SMTP:** contas legadas precisam de e-mail para confirmar e liberar as operações sensíveis, inclusive os administradores atuais da plataforma para entrar no RootAdmin. **Sem SMTP operacional, o RootAdmin fica inacessível às contas root legadas.** O SMTP precisa estar validado antes do deploy.
3. **Limite de 3 usuários** para empresas novas com assinatura enquanto a cobrança estiver fora de vigor (não há vaga paga). As empresas antigas continuam sem limite. Precisa de validação comercial.
4. **Inspeção da VPS, backup e restauração** continuam sem comprovação.
5. **Dado local:** uma cobrança antiga "Em emissão" de homologação continua no banco local (não existe em produção). Fora de vigor, nunca é emitida.
6. A RELEASE-11.1 identificou que o TLS visto daqui é interceptado pela rede local; a cadeia real deve ser conferida da VPS.

## 12. Confirmação

- Sem push, deploy ou migrations em produção.
- Sem acesso à VPS e sem alteração de dados de produção.
- Nenhuma conta administrativa criada ou alterada (as contas temporárias da verificação da migration foram criadas e removidas no banco local).
- Nenhuma cobrança real, chamada à Efí, compra de créditos ou e-mail real.
- Nenhum segredo em arquivos versionados.
- A próxima etapa não foi iniciada.
