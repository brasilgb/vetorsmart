# VETORSMART-WEBSITES-09.1 — Revisão final e preparação de implantação

Data: 16/11/2026. Base: `ce8404b`. Branch: `main`. Ambiente: desenvolvimento local. Referência: WEBSITES-09. Nenhum commit, push ou deploy.

## Parecer

**Pronta para os commits; o deploy depende da configuração de produção.** O domínio próprio verificado volta a exibir o site, e cada empresa tem uma única URL canônica. O endereço padrão `/{segmento}/{empresa}` continua sempre disponível. Gates automatizados verdes e homologação no navegador com 63 de 63 verificações aprovadas, inclusive um agendamento completo gravado no banco. Não há migration.

## 1. Correções realizadas

### Revisão inicial

`main` em `ce8404b`, com as alterações da WEBSITES-09 preservadas no diretório de trabalho e o relatório `docs/VETORSMART-WEBSITES-09.md` presente. O único ponto em conflito com a 09.1 era o domínio próprio, que a 09 redirecionava para o endereço padrão. Não houve outra regressão: suíte, concorrência e tipos estavam verdes antes da mudança.

### Alterações desta etapa

| Arquivo | Mudança |
| --- | --- |
| `app/Services/Websites/PublicSiteAddress.php` | `customHost()` escolhe o domínio próprio verificado da empresa (o marcado como principal, senão o mais antigo). `canonicalBase()` e `canonical()` definem a URL canônica única: o domínio próprio quando existe, senão o endereço padrão. |
| `app/Services/Websites/WebsiteResolver.php` | `resolve($host)` devolve também o domínio, para distinguir domínio próprio de subdomínio antigo. |
| `routes/web.php` | Grupo `Route::domain('{siteHost}')` registrado **primeiro** e restrito a hosts diferentes do central (`/`, `/servicos`, `/sitemap.xml`, `/robots.txt` e o atendimento). Removidas a rota `/atendimento` antiga e o desvio pela landing. |
| `app/Http/Controllers/Site/SiteController.php` | O domínio próprio serve início e serviços. Um domínio próprio secundário redireciona (`301`) para o principal da empresa, nunca para o domínio da plataforma. O subdomínio antigo redireciona para o endereço padrão. Sitemap e robots por host. Canônica e `og:url` iguais em todos os acessos. A navegação permanece no endereço que o visitante usa. |
| `app/Http/Controllers/Site/AssistantController.php` | O atendimento também responde no domínio próprio (`/atendimento`), com o cookie restrito ao caminho `/atendimento` daquele host. No subdomínio antigo, a página redireciona e os endpoints não iniciam conversa. |
| `app/Http/Controllers/LandingController.php` | Voltou a ser só a landing do host central. |
| `app/Http/Middleware/SiteHostGuard.php` | No host da empresa, só caminhos e métodos do site público são aceitos; o resto responde 404. |
| `app/Providers/AppServiceProvider.php` | Limite `assistant-public` por IP + `{segmento}/{empresa}` ou por host da empresa. |
| `app/Services/Websites/WebsitePresenter.php` e `resources/js/pages/tenant/website/index.tsx` | O painel mostra o **Endereço padrão** e o **Domínio próprio**, cada um com **Copiar link**, e informa qual é o endereço principal para os buscadores. O texto da aba Domínios foi atualizado. |
| `app/Http/Controllers/Tenant/AssistantController.php` | O painel Atendimento IA mostra o endereço canônico do atendimento. |
| `app/Services/Websites/WebsiteDomainService.php` | Comentário atualizado. Continua sem criar subdomínios. |
| `docs/VETORSMART-WEBSITES-09.md` | Aviso de que o comportamento do domínio próprio foi substituído por esta etapa. |

## 2. Funcionamento dos domínios próprios

| Situação | Resultado |
| --- | --- |
| Empresa sem domínio próprio | `https://vetorsmart.com.br/{segmento}/{empresa}` exibe o site. A canônica é o próprio endereço, e ele entra no sitemap central. |
| Domínio próprio verificado (`https://www.empresa.com.br`) | Exibe o site, os serviços e o atendimento. **Não redireciona** para o domínio da plataforma. |
| Endereço padrão de uma empresa com domínio próprio | Continua exibindo o site, com canônica e `og:url` apontando para o domínio próprio. Fica fora do sitemap central, porque entra no sitemap do domínio próprio. |
| Segundo domínio próprio da mesma empresa | `301` para o domínio próprio principal, mantendo o caminho (`/`, `/servicos` e `/atendimento`). |
| Domínio próprio pendente | Não serve nada. Fica fora dos hosts confiáveis até ser verificado. |
| Subdomínio antigo da plataforma | `301` para o endereço padrão (`/`, `/servicos` e `/atendimento`). O `robots.txt` dele permite seguir o redirecionamento. Os endpoints do atendimento não iniciam conversa ali. |
| Empresa suspensa, site não publicado ou segmento inativo | 404 genérico em todos os formatos, sem dados da empresa. |
| Qualquer caminho administrativo em host de empresa | 404 (`/clientes`, `/meu-site`, `/login`, `/dashboard`, `/rootadmin`, endereços padrão de outras empresas). |

A escolha da canônica é determinística: o domínio próprio verificado marcado como principal ou, se nenhum estiver marcado, o verificado mais antigo. Na prática há no máximo uma URL indexável por página da empresa.

## 3. Configurações necessárias em produção

Nada foi alterado na VPS. Valores necessários:

| Variável | Valor | Motivo |
| --- | --- | --- |
| `PLATFORM_CENTRAL_HOST` | `vetorsmart.com.br` (exatamente o host atendido) | Separa a plataforma dos hosts de empresas, inclusive na regex da rota por domínio. Com `www.vetorsmart.com.br` também em uso, ele precisa redirecionar no Nginx para o host central, porque senão é tratado como host de empresa e responde 404. |
| `PLATFORM_PUBLIC_URL` | `https://vetorsmart.com.br` | Base das URLs absolutas, canônicas e do sitemap. Sem ela, vale o `APP_URL`. |
| `PLATFORM_PUBLIC_SCHEME` | `https` (padrão) | Esquema das URLs dos domínios próprios. |
| `PLATFORM_TENANT_BASE_DOMAIN` | domínio dos subdomínios antigos | Mantém os subdomínios existentes como hosts confiáveis, para que redirecionem. |
| `PLATFORM_TRUSTED_PROXIES` | IP do Nginx | Sem isso, o Laravel não reconhece HTTPS atrás do proxy (já registrado na RELEASE-06.1). |
| `APP_URL` | `https://vetorsmart.com.br` | Coerência com as URLs geradas. |

Cache de configuração e de rotas:

- `php artisan config:cache` e `php artisan route:cache` funcionam com a rota por domínio. Verifiquei que `/`, `/servicos` e `/{segmento}/{empresa}` no host central e `/` e `/servicos` em um domínio próprio resolvem para as rotas certas com os dois caches ativos. Os caches locais foram limpos depois.
- **Ordem obrigatória no deploy:** o host central entra na regex da rota quando o cache de rotas é gerado. Por isso, `config:cache` e `route:cache` precisam rodar **depois** de o `.env` de produção estar correto. Se `PLATFORM_CENTRAL_HOST` mudar, refazer os dois caches.

`/robots.txt` e `/sitemap.xml`:

- São rotas do Laravel, e o `public/robots.txt` estático foi removido. O vhost do Nginx precisa encaminhar caminhos inexistentes ao `index.php` (`try_files $uri $uri/ /index.php?$query_string`).
- O Nginx compartilhado não está neste repositório (a imagem Docker só tem PHP-FPM), então essa compatibilidade não pôde ser conferida aqui.

Domínios próprios no Nginx e TLS (fora desta etapa, sem alterar DNS nem certificados):

- Para um domínio próprio funcionar, o DNS da empresa precisa apontar para o servidor, e o Nginx precisa aceitar o host e ter um certificado válido para ele.
- Sem isso, o domínio simplesmente não chega à aplicação. O endereço padrão continua funcionando.
- O mesmo vale para os subdomínios antigos: só redirecionam se o vhost e o certificado (curinga) estiverem configurados.

## 4. Atendimento público

Revalidado nos testes automatizados e no navegador:

- Acesso por `/{segmento}/{empresa}/atendimento` e pelo domínio próprio em `/atendimento`.
- Somente os serviços ativos da empresa resolvida aparecem.
- O cookie `vs_assistant` é `httpOnly` e restrito a `/{segmento}/{empresa}/atendimento` ou a `/atendimento` do domínio próprio. O token de uma empresa não abre conversa de outra.
- O pedido de atendente leva a conversa a "Aguardando atendente".
- Fluxo completo no navegador: serviço → nome e telefone → "amanhã" → horário 1 → "sim". No banco isolado ficaram gravados a solicitação (`scheduled`, origem `ai`) e o agendamento (`scheduled`), ambos da empresa correta.
- A segunda rodada recebeu o horário seguinte (09:00), porque o das 08:00 já estava ocupado. Isso confirma o uso da disponibilidade real e a proteção contra duplicidade.
- A confirmação só aparece depois da gravação; os testes `BookingFailureRuleTest` e de concorrência MySQL continuam aprovados.

## 5. Resultado dos testes

| Verificação | Resultado |
| --- | --- |
| Suíte completa | 450 testes: 441 aprovados, 9 pulados (concorrência), 2743 asserções |
| Sites + atendimento | 105 aprovados |
| Concorrência MySQL (`VETORSMART_CONCURRENCY=1`) | 9 aprovados |
| PHPStan | 0 erros |
| Pint | aprovado |
| TypeScript | aprovado |
| Build | aprovado |
| `git diff --check` | sem erros (exceto `correio.md`) |
| Varredura de credenciais no diff | nenhuma ocorrência |

Testes novos desta etapa:

- domínio próprio pendente;
- domínio próprio verificado servindo o site, com canônica única nos dois formatos;
- sitemap e robots do domínio próprio, e sitemap central sem duplicidade;
- domínio secundário → principal;
- domínio próprio de empresa fora do ar;
- domínio próprio sem rotas administrativas e sem cruzamento entre empresas;
- painel com os dois endereços e a canônica;
- `/servicos` central continua sendo a tela do painel;
- subdomínio antigo redirecionando `/servicos` e sem iniciar conversa;
- atendimento no domínio próprio, com cookie, isolamento e endereço no painel;
- endereço padrão do atendimento no painel.

Navegador: banco isolado `vetorsmart_websites09` (dados fictícios `.invalid`), domínio próprio `www.clima-homolog-09.localhost` (com um secundário) e subdomínio antigo `frio-homolog-09.vetorsmart.test`. Chromium headless via CDP, em 1440 × 900 e 390 × 844. **63 de 63 verificações aprovadas.** Cobrem:

- os dois formatos com canônica única, JSON-LD válido, sem rolagem horizontal e com margem;
- navegação que permanece no endereço usado;
- o redirecionamento do subdomínio antigo;
- 404 sem dados (rascunho, suspensa, segmento errado, rotas administrativas no domínio próprio);
- agendamento completo;
- atendimento no domínio próprio com encaminhamento humano;
- painel com os dois endereços;
- console sem erros.

## 6. Estado final do Git

- `main` em `ce8404b`, sem commits e sem push.
- 28 arquivos rastreados alterados, um removido (`public/robots.txt`) e quatro novos (`PublicSiteAddress.php`, `ReservedPaths.php` e os relatórios 09 e 09.1).
- Fora dos commits: `correio.md`, `executed.md` e `pnpm-lock.yaml` (alteração antiga). Nenhum artefato local (`public/build` é ignorado; os scripts e capturas da homologação ficaram fora do repositório).

Commits preparados (não criados, porque a instrução não autoriza criá-los):

1. `feat(sites): publica sites por segmento e empresa com domínio próprio opcional`. Rotas públicas e resolução de tenants:
   ```
   routes/web.php app/Http/Controllers/LandingController.php app/Http/Controllers/Site/SiteController.php
   app/Http/Controllers/Site/AssistantController.php app/Http/Controllers/Tenant/AssistantController.php
   app/Http/Controllers/Tenant/WebsiteDomainController.php app/Http/Requests/Tenant/StoreWebsiteDomainRequest.php
   app/Http/Middleware/SiteHostGuard.php app/Providers/AppServiceProvider.php app/Services/SegmentService.php
   app/Services/Websites/PublicSiteAddress.php app/Services/Websites/ReservedPaths.php
   app/Services/Websites/WebsiteResolver.php app/Services/Websites/WebsiteDomainService.php
   config/platform.php resources/views/website/atendimento.blade.php public/robots.txt
   ```
2. `feat(sites): ajusta SEO dos sites e o painel Meu Site`. SEO e interface:
   ```
   resources/views/website/site.blade.php app/Services/Websites/WebsitePresenter.php
   resources/js/pages/tenant/website/index.tsx resources/js/pages/tenant/assistant/index.tsx
   ```
3. `test(sites): cobre sites públicos e registra WEBSITES-09 e 09.1`. Testes e documentação:
   ```
   tests/Feature/Websites/WebsiteTest.php tests/Feature/Assistant/AssistantTest.php
   tests/Feature/Assistant/BookingFailureRuleTest.php tests/Feature/Assistant/VisitorTokenSecurityTest.php
   tests/Support/VisitorSite.php phpunit.xml docs/VETORSMART-WEBSITES-09.md docs/VETORSMART-WEBSITES-09.1.md
   ```

Os testes só passam com os três commits juntos. O código de cada commit é coerente sozinho, mas a suíte depende do terceiro.

## 7. Riscos remanescentes

1. **Nginx e TLS não verificados:** encaminhamento de `/robots.txt` e `/sitemap.xml`, vhosts dos domínios próprios e dos subdomínios antigos e certificados. Domínio sem vhost ou sem certificado não chega à aplicação. O endereço padrão não depende disso.
2. **`www.vetorsmart.com.br`:** se também servir a plataforma, precisa redirecionar para o host central no Nginx.
3. **Ordem dos caches no deploy:** `config:cache` e `route:cache` depois do `.env` correto. A regex do host central fica gravada no cache de rotas.
4. **Slug do segmento:** a URL usa o slug cadastrado em produção. Mudá-lo depois quebraria endereços já divulgados.
5. **Empresas sem segmento** não têm endereço padrão. Com domínio próprio verificado também não exibem o site, porque a regra de disponibilidade exige segmento ativo. O painel explica.
6. **Conversas antigas em subdomínio:** o visitante recomeça no endereço padrão. A conversa anterior continua no painel da equipe.
7. **Desempenho:** `customHost()` faz uma consulta por renderização. Isso é irrelevante no volume atual, mas pode ganhar cache se o tráfego crescer.
8. `vp check` aponta formatação antiga em 87 arquivos do frontend, fora do escopo.
9. O banco `vetorsmart_websites09` só tem dados fictícios e pode ser descartado (`DROP DATABASE vetorsmart_websites09`). Não foi apagado.

## 8. Parecer de liberação

**Tecnicamente pronta para os três commits propostos.** A implantação depende de:

1. autorização explícita para criar os commits e fazer o push;
2. `.env` de produção com `PLATFORM_CENTRAL_HOST`, `PLATFORM_PUBLIC_URL`, `PLATFORM_TRUSTED_PROXIES` e `APP_URL` corretos, seguido de `config:cache` e `route:cache`;
3. confirmação de que o Nginx encaminha `/robots.txt` e `/sitemap.xml` ao Laravel.

Domínios próprios e subdomínios antigos dependem do vhost e do certificado de cada um, sem bloquear a liberação do endereço padrão.
