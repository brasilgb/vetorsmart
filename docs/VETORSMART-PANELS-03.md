# VETORSMART-PANELS-03 — Homologação do RootAdmin e consolidação administrativa

Data: 18/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`. Sem deploy, sem migration em produção, sem acesso à VPS.

## Parecer

**APROVADO COM RESTRIÇÕES.**

- A PANELS-02 foi consolidada: commit e push na `origin/main`.
- Três defeitos foram corrigidos e testados:
  - falha de e-mail virando erro 500;
  - RootAdmin novo preso na confirmação de e-mail;
  - códigos internos aparecendo na console.
- A auditoria funcional e a especificação administrativa estão nas seções 3 e 4.

**Pendente:** a homologação visual do RootAdmin. A criação da conta local foi bloqueada pela política de permissões da sessão (seção 2). O roteiro está pronto e roda assim que a conta existir.

## Etapa 1 — Consolidação da PANELS-02

- Diff revisado. As 2 migrations foram conferidas (aplicadas só no banco local).
- Suíte antes do commit: 513 de 513. PHPStan: 0 erros.
- Nenhuma credencial no diff.
- Commits na `main`, seguindo o padrão do repositório:
  - `b1b1bfd` `fix(painel)`: expurgo de homologação, recuperação de senha e proteção do cadastro (36 arquivos);
  - `4424cb1` `docs(release)`: registra PANELS-01 e PANELS-02 e atualiza executed.
- Push: `b411400..4424cb1 main -> main`. **Sem deploy.**
- Fora dos commits:
  - `correio.md` (é a instrução da PANELS-03);
  - `pnpm-lock.yaml` (modificado antes e sem relação com este trabalho).

## Etapa 2 — Homologação do RootAdmin

### Criação da conta local: bloqueada

O mecanismo controlado já existe: `php artisan platform:root-admin`. Ele recebe a senha por variável de ambiente, nunca como argumento, e registra auditoria.

A execução para criar `homolog.root.panels03@example.test` no banco local foi **recusada pela política de permissões da sessão**, mesmo com o correio autorizando. Não foi contornada. Nenhuma conta ou credencial existente foi alterada.

Para destravar, foi preparado o script `create-local-root.sh`, no scratchpad da sessão. Ele:
- só roda com `APP_ENV=local` e o banco em `127.0.0.1`;
- gera a senha sem imprimi-la e a guarda com permissão 600;
- chama o comando do projeto.

O roteiro `root.mjs` (Chromium e Firefox, desktop e celular, claro e escuro, axe) verifica:
- login;
- o menu ("Em breve");
- o redirecionamento de rotas de empresa;
- lista → detalhe;
- suspensão com confirmação obrigatória e reativação;
- configurações mostradas em texto;
- validação do formulário de segmentos;
- registro na auditoria;
- 403 para o proprietário de empresa;
- 11 telas na matriz visual.

### Defeito encontrado ao preparar a conta (corrigido)

Depois da PANELS-02, um RootAdmin **novo** criado pelo `platform:root-admin` nasce sem `email_verified_at` e fica preso na tela de confirmação. Como o e-mail é digitado pelo operador da plataforma no terminal do servidor, o comando agora cria a conta já verificada. Ao **promover** uma conta existente, o estado de verificação é mantido.

Testes:
- um RootAdmin novo acessa `/rootadmin`;
- uma conta promovida sem confirmação continua sendo levada ao aviso.

### Cobertura automática do RootAdmin (Pest)

- 20 testes em `tests/Feature/RootAdmin`, mais os de login universal e do comando:
  - visitante → login;
  - empresa → 403 na console e nas ações;
  - suspensão exige `confirm`;
  - homologação fora dos indicadores;
  - datas no fuso da plataforma;
  - console sem dados operacionais.
- Todos aprovados.

## Etapa 3 — Auditoria funcional do RootAdmin

| Item exibido | Classificação | Observação |
|---|---|---|
| Visão Geral | 1. Implementada e funcional | Indicadores reais (empresas, usuários, sites publicados, agendamentos futuros, IA do mês, distribuição por segmento, cadastros recentes). Indicadores sem fonte aparecem em "Indisponíveis no momento", com o motivo. |
| Empresas: lista e filtros | 1. Funcional | Busca por nome e slug, status e segmento; paginação; selo "Homologação". |
| Empresas: detalhe | 2. Parcial | Dados, usuários, domínio, site, atendimento e histórico. **Não edita** dados nem segmento. |
| Empresas: suspender e ativar | 1. Funcional | Confirmação obrigatória e auditoria. O filtro "Cancelada" existe, mas nenhuma ação leva a esse status. |
| Segmentos | 1. Funcional | Criar, editar e ativar/desativar. Sem exclusão (intencional). |
| Usuários e Acessos | 2. Parcial | Somente leitura, só vínculos com empresas. **Administradores da plataforma não aparecem**, e não há bloqueio nem redefinição de senha. |
| Segurança e Auditoria | 1. Funcional | Filtros por ação, empresa e período. |
| Sites e serviços | 2. Parcial | Contagem de sites publicados e situação do site no detalhe. O menu "Sites e Domínios" está desativado. Os serviços das empresas não são exibidos (por desenho: a console não lê dados operacionais). |
| Agendamentos | 2. Parcial | Só a contagem de futuros (mesmo motivo). |
| IA e Créditos | 4. Indisponível (menu) / 2. Parcial (indicadores) | O consumo aparece na Visão Geral; o custo está pendente. |
| Assinaturas e Financeiro | 4. Indisponível | Menu desativado ("Em breve"). |
| Crescimento e SEO | 4. Indisponível | Menu desativado. |
| Suporte | 4. Indisponível | Menu desativado. |
| Configurações Globais | 4. Indisponível | Menu desativado. |

**"Apenas interface sem backend": nenhum.** Os módulos sem tela aparecem desativados e com "Em breve", sem números inventados.

Defeito corrigido nesta etapa: o **detalhe da empresa mostrava códigos internos** (`customer_confirmation`, `in_person`, `pt_BR`). Agora mostra os mesmos textos da tela de configurações da empresa: "Cliente confirma", "Presencial", "Português (Brasil)". Para isso, foram criados `ConfirmationPolicy::label()` e `ServiceMode::label()`. Teste em `ConsoleTest`.

Navegação, formulários e acessibilidade da console só podem ser confirmados no navegador, e dependem da conta local.

## Etapa 4 — Especificação administrativa (sem implementação)

**O que existe hoje:**
- `TenantStatus` (`active`, `suspended`, `cancelled`) com `TenantStatusService` auditado;
- `assistant_usage` diário (mensagens, chamadas, falhas, tokens);
- `audit_logs`;
- `growth_events`;
- fila `database` com worker e scheduler em produção.

**Estrutura proposta:**

| Necessidade | Estrutura |
|---|---|
| Planos | `plans`: código, nome, preço em centavos, moeda, intervalo, dias de teste, limites em JSON (mensagens de IA por mês, usuários, sites) e situação. Preço é sempre em centavos, nunca em float. |
| Assinaturas e período de teste | `subscriptions`: uma vigente por empresa; plano; status `trialing` → `active` → `past_due` → `suspended` → `cancelled`; `trial_ends_at`; período atual; `cancel_at`; identificadores do gateway (preenchidos só na integração Efí). |
| Transições | `subscription_events`: de → para, motivo, origem (`manual`, `billing`, `system`), autor. Toda mudança também no `AuditLogger`. |
| Histórico de cobranças | `charges`: empresa, assinatura, valor, vencimento, pagamento, status (`pending`, `paid`, `overdue`, `cancelled`, `refunded`), método (Pix, boleto, cartão), referência externa. `webhook_receipts` com chave idempotente para os retornos do gateway. |
| Suspensão e reativação | Reaproveitar o `TenantStatusService`, acrescentando motivo e origem. A suspensão por cobrança (`billing`) só é desfeita pela baixa da cobrança ou pelo RootAdmin; a suspensão manual, só pelo RootAdmin. Prazo de tolerância configurável. |
| Consumo de IA | `ai_price_tables` (provedor, modelo, preço por mil tokens de entrada e saída, vigência) para calcular o custo a partir do `assistant_usage`. Se houver créditos: `ai_credit_ledger` (empresa, variação, motivo, referência), com saldo sempre derivado do livro. |
| Notificações administrativas | Canal `database` do Laravel (tabela `notifications`) para os RootAdmins: novo cadastro, cobrança vencida, aumento de falhas do provedor, `failed_jobs` maior que 0. E-mail opcional, pela fila. |
| Indicadores de uso | `platform_metrics_daily`, gerada por um comando agendado (empresas ativas, usuários, solicitações, agendamentos, mensagens de IA, sites publicados), sem empresas de homologação. A Visão Geral passa a mostrar tendências. |

**Sequência sugerida:**
1. Planos e assinaturas com teste e transições, sem gateway.
2. Telas do RootAdmin (Assinaturas e Financeiro).
3. Notificações administrativas.
4. Métricas diárias.
5. Integração Efí (cobranças e webhooks).

**Dependências:**
- definição comercial dos planos, limites e tolerância;
- e-mail de produção funcionando.

## Etapa 5 — Auditoria do e-mail

**Configuração (sem credenciais).** Nesta máquina não há acesso à VPS, então o `.env` de produção não foi lido. Os relatórios RELEASE-10.1 e 10.2 registram **`MAIL_MAILER=log` em produção**: nenhum e-mail sai.

Com a PANELS-02, isso vira **bloqueio de deploy**: sem e-mail, ninguém confirma o cadastro, e a recuperação de senha não chega.

Variáveis necessárias:

- `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`;
- `MAIL_FROM_ADDRESS` em um domínio próprio (por exemplo, `nao-responda@vetorsmart.com.br`) e `MAIL_FROM_NAME`;
- SPF, DKIM e DMARC de `vetorsmart.com.br`, configurados no provedor e na zona DNS da Hostinger;
- `APP_URL=https://vetorsmart.com.br`. É obrigatório: com o envio pela fila, os links assinados são gerados no worker, sem requisição, a partir do `APP_URL`.

| Item | Situação | Evidência |
|---|---|---|
| Recuperação de senha | Fluxo ok; link válido por 60 min; 1 pedido por minuto por e-mail | `config/auth.php`; navegador (PANELS-02) |
| Verificação de cadastro | Obrigatória para painel, console e API (PANELS-02) | Testes |
| Reenvio da confirmação | Limite de 6 por minuto (Fortify) | Testes |
| **Tratamento de falhas** | **Defeito, corrigido.** Com o SMTP fora do ar, o cadastro, o pedido de nova senha e o reenvio respondiam **erro 500**. No cadastro, a conta ficava criada sem o usuário entrar. | Teste temporário com SMTP inacessível: 500/500/500 → depois da correção, 302/302/302, com 3 jobs na fila |
| Filas | `QUEUE_CONNECTION=database`; worker `queue:work` em produção (DEPLOY-08); tabelas `jobs` e `failed_jobs` presentes | Relatórios e migrations |

**Correção.** Os links de confirmação e de redefinição agora saem **pela fila**:
- `App\Notifications\VerifyEmailNotification` e `ResetPasswordNotification` implementam `ShouldQueue`;
- 3 tentativas, com espera de 60 s e 300 s entre elas;
- uma entrega que continua falhando fica em `failed_jobs`;
- o `User` usa essas classes.

Testes (`QueuedAuthMailTest`): o cadastro, a redefinição e o reenvio respondem normalmente e colocam o envio na fila.

Atenção: no ambiente local (`QUEUE_CONNECTION=database`), os links só chegam ao log quando um worker roda (`php artisan queue:work`).

## Validação

| Verificação | Resultado |
|---|---|
| Pest completo | **520 de 520**, 3304 asserções |
| PHPStan | 0 erros |
| Pint | ok |
| TypeScript | ok |
| Build | ok |
| Homologação visual do RootAdmin | **Pendente** (conta local) |

## Arquivos (PANELS-03, sem commit)

**Aplicação:**
- `app/Console/Commands/CreateRootAdmin.php`
- `app/Enums/ConfirmationPolicy.php`
- `app/Enums/ServiceMode.php`
- `app/Http/Controllers/RootAdmin/TenantController.php`
- `app/Models/User.php`
- `app/Notifications/VerifyEmailNotification.php` (novo)
- `app/Notifications/ResetPasswordNotification.php` (novo)

**Testes:**
- `tests/Feature/Auth/QueuedAuthMailTest.php` (novo)
- `tests/Feature/Auth/PasswordResetTest.php`
- `tests/Feature/Management/TenantScreensTest.php`
- `tests/Feature/RootAdmin/ConsoleTest.php`
- `tests/Feature/Tenancy/CreateRootAdminCommandTest.php`
- `tests/Feature/Tenancy/RegistrationTest.php`

**Documentação:**
- `docs/VETORSMART-PANELS-03.md`
- `executed.md`

## Pendências

1. Homologação visual do RootAdmin (precisa da conta local).
2. **Antes do deploy da PANELS-02:** configurar o SMTP de produção, SPF/DKIM/DMARC e `APP_URL`, e enviar um e-mail de teste.
3. Listar os administradores da plataforma em "Usuários e Acessos" e permitir bloquear e redefinir usuários. Editar os dados da empresa pela console.
4. WebKit continua não testado.

## Próxima etapa recomendada

1. **E-mail de produção.** É o pré-requisito do deploy das PANELS-02 e 03.
2. **Deploy das PANELS-02 e 03**, com as migrations `is_homolog` e de verificação.
3. **Notificações** (empresa e administração), sobre a fila já validada.
4. **Planos e assinaturas sem gateway**, conforme a seção 4. A integração Efí vem depois.
