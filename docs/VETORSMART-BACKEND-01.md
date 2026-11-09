# VETORSMART-BACKEND-01 — Fundação do SaaS, Multi-tenancy, Autenticação e Administração

**Status: CONCLUÍDO**, com as ressalvas listadas em *Pendências* e *Desvios da instrução*.

Data: 09/11/2026. Projeto: `gateway/vetor-smart` (Laravel 13, PHP 8.4, Fortify, Inertia React).

---

## 1. Diagnóstico inicial

- Projeto criado a partir do starter kit React do Laravel (`laravel/react-starter-kit`), com Fortify 1.x e Inertia v3.
- Autenticação: Fortify com registro e redefinição de senha habilitados; 2FA e passkeys não habilitados em `config/fortify.php`.
- Rotas de `dashboard` e `settings` exigiam `verified`, o que contrariava o requisito "login sem verificação de e-mail". Ajustado.
- Banco configurado no `.env` como MySQL (banco `vetorsmart`). Não havia PostgreSQL disponível nesta máquina (sem `pdo_pgsql`, sem servidor).
- Migrations: apenas as padrões do Laravel. Nenhum código de domínio existia.
- Baseline antes das alterações: 28 testes (25 passando, 3 pulados).
- Projeto não é um repositório próprio: o diretório aparece como não rastreado no repositório pai. Não houve commits.

## 2. Arquitetura implementada

Monólito Laravel com isolamento lógico por `tenant_id`. Sem microserviços e sem dependências novas.

| Componente | Caminho | Papel |
|---|---|---|
| Contexto do tenant | `app/Services/Tenancy/TenantContext.php` | Guarda o membership ativo da requisição ou do job. Binding `scoped`. `runFor()` restaura o contexto anterior. |
| Resolução | `app/Services/Tenancy/TenantResolver.php` | Só considera memberships ativos em tenants ativos. |
| Middleware | `app/Http/Middleware/ResolveTenant.php` (alias `tenant.resolve`) | Resolve o tenant a partir da sessão do servidor, validado contra memberships. Nunca lê `tenant_id` do input. |
| Conta suspensa | `app/Http/Middleware/EnsureAccountIsActive.php` | Desloga sessões de contas suspensas depois do login. Anexado ao grupo `web`. |
| Isolamento em models | `app/Concerns/BelongsToTenant.php` | Escopo explícito `forTenant()`. **Sem Global Scope**, para não filtrar operações do RootAdmin. |
| Permissões | `app/Policies/TenantPolicy.php` | Regras de owner/admin/operator sobre o tenant ativo. |
| RootAdmin | `Gate::define('platform-admin')` em `AppServiceProvider` | Autoridade global, separada dos perfis. Não concede acesso a dados de tenant. |
| Cadastro | `app/Services/TenantRegistrationService.php` | Cria usuário, tenant, vínculo owner e configurações em uma transação. |
| Auditoria | `app/Services/AuditLogger.php` | Registra ações. Remove recursivamente chaves com `password`, `token`, `secret`, `two_factor`, `remember`, `key`. |
| Serviços | `TenantUserService`, `TenantSettingsService`, `TenantStatusService`, `SegmentService` | Regras de negócio. Controllers só validam e delegam. |
| Job base | `app/Jobs/TenantScopedJob.php` | Recebe `tenantId` e `userId` explicitamente e revalida tenant ativo e membership antes de processar. |
| Provisionamento | `php artisan platform:root-admin {email}` (`app/Console/Commands/CreateRootAdmin.php`) | Cria ou promove um RootAdmin. Senha solicitada de forma oculta. Exige terminal interativo. |

Decisões técnicas:

- **Enums** em `app/Enums/`: `TenantStatus`, `TenantRole`, `MembershipStatus`, `UserStatus`.
- **Sessão como autenticação da API**: a API v1 usa o guard `web` do starter kit (cookie de sessão). Não foi instalado Sanctum, por restrição de dependências. Logout reaproveita o fluxo existente.
- **Cadastro público** usa o fluxo do Fortify (`register.store`). `CreateNewUser` valida e delega ao serviço transacional. Campos `is_root_admin` e `status` não são mass-assignable.
- **Rate limiting do cadastro**: 5 tentativas por minuto por IP, aplicadas em `CreateNewUser` com `RateLimiter::attempt`. Tentativa de anexar o throttle à rota pelo Fortify não funcionou e foi descartada.
- **Login**: `Fortify::authenticateUsing` aceita apenas contas ativas. Conta suspensa recebe a mesma mensagem de senha incorreta, sem revelar o status.
- **Respostas de acesso**: troca de tenant para empresa sem acesso retorna 403, e recursos de outra empresa retornam 404 (busca escopada).

## 3. Tabelas criadas ou modificadas

| Tabela | Operação | Observações |
|---|---|---|
| `segments` | Criada | `slug` único. Seeder idempotente com os 8 segmentos iniciais. |
| `tenants` | Criada | `uuid` e `slug` únicos, `document` único e nullable, `segment_id` nullable (`nullOnDelete`), `status` (`active`/`suspended`/`cancelled`), `timezone`. Sem `deleted_at`, por não haver necessidade justificada. |
| `tenant_users` | Criada | `(tenant_id, user_id)` único. Índice `(user_id, status)`. Suporta usuário em várias empresas. |
| `tenant_settings` | Criada | `tenant_id` único (uma configuração por empresa). `settings` em JSON. |
| `audit_logs` | Criada | Append-only, sem `updated_at`. Índices em `(tenant_id, created_at)` e `(auditable_type, auditable_id)`. |
| `users` | Modificada | Acrescentadas `status` (padrão `active`) e `is_root_admin` (padrão `false`). Migration separada. |

**Normalização para evitar duplicação**: `tenants` é a fonte de nome legal, documento, e-mail, telefone e fuso. `tenant_settings` guarda apenas o que é operacional: `business_name` (nome exibido), WhatsApp, endereço, cidade, UF, locale e JSON. Telefone e fuso não se repetem.

**Ordem das migrations**: `segments` antes de `tenants`, porque há chave estrangeira.

## 4. Rotas adicionadas

Prefixo `/api/v1`, grupo `web` + `auth`. Respostas em Eloquent API Resources (`{"data": ...}`). Erros de validação em 422, permissão em 403, inexistente ou de outra empresa em 404, não autenticado em 401.

**Autenticação**

| Método | Rota | Descrição |
|---|---|---|
| GET | `auth/user` | Usuário autenticado. |
| GET | `auth/tenants` | Empresas ativas às quais o usuário pertence. |
| POST | `auth/switch-tenant` | Troca o tenant ativo. Aceita apenas memberships ativos em tenants ativos. |
| POST | `auth/logout` | Encerra a sessão (204). |

**Tenant** (middleware `tenant.resolve`)

| Método | Rota | Permissão |
|---|---|---|
| GET | `tenant/settings` | Qualquer membro. |
| PATCH | `tenant/settings` | Owner, admin. |
| GET | `tenant/users` | Owner, admin. |
| POST | `tenant/users` | Owner, admin (admin só cria operator). |
| PATCH | `tenant/users/{membershipId}` | Owner (qualquer); admin (apenas operator). |
| DELETE | `tenant/users/{membershipId}` | Mesma regra do PATCH. Desativa o vínculo. |

**Administração global** (`can:platform-admin`)

| Método | Rota | Descrição |
|---|---|---|
| GET | `admin/tenants` | Lista empresas. |
| GET | `admin/tenants/{tenant}` | Consulta empresa. |
| POST | `admin/tenants/{tenant}/activate` | Ativa empresa. Auditado. |
| POST | `admin/tenants/{tenant}/suspend` | Suspende empresa. Auditado. |
| GET | `admin/segments` | Lista segmentos. |
| POST | `admin/segments` | Cria segmento. Auditado. |
| PATCH | `admin/segments/{segment}` | Edita segmento. Auditado. |

Rotas web: `/register` (formulário com empresa e segmento) e as rotas de login/logout do Fortify já existentes.

## 5. Regras de isolamento

1. Tenant ativo vem da sessão do servidor, validada contra memberships ativos. Nunca do input.
2. `tenant_id` enviado pelo cliente é ignorado em criação e atualização.
3. Membership de outra empresa é buscado com `forTenant()` e retorna 404.
4. Troca de tenant exige membership ativo e tenant ativo; caso contrário, 403.
5. Vínculos são validados no serviço: cada `tenant_users` pertence ao tenant do contexto.
6. O contexto é restaurado ao final de cada requisição e de cada job (`finally`). Teste verifica que não há vazamento.
7. RootAdmin **não contorna** isolamento: sem membership, recebe 403 em rotas de tenant.
8. Jobs recebem tenant e usuário explicitamente, revalidam ambos antes de processar e não fazem nada se a revalidação falhar.

## 6. Permissões

| Ação | Owner | Admin | Operator |
|---|---|---|---|
| Ver configurações | ✔ | ✔ | ✔ |
| Alterar configurações | ✔ | ✔ | ✘ |
| Listar usuários | ✔ | ✔ | ✘ |
| Criar operator | ✔ | ✔ | ✘ |
| Criar admin ou owner | ✔ | ✘ | ✘ |
| Alterar/desativar owner ou admin | ✔ | ✘ | ✘ |
| Alterar/desativar operator | ✔ | ✔ | ✘ |

Regras adicionais:
- A empresa precisa manter ao menos um owner ativo. Rebaixar ou desativar o último owner retorna 422.
- RootAdmin: `is_root_admin` não é atribuível publicamente, e a conta precisa estar ativa para ter a autoridade.

## 7. Testes realizados e resultados

Executados com `php artisan test --compact` em **MySQL** (banco `vetorsmart_test`).

- **Resultado final: 80 testes, 77 aprovados, 3 pulados, 227 assertions.**
- Os 3 pulados são os testes de 2FA (`Settings/SecurityTest`), pulados porque 2FA está desativado na configuração, conforme a instrução.
- Baseline anterior: 28 testes, 25 aprovados, 3 pulados.

Cobertura por item da seção 11 do `correio.md`:

| # | Item | Arquivo de teste |
|---|---|---|
| 1 | Cadastro de empresa e proprietário | `Tenancy/RegistrationTest` |
| 2 | Rollback em cadastro incompleto | `Tenancy/RegistrationTest` (falha simulada no AuditLogger) |
| 3 | Login sem verificação de e-mail | `Auth/AccountStatusTest` |
| 4 | Isolamento entre tenants | `Tenancy/TenantIsolationTest` |
| 5 | Bloqueio de registros de outra empresa | `Tenancy/TenantIsolationTest` |
| 6 | Tentativa de manipular `tenant_id` | `Tenancy/TenantIsolationTest` |
| 7 | Controle de permissões | `Tenancy/TenantPermissionsTest` |
| 8 | Usuários suspensos | `Auth/AccountStatusTest` |
| 9 | Tenants suspensos | `Auth/AccountStatusTest`, `Tenancy/TenantIsolationTest` |
| 10 | Acesso do RootAdmin | `Tenancy/PlatformAdminTest` |
| 11 | Acesso RootAdmin por usuário comum | `Tenancy/PlatformAdminTest` |
| 12 | Unicidade e integridade dos vínculos | `Tenancy/TenantPermissionsTest` |
| 13 | Troca segura de tenant | `Tenancy/TenantIsolationTest` |
| 14 | Auditoria das operações | `Tenancy/AuditLogTest`, `Tenancy/PlatformAdminTest` |

Testes adicionais: contexto restaurado após job, job não executa com tenant suspenso ou membership removido (`Tenancy/TenantScopedJobTest`); comando `platform:root-admin` (`Tenancy/CreateRootAdminCommandTest`); seeder idempotente (`Tenancy/SegmentSeederTest`); limite de cadastros por IP (`Tenancy/RegistrationTest`).

Outras verificações:

- **Pint** (`--dirty`): aprovado.
- **PHPStan** (Larastan, nível do projeto): 0 erros. Nenhuma supressão adicionada.
- **Build do frontend** (`npm run build`): aprovado.
- **TypeScript** (`tsc --noEmit`): 12 erros, todos em arquivos não alterados (`app-header`, `app-logo`, `app-shell`, `nav-user`, `auth-split-layout`, `welcome`). Nenhum em `register.tsx`.
- **Rotas**: 17 rotas `api/v1` conferidas com `route:list`.
- **Migrations**: aplicadas no banco de desenvolvimento `vetorsmart` (MySQL), com `migrate:status` confirmando todas como executadas. Seeder de segmentos executado.

**Não testado**: navegação no navegador (formulário de cadastro renderizado apenas via build e testes HTTP), e-mail (não há envio), e integridade de PostgreSQL (ver desvios).

## 8. Arquivos relevantes

- Models: `app/Models/{Tenant,Segment,TenantUser,TenantSetting,AuditLog,User}.php`
- Enums: `app/Enums/{TenantStatus,TenantRole,MembershipStatus,UserStatus}.php`
- Trait: `app/Concerns/BelongsToTenant.php`
- Serviços: `app/Services/` (ver seção 2)
- Middleware: `app/Http/Middleware/{ResolveTenant,EnsureAccountIsActive}.php`
- Policy: `app/Policies/TenantPolicy.php`
- Controllers: `app/Http/Controllers/Api/V1/` (inclui `Admin/`)
- Requests e Resources: `app/Http/Requests/Api/V1/`, `app/Http/Resources/Api/V1/`
- Rotas: `routes/api.php` (novo), `routes/web.php`, `routes/settings.php`
- Providers: `AppServiceProvider` (scoped binding e gate), `FortifyServiceProvider` (login, cadastro, views)
- Ações: `app/Actions/Fortify/CreateNewUser.php`
- Comando: `app/Console/Commands/CreateRootAdmin.php`
- Job base: `app/Jobs/TenantScopedJob.php`
- Migrations: `database/migrations/2026_11_09_1837*` a `..._1838*`
- Factories e seeders: `database/factories/`, `database/seeders/`
- Frontend: `resources/js/pages/auth/register.tsx`
- Configuração de testes: `phpunit.xml`

## 9. Desvios da instrução

1. **MySQL em vez de PostgreSQL.** O `correio.md` pede PostgreSQL. Você orientou o uso de MySQL, e o projeto foi configurado assim. Não houve validação de PostgreSQL. As migrations usam tipos portáveis (`json`, não `jsonb`).
2. **`phpunit.xml` mudou de SQLite para MySQL** (`vetorsmart_test`), para que os testes rodem no mesmo motor do desenvolvimento.
3. **Subpastas novas em `app/`** (`Enums`, `Services`, `Policies`, `Jobs`, `Concerns`). São subdiretórios dentro de `app/`, não pastas-base novas. Se o padrão do projeto proibir isso, a mudança é mover os arquivos.
4. **Verificação de e-mail removida das rotas** (`verified`). A instrução pede que login não dependa dela.
5. **Documentação**: este arquivo foi criado porque o `correio.md` exige o relatório `docs/VETORSMART-BACKEND-01.md`.

## 10. Pendências

1. **Validar PostgreSQL**, se a exigência original for mantida: instalar o servidor, `pdo_pgsql`, ajustar `DB_CONNECTION` e rodar a suíte.
2. **Teste de navegador** do fluxo de cadastro e de login (não executado).
3. **Auditoria de leituras do RootAdmin**: apenas mutações são auditadas. Listagens e consultas não geram registro, para evitar ruído. Se a exigência for auditar leituras, é preciso decidir o escopo.
4. **Job base sem uso em produção**: `TenantScopedJob` existe e está testado, mas nenhum job real o utiliza ainda.
5. **Provisionamento do primeiro RootAdmin** não foi executado em nenhum ambiente. O comando está testado. Execução: `php artisan platform:root-admin <email>`.
6. **Seeder legado**: `DatabaseSeeder` mantém o usuário `test@example.com` do starter kit. Pode ser removido em produção.
7. **Atribuição de `email_verified_at`** em `ProfileController::update` continua zerando a verificação ao trocar e-mail. Sem efeito prático, pois verificação está desativada, mas o código ainda existe.
8. **Exigência de `password_confirmation`** na criação de usuário pela API (herdada de `passwordRules()`). Clientes precisam enviar o campo.
9. **Sessão como autenticação da API**: clientes externos (não-SPA) precisariam de um mecanismo de token. Requer decisão e, se aprovado, instalação do Sanctum.
10. **Ausência de cancelamento de tenant**: o status `cancelled` existe no enum, mas nenhuma operação o aplica.
11. **Campos de `tenants` sem endpoint**: documento, telefone, e-mail e fuso não são editáveis pela API. Só a administração global pode alterar status.

## 11. Próxima etapa recomendada

1. Decidir o banco definitivo (MySQL ou PostgreSQL) e, se for PostgreSQL, validar a suíte nele.
2. Adicionar teste de navegador do cadastro e do login.
3. Definir se a auditoria de leitura do RootAdmin é necessária.
4. Desenhar a primeira funcionalidade de domínio (catálogo de serviços ou clientes) usando `BelongsToTenant` e `TenantScopedJob` como padrão.
