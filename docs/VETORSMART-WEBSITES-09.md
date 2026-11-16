# VETORSMART-WEBSITES-09 — Sites públicos por segmento e tenant

Data: 16/11/2026. Base: `ce8404b`. Branch: `main`. Ambiente: desenvolvimento local. Nenhum commit, push ou deploy.

> **Atualizado pela WEBSITES-09.1:** o domínio próprio verificado deixou de redirecionar e passou a exibir o site, como endereço canônico da empresa. Veja `docs/VETORSMART-WEBSITES-09.1.md`.

## Parecer

**Pronta para revisão e commit.** Os sites das empresas agora ficam em `https://vetorsmart.com.br/{segmento}/{empresa}`, sem subdomínio e sem DNS por empresa. Gates automatizados verdes, nenhuma migration nova e homologação no navegador com 88 de 88 verificações aprovadas. Para implantar, falta configurar `PLATFORM_PUBLIC_URL` e `PLATFORM_CENTRAL_HOST` em produção (seção 8).

## 1. Diagnóstico das rotas anteriores

| Item | Como era |
| --- | --- |
| Resolução do site | Pelo **host**. `LandingController` entregava `SiteController::show` quando o host não era o central; `WebsiteResolver::resolve()` procurava um `TenantDomain` verificado. |
| Endereço da empresa | Subdomínio da plataforma (`clima-total.<base>`), criado em Meu Site → Domínios, ou domínio próprio verificado por TXT. Sem domínio, o site não tinha endereço. |
| Atendimento público | `/atendimento`, `/atendimento/conversa` e `/atendimento/mensagens` no host da empresa, com cookie `vs_assistant` no caminho `/`. |
| Limite de requisições | `assistant-public` por IP + host. |
| Proteção do host | `SiteHostGuard` só liberava `/`, `/sitemap.xml`, `/robots.txt` e `/atendimento*` em hosts que não fossem o central. |
| SEO | Sitemap e robots por host. `public/robots.txt` estático encobria a rota no servidor web. |
| Slugs | `segments.slug` e `tenants.slug` já eram únicos na plataforma inteira (índices `unique`), gerados por `Str::slug` e estáveis depois de criados. |
| Publicação | `TenantWebsite` com rascunho, versões imutáveis (`WebsiteVersion`), publicar e retirar do ar. Prévia autenticada em `/meu-site/previa`. |
| Painel | Mostrava "Disponível após verificar um domínio". A prévia usava `<Link>` do Inertia para uma página Blade. |

Defeitos que já existiam e foram corrigidos:

- **JSON-LD quebrado:** no corpo do template, a chave `'@context'` era compilada como a diretiva Blade `@context` e despejava código PHP dentro do `<script type="application/ld+json">`. Agora o JSON é montado no bloco `@php`, e há um teste de regressão.
- **Margem lateral do título:** `.hero { padding: 64px 0 40px }` anulava o recuo lateral de `.wrap`, e no mobile o título encostava na borda.
- O link de prévia do painel usava o `<Link>` do Inertia para uma resposta Blade. Agora é um `<a target="_blank">`.

## 2. Arquitetura implementada

- **`App\Services\Websites\PublicSiteAddress`** gera os endereços absolutos `{public_url}/{segmento}/{empresa}`, `/servicos` e `/atendimento`. Empresa sem segmento não tem endereço.
- **`App\Services\Websites\ReservedPaths`** junta o primeiro segmento de todas as rotas registradas (calculado na hora, sem lista para manter) com `platform.reserved_paths` (pastas e arquivos de `public/`). Um segmento nunca recebe um desses nomes (`SegmentService::uniqueSlug`), e o resolvedor também os recusa.
- **`WebsiteResolver`**:
  - `resolvePath($segmento, $empresa)` só devolve o site quando o segmento existe, está ativo e não é reservado, a empresa pertence a esse segmento e está ativa, e o site está publicado com versão. Os slugs são comparados exatamente, para que a collation do MySQL não aceite outra caixa.
  - `resolve($host)` atende os endereços antigos e só resolve se o endereço oficial estiver no ar.
  - `isLive($tenant)` e `liveSites()` alimentam o painel e o sitemap.
- **Rotas** (`routes/web.php`): ficam registradas por último, então qualquer rota da plataforma vence. Os slugs passam pela restrição `[a-z0-9]+(?:-[a-z0-9]+)*`.
- **Atendimento:** a empresa vem só dos dois slugs da URL. O cookie `vs_assistant` fica restrito a `/{segmento}/{empresa}/atendimento`, então empresas no mesmo domínio não compartilham o cookie. O limite `assistant-public` passou a ser por IP + `{segmento}/{empresa}`. O fluxo (serviços reais, identificação, disponibilidade, agendamento com proteção de concorrência, encaminhamento humano) continua o mesmo, sem alteração.
- **Endereços antigos:** o subdomínio da plataforma e o domínio próprio verificado respondem `301` em `/` e `/atendimento` para o endereço oficial, só quando o site oficial está no ar. A correspondência é inequívoca, porque cada hostname pertence a uma única empresa. O `robots.txt` deles permite seguir o redirecionamento. Qualquer outro caminho ou método nesses hosts responde 404 (ou 405 para POST).
- **Domínios:** não se criam mais subdomínios da plataforma. O domínio próprio continua opcional e separado, e depois de verificado apenas redireciona.
- **SEO:**
  - O sitemap central (`/sitemap.xml`) lista a landing e os sites no ar e indexáveis (início e `/servicos`, quando há serviços).
  - O `robots.txt` central aponta o sitemap e bloqueia `/meu-site/`. O `public/robots.txt` estático foi removido para a rota responder.
  - Cada página tem canônica própria, título, descrição, Open Graph (`og:url`, `og:locale` e `og:site_name`) e JSON-LD.
  - Rascunho, empresa suspensa e segmento inativo respondem 404. Site não indexável recebe `noindex` e fica fora do sitemap.
- **Página de serviços:** `/{segmento}/{empresa}/servicos` reutiliza o template do site com navegação (Início · Serviços · Atendimento). Sem serviços publicados, responde 404, para não indexar página vazia.
- **Painel Meu Site:** mostra o endereço oficial com botão **Copiar link** (com anúncio para leitor de tela), situação (No ar / Não publicado / Publicado, fora do ar), aviso em linguagem simples quando o site não aparece (não publicado, sem segmento, segmento indisponível ou empresa suspensa) e prévia em nova aba. A aba Domínios virou "Domínio próprio (opcional)". O painel Atendimento IA mostra o endereço oficial do atendimento.

## 3. URLs públicas resultantes

| Recurso | URL |
| --- | --- |
| Página da empresa | `https://vetorsmart.com.br/{segmento}/{empresa}` |
| Serviços | `https://vetorsmart.com.br/{segmento}/{empresa}/servicos` |
| Atendimento | `https://vetorsmart.com.br/{segmento}/{empresa}/atendimento` |
| Endpoints do atendimento | `…/atendimento/conversa` (POST) e `…/atendimento/mensagens` (GET/POST) |
| Sitemap / robots | `https://vetorsmart.com.br/sitemap.xml` e `/robots.txt` |

Os slugs de segmento vêm do cadastro. No banco local o segmento de refrigeração é `climatizacao`, então o exemplo do correio fica `/climatizacao/clima-total`. Em produção vale o slug que estiver cadastrado lá.

## 4. Arquivos modificados

- Novos: `app/Services/Websites/PublicSiteAddress.php` e `app/Services/Websites/ReservedPaths.php`.
- Backend:
  - Site e rotas: `routes/web.php`, `app/Http/Controllers/Site/{SiteController,AssistantController}.php`, `app/Http/Controllers/LandingController.php` e `app/Http/Middleware/SiteHostGuard.php`.
  - Serviços de site: `app/Services/Websites/{WebsiteResolver,WebsitePresenter,WebsiteDomainService}.php` e `app/Services/SegmentService.php`.
  - Painel e domínios: `app/Http/Controllers/Tenant/{AssistantController,WebsiteDomainController}.php` e `app/Http/Requests/Tenant/StoreWebsiteDomainRequest.php`.
  - Limite e configuração: `app/Providers/AppServiceProvider.php` (limite) e `config/platform.php` (`public_url` e `reserved_paths`).
- Views: `resources/views/website/site.blade.php` e `resources/views/website/atendimento.blade.php`.
- Frontend: `resources/js/pages/tenant/website/index.tsx` e `resources/js/pages/tenant/assistant/index.tsx`.
- Removido: `public/robots.txt`.
- Testes: `tests/Feature/Websites/WebsiteTest.php` (reescrito), `tests/Feature/Assistant/{AssistantTest,VisitorTokenSecurityTest,BookingFailureRuleTest}.php`, `tests/Support/VisitorSite.php` e `phpunit.xml` (`PLATFORM_CENTRAL_HOST` e `PLATFORM_PUBLIC_URL` fixos nos testes).

Nenhuma migration e nenhuma dependência nova.

## 5. Testes automatizados

| Verificação | Resultado |
| --- | --- |
| Suíte completa | 442 testes: 433 aprovados, 9 pulados (concorrência), 2604 asserções |
| Sites + atendimento (`tests/Feature/Websites`, `tests/Feature/Assistant`) | 97 aprovados |
| Concorrência MySQL (`VETORSMART_CONCURRENCY=1`) | 9 aprovados, 39 asserções |
| PHPStan | 0 erros |
| Pint | aprovado |
| TypeScript (`tsc --noEmit`) | aprovado |
| Build (`npm run build`) | aprovado |
| `git diff --check` | sem erros (exceto `correio.md`, que não é deste trabalho) |

O `vp check` aponta formatação em 87 arquivos do frontend. Isso já existia antes desta etapa e não faz parte dos gates usados nas releases. Não reformatei o projeto.

Cobertura nova ou ajustada:

- endereço oficial sem domínio;
- rascunho, publicação e retirada;
- empresa suspensa, segmento inativo e empresa sem segmento;
- segmento e empresa inexistentes, inclusive com outra caixa;
- empresa acessada por outro segmento;
- dois tenants no mesmo segmento e em segmentos diferentes;
- unicidade do slug;
- rotas da plataforma vencendo os sites, segmentos que nunca recebem nome reservado e segmento reservado nunca resolvido;
- página de serviços com e sem serviços;
- prévia restrita (visitante e membro de outra empresa);
- escape de HTML;
- canônica, Open Graph e JSON-LD;
- sitemap central excluindo rascunho, não indexável e suspensa; robots central;
- redirecionamento `301` do subdomínio antigo e do domínio próprio;
- subdomínio antigo sem site no ar;
- bloqueio de novos subdomínios;
- tenant enviado pelo navegador sendo ignorado;
- cookie restrito ao caminho;
- limite por site;
- atendimento de empresa inexistente.

## 6. Evidências no navegador

Ambiente isolado: banco `vetorsmart_websites09`, migrado do zero, com cinco empresas fictícias (`.invalid`):

- Clima, Frio e Elétrica, publicadas, em dois segmentos;
- Rascunho, não publicada;
- Suspensa.

Servidor `php artisan serve` na porta 8090 e Chromium headless do cache do Playwright, controlado por CDP, sem dependência no projeto. Viewports de 1440 × 900 e 390 × 844.

**88 de 88 verificações aprovadas:**

- Início, serviços e atendimento respondem 200 nos dois tamanhos, sem rolagem horizontal, com canônica correta, JSON-LD válido, indexáveis, com navegação e margem lateral ≥ 16 px.
- Rascunho, suspensa, segmento errado, segmento inexistente e empresa inexistente: 404 sem dados da empresa. `/clientes/novo` continua sendo rota da plataforma.
- Atendimento:
  - a saudação lista o serviço real;
  - o cookie `vs_assistant` fica com `path=/climatizacao/clima-homolog-09/atendimento` e `httpOnly`;
  - a mensagem recebe resposta (aviso de privacidade antes de pedir nome e telefone);
  - o pedido de atendente muda o estado para "Aguardando atendente".
- Painel (desktop e mobile): endereço oficial, **Copiar link**, situação **No ar**, prévia em nova aba e prévia com `noindex`. O site não publicado mostra o aviso. Prévia sem login redireciona para o login.
- Nenhum erro de JavaScript no console.

A primeira rodada encontrou o título encostado na borda no mobile, corrigido na seção 1. A segunda rodada passou a verificar essa margem.

## 7. Segurança multi-tenant

- A empresa vem só de `{segmento}/{empresa}` na URL. Sessão, cookies e payload nunca escolhem o tenant. Há teste que envia `tenant_id` e `tenant` no corpo e confirma que são ignorados.
- A empresa precisa pertencer ao segmento da URL: `/eletricistas/clima-total` responde 404.
- Todas as consultas do atendimento e do site continuam usando `forTenant` com o tenant resolvido. A conversa é procurada pelo token **e** pelo tenant.
- O cookie do visitante é restrito ao caminho de cada empresa, então empresas no mesmo domínio não trocam cookie.
- O limite de requisições é por IP e por empresa, então o limite de uma empresa não afeta outra.
- Respostas 404 genéricas não revelam o nome da empresa para rascunho, suspensa ou endereço inexistente.
- Os endereços antigos não expõem nenhuma rota administrativa (404 para tudo que não seja `/`, `/atendimento` e `/robots.txt`).
- A prévia exige login e mostra só o rascunho da empresa ativa do usuário.

## 8. Pendências e riscos

1. **Configuração de produção:** definir `PLATFORM_PUBLIC_URL=https://vetorsmart.com.br` (ou `APP_URL` com esse valor) e `PLATFORM_CENTRAL_HOST=vetorsmart.com.br`. Sem isso, os links absolutos usam o `APP_URL` atual.
2. **robots.txt:** o arquivo estático foi removido. O Nginx de produção precisa encaminhar `/robots.txt` para o Laravel quando o arquivo não existir (o `try_files … /index.php?$query_string` padrão já faz isso). Não alterei o Nginx.
3. **Slugs de segmento em produção:** a URL usa o slug cadastrado (no local, `climatizacao`). Se o negócio quiser `refrigeracao`, é uma decisão de cadastro. O slug de segmento é estável depois de criado, e mudá-lo quebraria endereços já divulgados.
4. **Empresas sem segmento** não têm endereço público. O painel avisa e pede que o suporte defina o segmento. No banco local há duas nessa situação (dados de fábrica).
5. **Conversas abertas em subdomínio antigo:** o cookie antigo ficava no host do subdomínio, então um visitante com conversa em andamento começa uma nova no endereço oficial. A conversa antiga continua no painel da equipe e expira pela retenção.
6. **Subdomínios existentes:** continuam aceitos (`TrustedHosts`) apenas para redirecionar. Não criei remoção automática, para não apagar dados.
7. O `vp check` aponta formatação antiga em 87 arquivos, fora do escopo.
8. O banco `vetorsmart_websites09` só tem dados fictícios e pode ser descartado com `DROP DATABASE vetorsmart_websites09`. Não foi apagado, porque a instrução proíbe remover dados.

## 9. Estado final do Git

- `main` em `ce8404b`, sem commits novos e sem push.
- 28 arquivos rastreados alterados, um removido (`public/robots.txt`) e dois novos (`PublicSiteAddress.php` e `ReservedPaths.php`), mais este relatório.
- Fora do conjunto funcional: `correio.md`, `executed.md` e `pnpm-lock.yaml` (alteração antiga, preservada).

Sugestão de commits:

1. `feat(sites): publica sites em /{segmento}/{empresa} no domínio principal`: código, views, frontend, `public/robots.txt`, `phpunit.xml` e testes.
2. `docs(sites): registra relatório da WEBSITES-09`: este arquivo.

## 10. Parecer de liberação

Funcionalmente pronta e homologada localmente. O deploy depende de (1) revisão e autorização dos commits e do push, (2) configuração de `PLATFORM_PUBLIC_URL` e `PLATFORM_CENTRAL_HOST` em produção e (3) confirmar que o Nginx encaminha `/robots.txt` ao Laravel. Não há migration nesta etapa.
