# VETORSMART-PANELS-02 — Correções críticas e homologação autenticada

Data: 18/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`. Nada foi feito na VPS: sem deploy, sem alteração de dados de produção, sem commit nem push.

## Parecer

**APROVADO COM RESTRIÇÕES.**

- D1, D2, R1 e R2 foram corrigidos e cobertos por testes.
- R3 e R4 receberam correções medidas.
- O painel do assinante foi homologado com login, em Chromium e Firefox.

Restrições:

1. **O RootAdmin não foi homologado visualmente.** A redefinição da senha das contas de homologação foi bloqueada pela política de permissões da sessão (seção 5).
2. **WebKit não foi testado.**
3. **Pré-requisito de deploy:** o envio de e-mail em produção precisa estar funcionando antes de publicar esta versão, porque cadastros novos dependem dele para entrar no painel.

## 1. Defeitos corrigidos

### D1 — Expurgo de homologação

- O `HomologPurgeService` agora tem **uma única lista ordenada de tabelas** (filhas antes das mães), usada na prévia e na remoção:
  - `assistant_operations`, `assistant_messages`, `assistant_conversations`, `assistant_usage` e `assistant_settings`;
  - `appointments`, `service_request_events`, `service_requests` e `professional_blocks`;
  - as tabelas que já eram tratadas.
- As chaves estrangeiras são respeitadas sem depender de cascata e sem desativar as verificações. Tudo continua em uma transação, e o histórico de auditoria é mantido.
- A remoção é feita por `tenant_id IN (empresas marcadas)`. Não há exclusão global.
- **Nova proteção:** um usuário com e-mail de homologação que seja administrador da plataforma nunca é removido. Antes, `homolog.root071@example.test` seria apagado.
- Testes cobrem:
  - uma empresa com solicitação, evento, agendamento, bloqueio, conversa, mensagem, operação, uso e configuração do atendimento é removida por inteiro, e uma empresa real com os mesmos dados fica intacta;
  - a prévia lista as tabelas operacionais;
  - uma empresa com o prefixo no nome, mas sem a marca interna, não é removida;
  - o administrador da plataforma é preservado.

### D2 — Recuperação de senha

- A tela de login mostra o link **"Esqueci minha senha"** ao lado do rótulo "Senha", quando `canResetPassword` é verdadeiro.
- O fluxo completo foi validado no navegador (seção 4): link → pedido → e-mail (log local) → redefinição → login com a nova senha.

### R1 — Proteção do cadastro público

Correção da PANELS-01: o cadastro **já tinha** um limite de 5 tentativas por minuto por IP (em `CreateNewUser`). O relatório anterior dizia que não havia limite, e foi corrigido.

O que foi acrescentado:

- **Limite por hora:** 20 cadastros por hora por IP, além dos 5 por minuto. Um escritório atrás de um único IP ainda consegue cadastrar algumas empresas; um script não cria em massa. As mensagens são diferentes para cada limite.
- **Campo-isca:** um campo invisível e fora da árvore de acessibilidade (`aria-hidden`, `tabindex=-1`). Se vier preenchido, o cadastro é recusado com uma mensagem neutra e nada é criado.
- **Verificação de e-mail:**
  - `Features::emailVerification()` ativado e `User implements MustVerifyEmail`;
  - nova tela `auth/verify-email`, com reenvio (o Fortify limita a 6 por minuto) e botão para sair;
  - o middleware `verified` protege `/dashboard`, todas as rotas do painel (`routes/tenant.php`), o RootAdmin e a API `v1/tenant` e `v1/admin`.
  - Perfil, segurança e aparência continuam acessíveis, para o usuário poder corrigir o e-mail.
- **Usuários criados pela equipe** também recebem o link de confirmação (após o commit da transação). O texto da tela de equipe foi atualizado, porque antes dizia "Não há envio de e-mail".
- **Contas existentes:** a migration `mark_existing_users_as_verified` marca como verificados os usuários que já existem, para que ninguém seja bloqueado no deploy. Ela não tem reversão.
- **Isolamento:** um link de verificação de outra conta é recusado (403), e uma conta não verificada recebe 403 na API do tenant.

### R2 — Identificação de homologação

- Nova coluna **`tenants.is_homolog`** (booleano, com índice, fora do `$fillable`). A migration marca as empresas que já se chamavam `[HOMOLOG]…`. No banco local, foram 6.
- `Tenant::isHomolog()`, os indicadores do RootAdmin e o expurgo passam a usar **só a marca**. O nome não decide mais nada.
- O cadastro público recusa nomes que começam com o prefixo reservado, sem diferenciar maiúsculas e espaços (`[HOMOLOG]`, `[homolog]`, `[ Homolog-SRV ]`). A mensagem é: "Este nome é reservado. Escolha outro nome para a empresa." Um nome como "Homologação Técnica Ltda" continua aceito.
- Novo comando **`homolog:mark {tenant}`**: exige terminal interativo e confirmação, e registra auditoria (`platform.tenant_marked_homolog`). É a única forma de marcar uma empresa.
- **Mudança de procedimento:** a homologação em produção não pode mais criar empresas `[HOMOLOG]` pelo cadastro. Agora é preciso cadastrar com um nome comum e rodar `homolog:mark <id>` na VPS.

## 2. Desempenho (R3 e R4)

| Ponto | Correção | Medida (teste temporário, já removido) |
|---|---|---|
| R3: o `HandleInertiaRequests` resolvia a empresa de novo e consultava a lista separadamente | Reaproveita a membership do `TenantContext` quando a rota já a resolveu, e carrega a lista de empresas acessíveis uma vez por requisição para `tenant` e `tenants`. O comportamento é o mesmo do `TenantResolver::resolve()` (testes em `SharedTenantPropsTest`). | `/dashboard`: 18 → **16** consultas. `/clientes`: 8 → **6**. |
| R4: "nova solicitação" carregava todos os clientes ativos | Busca no servidor (nome, e-mail, telefone), limite de 50, recarga parcial (`router.reload` com `only`) depois de 300 ms sem digitar. O cliente escolhido continua selecionado após uma nova busca. | 300 clientes: 7 → **5** consultas; resposta de 20,5 KB → **11,4 KB**; 300 → **50** opções. |
| Atendimento: a conversa selecionada carregava cliente, serviço e atendente um a um | Carregamento antecipado (`with`) | −3 consultas ao abrir uma conversa |

Não alterado: o limite de 50 conversas na caixa do atendimento. As listas já são limitadas e ordenadas por atividade, e paginar exigiria redesenhar a tela. Fica como risco residual.

## 3. Arquivos modificados

**Aplicação:**
- `app/Actions/Fortify/CreateNewUser.php`
- `app/Console/Commands/MarkHomologTenant.php` (novo)
- `app/Http/Controllers/RootAdmin/DashboardController.php`
- `app/Http/Controllers/Tenant/AssistantController.php`
- `app/Http/Controllers/Tenant/ServiceRequestController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Models/Tenant.php`
- `app/Models/User.php`
- `app/Providers/FortifyServiceProvider.php`
- `app/Services/HomologPurgeService.php`
- `app/Services/TenantUserService.php`
- `config/fortify.php`
- `routes/web.php`, `routes/tenant.php`, `routes/rootadmin.php` e `routes/api.php`

**Banco:**
- `database/migrations/2026_11_18_134100_add_is_homolog_to_tenants_table.php`
- `database/migrations/2026_11_18_134101_mark_existing_users_as_verified.php`
- `database/factories/TenantFactory.php` (estado `homolog()`)

**Interface:**
- `resources/js/pages/auth/login.tsx`
- `resources/js/pages/auth/register.tsx`
- `resources/js/pages/auth/verify-email.tsx` (novo)
- `resources/js/pages/tenant/requests/create.tsx`
- `resources/js/pages/tenant/team/index.tsx` (texto)

**Testes:**
- Novos arquivos: `Tenancy/MarkHomologTenantTest.php` e `Tenancy/SharedTenantPropsTest.php`.
- Novos casos em:
  - `Tenancy/PurgeHomologDataTest.php`
  - `Tenancy/RegistrationTest.php`
  - `Auth/AuthenticationTest.php`
  - `Management/TenantScreensTest.php`
  - `Operations/ServiceRequestWorkflowTest.php`
- Testes ajustados ao novo contrato (nenhum foi apagado):
  - `Auth/AccountStatusTest` e `Tenancy/RegistrationTest` diziam que a conta sem e-mail verificado acessava o painel. Agora ela é levada à confirmação.
  - `Platform/Release102Test`, `RootAdmin/ConsoleTest`, `Websites/SubdomainSiteTest`, `Tenancy/PurgeHomologDataTest` e `Support/VisitorSite` passaram a marcar a homologação pela coluna.

**Documentação:**
- `docs/VETORSMART-PANELS-01.md`: correção da R1.
- `docs/VETORSMART-PANELS-02.md` (este arquivo).

## 4. Evidências de testes

| Verificação | Resultado |
|---|---|
| Pest completo (MySQL, `VETORSMART_CONCURRENCY=1`) | **513 de 513 aprovados**, 0 pulados, 3273 asserções (eram 486) |
| PHPStan | 0 erros |
| Pint | ok (`--dirty`) |
| TypeScript (`tsc --noEmit`) | 0 erros |
| Build de produção | ok |
| Isolamento entre empresas | Suíte `Tenancy` e `Operations` ok. Novos casos: a busca de clientes nunca lista outra empresa; um link de verificação de outra conta é recusado; o expurgo preserva a empresa real. |
| Segurança do cadastro e da recuperação | Prefixo reservado (3 variações), campo-isca, limites por minuto e por hora, verificação obrigatória (painel e API), link de outra conta, envio para usuário criado pela equipe; reset de senha (testes existentes, mais o fluxo no navegador). |
| Migrations no banco **local** de desenvolvimento | Aplicadas: `retired_at` (pendente desde a WILDCARD-01), `is_homolog` (6 empresas marcadas) e verificação das contas existentes (0 pendentes) |

## 5. Homologação visual

### Como foi feita

O correio autorizou redefinir temporariamente as senhas das contas de homologação no ambiente local. Mesmo assim, a política de permissões desta sessão **bloqueou** a operação, e ela não foi contornada.

Em vez disso, foram criadas **contas novas pelo próprio cadastro público** no servidor local:
- `homolog.panels02b.owner@example.test` (empresa "Homologacao Panels 02 B", marcada com `homolog:mark`);
- um operador criado pela tela de equipe.

As senhas foram geradas no momento, ficaram só no scratchpad da sessão (permissão 600) e não aparecem em nenhum relatório. Os links de confirmação e de redefinição foram lidos do log de e-mail local (`MAIL_MAILER=log`).

### Fluxo funcional (Chromium): 24 de 24

- **Cadastro:** campo-isca invisível; prefixo `[HOMOLOG]` recusado com mensagem; conta nova levada a "Confirme seu e-mail"; painel bloqueado antes do link; e-mail enviado; painel liberado depois do link.
- **`homolog:mark`:** marcou a empresa.
- **Cliente:** cadastro pelo formulário; erro de validação exibido.
- **Nova solicitação:** a busca recarrega a lista; o cliente escolhido continua selecionado depois de outra busca; a solicitação é registrada.
- **Equipe:** operador adicionado e e-mail de confirmação enviado.
- **Operador:**
  - o login sem confirmação leva ao aviso;
  - depois da confirmação, `/clientes` fica liberado;
  - `/equipe` responde 403;
  - o menu não mostra "Usuários e permissões";
  - as configurações aparecem somente para leitura.
- **Recuperação de senha (celular, tema escuro):** link visível → pedido → confirmação de envio → link → nova senha → login.
- Nenhum erro de JavaScript.

### Matriz visual: Chromium e Firefox × desktop 1440×900 e celular 390×844 × claro e escuro

- **Proprietário, 19 telas:** painel, clientes (lista e formulário), solicitações (lista e nova), atendimento IA, agenda (semana e mês), serviços (lista e formulário), profissionais (lista e formulário), equipe, Meu Site, configurações, perfil, segurança, aparência e `/email/verify`.
- **Operador, 5 telas:** painel, clientes, solicitações, agenda e configurações.
- **Modal:** "Suspender acesso" na equipe abre e fecha com Esc.

Resultado: **196 verificações.**
- 188 aprovadas, todas com HTTP 200, sem rolagem horizontal, sem erro de JavaScript e com **0 violações de axe** (WCAG 2.0/2.1 A e AA).
- As outras 8 são o mesmo comportamento esperado: `/email/verify` acessado por uma conta **já verificada** redireciona para o painel (padrão do Fortify). Com conta não verificada, a tela aparece normalmente.

Os estados vazios foram vistos de verdade, porque a empresa nova não tinha agenda, serviços nem conversas. Ficaram 76 capturas no Chromium, no scratchpad da sessão (temporário, fora do repositório).

### Não executado

| Item | Motivo | Como concluir |
|---|---|---|
| **RootAdmin** (visual) | Não há credencial conhecida, e a redefinição local foi bloqueada pela política de permissões. Criar um administrador novo pelo `CreateRootAdmin` também não foi feito: seria criar uma conta privilegiada sem autorização direta. | Informar uma senha de homologação de root, ou rodar `php artisan` no terminal com uma conta de teste, e repetir a matriz. A cobertura automática do RootAdmin (Pest) continua aprovada. |
| WebKit | Faltam bibliotecas do sistema (`sudo`) | `sudo npx playwright install-deps webkit` |

Efeito colateral a registrar: ao instalar o Firefox e o Chromium compatíveis com o Playwright 1.64.1 em `~/.cache/ms-playwright`, o instalador **removeu os navegadores antigos do cache** (`chromium-1208`, `firefox-1509`, `webkit-2248`). É só cache, mas quem usava aquelas versões vai precisar baixá-las de novo.

## 6. Riscos remanescentes

1. **E-mail em produção (bloqueia o deploy).** Sem envio de e-mail funcionando, um cadastro novo não confirma o endereço e não entra no painel. Isso vale também para usuários criados pela equipe e para a recuperação de senha. Antes do deploy, é preciso conferir `MAIL_*` na VPS e enviar um e-mail de teste. O envio hoje é síncrono: uma falha do servidor de e-mail aparece como erro na hora do cadastro, mas a conta já terá sido criada (o "Reenviar" resolve).
2. **Procedimento de homologação mudou** (R2): é preciso usar o `homolog:mark` na VPS. Depois do deploy, as empresas `[HOMOLOG]` atuais recebem a marca pela migration.
3. As contas existentes foram marcadas como verificadas sem confirmação real. É uma decisão consciente, para não bloquear quem já usa o sistema.
4. A caixa do atendimento continua limitada a 50 conversas, sem paginação.
5. O campo-isca e os limites por IP reduzem abuso, mas não o impedem. Um CAPTCHA pode ser avaliado se surgir abuso real.
6. O RootAdmin não foi homologado visualmente, e o WebKit não foi testado.
7. Dados locais criados nesta etapa:
   - 2 empresas marcadas: "Homologacao Panels 02", de uma primeira tentativa interrompida, e "Homologacao Panels 02 B";
   - 4 usuários `homolog.panels02*@example.test`.

   O `homolog:purge --execute` remove tudo isso, e também as outras empresas de homologação locais.

## 7. Parecer técnico final

As correções críticas estão implementadas, testadas (513 de 513) e homologadas no painel do assinante, em dois navegadores, desktop e celular, temas claro e escuro, sem violações de acessibilidade.

O código está pronto para revisão e commit. **O deploy depende de:**
1. autorização para commit e push;
2. confirmação do envio de e-mail em produção;
3. execução das duas migrations novas;
4. homologação visual do RootAdmin, quando houver credencial.
