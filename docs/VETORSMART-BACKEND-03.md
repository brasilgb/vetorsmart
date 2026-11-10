# VETORSMART-BACKEND-03 — Login Universal, Cadastro, Console RootAdmin e Identidade Visual

**Status: CONCLUÍDO COM RESSALVAS.** Fluxos implementados e validados por 176 testes automatizados (173 aprovados, 3 pulados de 2FA), Pint, PHPStan (0 erros), TypeScript (0 erros), build e inspeção de rotas. Validação em navegador real **não foi executada**: Chrome não está instalado nesta máquina. A validação de interface foi feita por HTTP e por asserts de props Inertia (ver seção 15).

Data: 09/11/2026. Diretório: `gateway/vetor-smart/`. Banco: MySQL-compatível local (servidor real é MariaDB 11.8.6; ver seção 16).

---

## 1. Diagnóstico inicial

- `docs/VETORSMART-BACKEND-01.md` e `docs/VETORSMART-BACKEND-02.md` lidos. O 02 já existia e estava consistente com o código.
- `executed.md` anterior (BACKEND-02) confirmou o estado: clientes, categorias, serviços, profissionais e configurações já implementados.
- Autenticação: Fortify com login, cadastro e redefinição de senha. Login e cadastro tinham layout padrão do starter kit (cartão simples, sem identidade VetorSmart).
- Não havia tela de tenant além de `dashboard` vazia, nem console RootAdmin.
- Logo: `public/images/vetor-smart-logo.png` já existia (monograma V com nós de conexão, sem letreiro). Não foi sobrescrito.
- Fundo: nenhuma imagem de fundo aprovada encontrada no repositório.
- Frontend: Inertia React, Tailwind v4, componentes shadcn. `app.tsx` aplicava `AuthLayout` a todas as páginas `auth/*`.
- Git: `gateway/vetor-smart` é um repositório próprio (branch `main`, remoto `brasilgb/vetorsmart`). Alterações não commitadas, conforme decisão do usuário (git fica com o usuário).

## 2. Componentes reutilizados

| Componente existente | Uso |
|---|---|
| Fortify (login, cadastro, rate limiting, `authenticateUsing`) | Login universal. Sem autenticação duplicada |
| `CreateNewUser` → `TenantRegistrationService` | Cadastro transacional (reutilizado sem fluxo paralelo) |
| `TenantContext`, `TenantResolver`, middleware `tenant.resolve` | Tenant ativo, dashboard e troca de empresa |
| Gate `platform-admin` | Proteção do console RootAdmin |
| `TenantStatusService`, `SegmentService`, `AuditLogger` | Suspensão/ativação, segmentos e auditoria |
| `platform:root-admin` | Provisionamento do RootAdmin (não recriado) |
| `StoreSegmentRequest`, `UpdateSegmentRequest` (API) | Reutilizados pelo console |
| `POST api/v1/auth/switch-tenant` | Troca de empresa pela interface |
| Componentes `ui/*` (Button, Card, Badge, Input, Label, Checkbox, Sidebar), `PasswordInput`, `NavMain`, `NavUser` | Interface |

## 3. Login universal

- Uma única tela (`auth/login`) para RootAdmin, owner, admin e operator.
- Destino decidido pelo servidor (`App\Http\Responses\LoginResponse`):
  - RootAdmin → `/rootadmin`.
  - Demais perfis → `/dashboard`.
- A URL `intended` guardada na sessão é **ignorada**. Isso evita redirecionamento para site externo ou para área de outro perfil.
- Campos: e-mail, senha (com mostrar/ocultar), "Lembrar acesso" (Fortify `remember`), botão Entrar, link "Criar minha empresa".
- **Sem** link de recuperação de senha, porque não há mecanismo funcional de e-mail.
- Erros genéricos (não revelam se o e-mail existe nem se a conta está suspensa).
- Rate limiting: 5 tentativas por minuto por e-mail + IP. A sexta retorna **429**, antes de verificar a senha.
- Sessão regenerada no login; invalidada no logout (testado).
- Empresa suspensa: o usuário entra, mas `/dashboard` retorna 403. Usuário suspenso: login recusado e sessão existente encerrada na próxima requisição.
- Seleção segura de empresa: quando o usuário pertence a mais de uma, o cabeçalho mostra um seletor. A troca passa por `POST /api/v1/auth/switch-tenant`, que só aceita vínculo ativo em empresa ativa. Um `tenant_id` forjado retorna 403.

## 4. Cadastro de tenant

- Tela `auth/register` em **uma única tela**, sem wizard. Campos: nome da empresa, segmento (lista só segmentos ativos), nome do responsável, e-mail, senha e confirmação.
- Não pede CNPJ, endereço, telefone, WhatsApp, dados financeiros nem cartão.
- Fluxo: validação → transação (usuário, tenant, vínculo owner, configurações) → autenticação → redirecionamento ao dashboard.
- **Regra de duplicidade**: nomes de empresa podem se repetir. Cada empresa recebe um slug único (sufixo aleatório em caso de colisão). O e-mail do responsável continua único. A duplicidade por documento é recusada pelo banco quando o documento é informado.
- Rate limiting de cadastro: 5 por minuto por IP.
- Layout: casca compartilhada com o login, cartão centralizado, sem rolagem em viewport desktop usual (campos de 40px). Em telas menores, a página rola verticalmente.

## 5. Identidade visual

- Logo: símbolo existente, **sem letreiro**. Nome "VetorSmart" aparece como texto abaixo do símbolo.
- Variantes geradas a partir do original (que não foi alterado), em novos arquivos:
  - `public/images/vetor-smart-logo-transparent.png` (512 px, fundo branco removido);
  - `public/images/vetor-smart-logo-reduced.png` (128 px, para cabeçalhos e login);
  - `public/images/vetor-smart-favicon.png` (64 px, ligado no `app.blade.php`, sem remover os favicons existentes).
- Fundo: `public/images/auth-background.svg`, um fundo vetorial provisório (rede de nós azuis) com `preserveAspectRatio="xMidYMid slice"`, equivalente a `background-size: cover`. **Pendência**: substituir por imagem aprovada.
- Película: `rgba(15, 23, 42, 0.72)` sobre o fundo.
- Cartão: largura máxima 460 px, cantos de 20 px, sombra suave, padding 32 px.

## 6. Paleta CSS

Tokens centralizados em `resources/css/app.css`:

- `@theme inline`: `--color-vs-ink` (#0F172A), `vs-petrol` (#1E3A5F), `vs-blue` (#2563EB), `vs-blue-hover` (#1D4ED8), `vs-cyan` (#06B6D4), `vs-surface` (#F8FAFC), `vs-border` (#CBD5E1), `vs-text` (#0B1220), `vs-muted` (#475569).
- `:root`: mapeia os tokens de componentes (`--primary`, `--sidebar`, `--border` etc.) para a paleta.
- Componentes usam as utilidades `bg-vs-blue`, `text-vs-muted`, etc. Não há hex repetido nos componentes (exceto a película e a sombra do cartão, que são valores únicos).
- Cyan aparece só em indicadores pontuais (ícone de item concluído).
- Modo escuro do starter mantido sem ajuste de paleta. **Pendência**.

## 7. Layout administrativo

- Sidebar com fundo `#0F172A` (via `--sidebar`), logo no topo, item ativo em azul tecnológico.
- Menu depende do perfil:
  - Tenant: somente **Dashboard**. Clientes, serviços, profissionais e usuários têm API, mas **ainda não têm tela**; por isso não aparecem no menu (regra "apenas módulos existentes").
  - RootAdmin: Console, Empresas, Segmentos, Usuários, Auditoria.
- Cabeçalho claro com nome da empresa ativa (ou seletor, quando há várias), breadcrumbs, gatilho do sidebar e menu de perfil (NavUser com logout) no rodapé do sidebar.
- Mobile: sidebar recolhível (componente Sidebar do shadcn), conteúdo sem overflow horizontal por `min-w` com scroll interno nas tabelas.
- Sem chatbot nem botão de WhatsApp nas áreas administrativas.

## 8. Dashboard do tenant

- `GET /dashboard` com `tenant.resolve`.
- Indicadores **reais**: clientes ativos, serviços ativos, profissionais ativos (contagens com escopo de tenant).
- Solicitações e agendamentos **não** aparecem (módulos inexistentes). Nota explícita na tela.
- Primeira utilização: checklist "Configure sua empresa para começar" (dados da empresa, primeiro serviço, primeiro profissional). Não bloqueia nada.
- Usuário sem empresa ativa: 403. RootAdmin sem empresa: redirecionado ao console.

## 9. Console RootAdmin

Rotas em `routes/rootadmin.php`, prefixo `/rootadmin`, `auth` + `can:platform-admin`.

- **Dashboard**: total de empresas, ativas, suspensas, usuários; distribuição por segmento; cinco cadastros recentes. Totais por `GROUP BY`; nenhuma lista completa é carregada para contar.
- **Empresas** (`/rootadmin/tenants`): listagem paginada (15), filtros por nome/slug, status e segmento. Responsável carregado em consulta única (`owners.user`). Não há exclusão.
- **Detalhe da empresa** (`/rootadmin/tenants/{id}`): dados administrativos, configurações em somente leitura, usuários vinculados (paginados, sem senha nem token).
- **Suspender/Ativar**: suspensão exige checkbox de confirmação (`accepted_if:status,suspended`). Ambas as ações são auditadas com o RootAdmin como responsável.
- **Segmentos** (`/rootadmin/segments`): listar, cadastrar, editar nome/descrição e ativar/desativar. **Sem rota de exclusão** (405 testado).
- **Usuários** (`/rootadmin/users`): somente leitura, com filtros por busca, perfil, vínculo e empresa. Sem senha e sem alteração de permissão.
- **Auditoria** (`/rootadmin/audit`): paginada (30), filtros por ação, empresa e período. Metadados já são sanitizados na gravação.

**Limitação de leitura**: visualizações do RootAdmin não geram registro de auditoria (apenas mutações). Decisão mantida do BACKEND-01.

## 10. Provisionamento do RootAdmin

- Comando existente: `php artisan platform:root-admin <email>`. Não foi recriado.
- Validação já coberta por testes: execução interativa obrigatória; senha oculta; mínimo de 12 caracteres; e-mail validado; conta existente é promovida sem alterar a senha; sem senha no log de auditoria.
- **Não executado em nenhum ambiente nesta etapa.** Nenhuma credencial foi criada, inventada ou registrada. A execução de login do RootAdmin pela tela também não foi feita, por depender do comando interativo. Pendência explícita (seção 16).
- Não há botão público para criar RootAdmin; `is_root_admin` não é mass-assignable.

## 11. Rotas criadas ou alteradas

**Alteradas**
- `GET /dashboard`: agora controller `DashboardController` com `tenant.resolve`. Antes era página vazia.
- `routes/web.php`: inclui `routes/rootadmin.php`.
- `app/Providers/FortifyServiceProvider.php`: `LoginResponse` vinculado ao contrato do Fortify; `login` com autenticação só para contas ativas.

**Criadas (web)**

| Método | Rota | Permissão |
|---|---|---|
| GET | `/rootadmin` | platform-admin |
| GET | `/rootadmin/tenants` | platform-admin |
| GET | `/rootadmin/tenants/{tenant}` | platform-admin |
| POST | `/rootadmin/tenants/{tenant}/status` | platform-admin (+ confirmação ao suspender) |
| GET | `/rootadmin/segments` | platform-admin |
| POST | `/rootadmin/segments` | platform-admin |
| PATCH | `/rootadmin/segments/{segment}` | platform-admin |
| GET | `/rootadmin/users` | platform-admin (somente leitura) |
| GET | `/rootadmin/audit` | platform-admin |

Rotas de API existentes (sem alteração de contrato): `auth/switch-tenant`, `auth/tenants`, `auth/user`, `auth/logout` e os recursos do tenant do BACKEND-02.

## 12. Permissões

- **Tenant** (inalterada desde o BACKEND-02): owner e admin gerenciam; operator opera clientes e consulta o restante.
- **Console RootAdmin**: somente quem tem `is_root_admin` ativo. Usuário comum recebe 403 em todas as rotas `/rootadmin/*` (testado) e 403 em `POST` de status e segmento.
- RootAdmin não recebe acesso a dados operacionais de tenant (403 em clientes, serviços, profissionais e categorias).
- Sem escalonamento: nenhuma rota permite a um usuário comum se tornar RootAdmin ou alterar perfis de outro tenant.

## 13. Testes executados

Suíte completa: **`php artisan test --compact` → 176 testes, 173 aprovados, 0 falhas, 3 pulados** (os 3 pulados são de 2FA, desativado na configuração).

Novos nesta etapa (além dos testes do BACKEND-02):

- `Auth/UniversalLoginTest` (11): login de owner, login de RootAdmin, senha inválida, sessão regenerada, logout, rate limiting (429), intended externo ignorado, empresa suspensa, usuário suspenso, RootAdmin sem empresa, ausência de auditoria em login.
- `Auth/AccountStatusTest`: + empresa cancelada sem acesso.
- `Interface/TenantRegistrationScreenTest` (5): tela de cadastro com segmentos ativos apenas; tela de login; cadastro mínimo; erros de campos; mesmo nome de empresa com slugs distintos.
- `Interface/TenantDashboardTest` (3): indicadores reais contando só ativos; checklist; operador vê dashboard.
- `Tenancy/MultiTenantSelectionTest` (4): lista de empresas do usuário; troca para empresa própria; empresa alheia recusada (403); lista compartilhada só com vínculos próprios.
- `RootAdmin/ConsoleTest` (15): acesso negado a comuns e visitantes; totais do dashboard; filtros de empresas; detalhe sem credenciais; suspensão exige confirmação e é auditada; ativação auditada; empresas não excluíveis (405); segmentos sem exclusão (405); usuários sem senha; auditoria filtrável e sem segredos; RootAdmin redirecionado ao console.

Ajustes em testes existentes (comportamento alterado de propósito): `DashboardTest` e `AccountStatusTest` (login sem verificação) agora criam vínculo de empresa, porque `/dashboard` é o painel do tenant.

## 14. Resultados de qualidade

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | 176 testes: 173 aprovados, 0 falhas, 3 pulados (2FA) |
| Pint (`--dirty`) | aprovado |
| PHPStan | **0 erros** (8 corrigidos nesta etapa, na origem) |
| TypeScript (`tsc --noEmit`) | **0 erros** |
| Build (`npm run build`) | aprovado |
| Inspeção de rotas | 9 rotas `rootadmin.*`; 29 rotas `api/v1/tenant/*` (BACKEND-02) |

**Problemas encontrados e corrigidos nesta etapa**
1. **Regra `accepted` implícita no Laravel**: `confirm` era exigido em toda requisição, bloqueando também a ativação. Corrigido para `accepted_if:status,suspended`. Teste de ativação falhou e pegou o problema.
2. **Rate limiting retorna 429**, não erro de sessão. Teste ajustado ao comportamento correto.
3. **Layout duplicado**: `rootadmin/dashboard` recebeu `.layout` manual, somando-se ao `AppLayout` do `app.tsx`. Removido.
4. **Paginação com HTML**: labels do paginador eram inseridos com `dangerouslySetInnerHTML`. Substituído por texto (entidades « e » tratadas).
5. **Segmento: toggle** usava `form.patch` com opção `data`, não suportada. Trocado por `router.patch`.
6. **PHPStan**: tipagem de agregação (Eloquent vs query builder), `instanceof` sempre verdadeiro, anotação `list` incorreta e `?->` desnecessário. Corrigidos na origem.

## 15. Evidências de navegador

**Não executadas.** Chrome não está instalado nesta máquina (não encontrado em `/opt/google/chrome` nem no caminho do Windows). Nenhuma captura de tela foi gerada.

Evidências **reais** obtidas por HTTP contra `php artisan serve` (`127.0.0.1:8000`):

| Verificação | Resultado |
|---|---|
| `GET /login` | 200 |
| `GET /register` | 200 |
| `GET /images/vetor-smart-logo-reduced.png` | 200 `image/png` |
| `GET /images/auth-background.svg` | 200 `image/svg+xml` |
| `GET /images/vetor-smart-favicon.png` | 200 `image/png` |

Os itens abaixo estão **cobertos por asserts de props Inertia e testes HTTP**, mas **não validados visualmente**: login desktop/mobile, cadastro desktop/mobile, dashboard, console, sidebar responsivo, contraste, ausência de rolagem no cadastro desktop.

## 16. Pendências reais

1. **Homologação em navegador real** (Chrome não instalado). Pendente: login e cadastro desktop/mobile, dashboard, console, sidebar, contraste e rolagem do cadastro. Recomendado: instalar navegador e repetir.
2. **Primeiro RootAdmin não provisionado**. O comando é interativo e não foi executado. Execução: `php artisan platform:root-admin <email>`, em terminal. Não executar em produção sem autorização.
3. **Imagem de fundo aprovada**: não existe no repositório. Usado fundo SVG provisório.
4. **Variante do logo para fundo escuro**: gerada somente a versão transparente. Não há versão específica (branca) para fundos escuros.
5. **Dark mode**: tokens aplicados apenas ao tema claro.
6. **Interface de clientes, serviços, profissionais e usuários do tenant**: não existe; somente APIs. Por isso o menu do tenant contém apenas Dashboard.
7. **Suspensão de empresa pelo console não foi exercitada no navegador**; coberta por teste HTTP.
8. **Sessões seguras em produção**: `SESSION_SECURE_COOKIE` não está definido no `.env`. Em produção (HTTPS), definir `true`.
9. **`.env.example` com segredos reais** (senha do MySQL e `APP_KEY`), já alterado antes desta etapa. Não incluído em commit. Recomendado: restaurar placeholders e rotacionar senha e chave.
10. **Banco de homologação**: registros de teste com prefixo `[HOMOLOG]` criados no `vetorsmart` (BACKEND-02 e este). Remover antes de uso real.
11. **Recuperação de senha**: o Fortify mantém as rotas, mas o link foi removido da tela de login. Sem envio de e-mail, não é funcional.
12. **MySQL 8 não testado**: servidor local é MariaDB 11.8.6.
13. **Suspensão de usuário** (conta) não tem endpoint de administração.

## 17. Próximos passos

1. Instalar um navegador e executar a homologação visual pendente (seção 15), salvando evidências.
2. Provisionar o primeiro RootAdmin pelo comando interativo, com autorização.
3. Construir as telas de clientes, serviços, profissionais e usuários do tenant, reutilizando `Pagination`, `Card` e a casca de layout.
4. Substituir o fundo provisório por imagem aprovada e definir a variante do logo para fundo escuro.
5. Ajustar o modo escuro à paleta VetorSmart.
6. Definir se visualizações do RootAdmin devem ser auditadas.
