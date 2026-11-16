# VETORSMART-RELEASE-10 — Implantação e homologação na VPS

Data: 16/11/2026, 18:59 a 19:10 UTC. Ambiente: VPS de produção `srv1990862`, `/opt/infra-abrasil/gateway/vetorsmart`. Origem: WEBSITES-09.1, branch `main`.

## Parecer

**APROVADO COM RESTRIÇÕES.**

A release está em produção em `fd7fa5f`, saudável, sem migrations e com o banco inalterado. Todos os 12 itens da homologação foram exercitados e passaram, mas com três limites que impedem a aprovação plena:

1. **O RootAdmin não tem MFA.** O acesso é controlado só por `is_root_admin` (gate `platform-admin`). As colunas de dois fatores foram removidas pela migration `2026_11_16_120000_drop_two_factor_columns_from_users_table`. O item "RootAdmin com MFA" foi validado apenas quanto ao acesso e ao bloqueio de não administradores.
2. **Não houve teste em navegador.** A VPS não tem navegador instalado. Layout desktop e mobile, modo claro e escuro e console não foram conferidos aqui (foram na WEBSITES-09.1, em desenvolvimento).
3. **Os fluxos com dados não foram feitos por HTTP externo.** A produção só tem uma empresa real (Clima Total, site em rascunho) e nenhum tenant de homologação. Para não gravar dados fictícios, os fluxos rodaram dentro de um container descartável da imagem nova, contra o banco de produção, numa transação revertida ao final (seção 6).

## 1. HEAD anterior e novo

| | Commit |
| --- | --- |
| Anterior | `cc1b226` (código de aplicação igual ao de `ce8404b`) |
| Novo | `fd7fa5f` |

Fast-forward de 3 commits, os três da WEBSITES-09.1:

- `3bba29f` feat(sites): publica sites por segmento e empresa com domínio próprio opcional
- `7e9a76b` feat(sites): ajusta SEO dos sites e o painel Meu Site
- `fd7fa5f` test(sites): cobre sites públicos e registra WEBSITES-09 e 09.1

`main` local igual a `origin/main`. Nenhum push.

## 2. Inspeção pré-deploy

- Hostname `srv1990862`, branch `main`, 0 commits à frente e 3 atrás de `origin/main`.
- Única alteração local: `correio.md` (a própria instrução). Não bloqueia.
- 24 de 24 containers saudáveis. PHP 8.4.27, Laravel 13.42.0, MySQL 8.4.
- Migrations: 36 executadas, 0 pendentes. A release não altera `database/`, `composer.*`, `package.json` nem o `Dockerfile`.
- Nginx: `nginx -t` aprovado; a configuração em execução é idêntica aos arquivos do repositório de infraestrutura.
- TLS: certificado Let's Encrypt para `vetorsmart.com.br` e `www.vetorsmart.com.br`, válido até 09/02/2027.

## 3. Backup e recuperação

- Dump: `/opt/infra-abrasil/backups/vetorsmart_pre_release10_fd7fa5f_20261116_190035.sql.gz`
- SHA-256: `f5f85198c2ed96b157f9b101d451d33a908325534f68f064c34ab195cde2207e` (arquivo `.sha256` ao lado).
- Só o banco `vetorsmart`, com `--single-transaction`, rotinas e triggers. 33 tabelas.
- **Restauração testada** num MySQL 8.4 descartável, sem rede e em memória: 33 tabelas e contagens iguais às da produção (1 empresa, 2 usuários, 8 registros de auditoria, 36 migrations, 10 segmentos, 1 site). Container removido.
- Configuração preservada em `/opt/infra-abrasil/backups/vetorsmart_pre_release10_fd7fa5f_20261116_190035.conf/` (diretório `700`): `.env` (`600`), `docker-compose.yml`, `apps.conf`, `laravel.inc`, `nginx.conf` e `PREVIOUS_HEAD`.
- Imagens anteriores marcadas como `rollback-pre-fd7fa5f` nos três repositórios de imagem.

### Rollback

1. `cd /opt/infra-abrasil/gateway/vetorsmart && git reset --hard cc1b226` (guardar antes `correio.md` e `executed.md`). Isso também restaura `public/robots.txt`, que o Nginx lê direto do repositório.
2. Para `vetorsmart`, `vetorsmart-worker` e `vetorsmart-scheduler`: `docker tag infra-abrasil-<serviço>:rollback-pre-fd7fa5f infra-abrasil-<serviço>:latest`.
3. `cd /opt/infra-abrasil && docker compose up -d --no-deps vetorsmart vetorsmart-worker vetorsmart-scheduler`.
4. Conferir saúde e `https://vetorsmart.com.br/login`.

Não há migration, então o rollback de código não exige restaurar o banco. Nginx e `.env` não foram alterados, então não há rollback de configuração. O dump só deve ser restaurado em caso de corrupção de dados, restrito ao banco `vetorsmart`, depois de avaliar o que foi gravado desde 19:00 UTC.

## 4. Alterações efetivamente aplicadas

```
git fetch origin
git merge --ff-only fd7fa5f
mysqldump --single-transaction --no-tablespaces --routines --triggers vetorsmart | gzip
docker run --rm --network none --tmpfs /var/lib/mysql mysql:8.4          (teste de restauração, removido)
docker tag infra-abrasil-{vetorsmart,vetorsmart-worker,vetorsmart-scheduler}:latest ...:rollback-pre-fd7fa5f
docker compose build vetorsmart vetorsmart-worker vetorsmart-scheduler
docker compose run --rm --no-deps vetorsmart ...                          (validação da imagem e homologação, removidos)
docker compose up -d --no-deps vetorsmart vetorsmart-worker vetorsmart-scheduler
docker exec -u www-data infra-abrasil-vetorsmart-1 php artisan config:cache | route:cache | view:cache
```

- Serviços recriados às 19:04 UTC e saudáveis em 16 segundos: `vetorsmart`, `vetorsmart-worker`, `vetorsmart-scheduler`.
- Os outros 21 containers mantiveram o mesmo ID. MySQL, Redis e Nginx não foram reiniciados nem recarregados.
- Build pelo procedimento do projeto (`composer install --no-dev`, `yarn build`), sem erros. Assets novos respondem 200.
- `public/robots.txt` deixou de existir; `/robots.txt` agora é rota do Laravel.
- Permissões: `storage`, `bootstrap/cache` e o volume de uploads pertencem a `www-data`.

Não executados: `migrate`, `migrate:fresh`, `db:wipe`, `docker compose down`, alteração de `.env`, de `docker-compose.yml`, do Nginx, de DNS ou de certificados, commit, push.

### Caches do Laravel

Aplicados no container web depois de validar a configuração: `Config`, `Routes` e `Views` constam como `CACHED`. A rota por domínio funciona com os dois caches.

**Limite:** os caches ficam no sistema de arquivos do container e somem quando ele é recriado. O `Dockerfile` e o `docker-entrypoint.sh` não os geram. Sem eles a aplicação funciona igual, só sem o ganho de desempenho. Worker e scheduler não receberam cache.

## 5. Migrations

Nenhuma. `migrate:status` na imagem nova: 0 pendentes.

## 6. Configuração da plataforma e Nginx

Valores efetivos na imagem nova (`config:show`):

| Configuração | Valor | Situação |
| --- | --- | --- |
| `PLATFORM_CENTRAL_HOST` | `vetorsmart.com.br` | conforme |
| `APP_URL` | `https://vetorsmart.com.br` | conforme |
| `platform.public_url` | `https://vetorsmart.com.br` | conforme, **por herança do `APP_URL`**: `PLATFORM_PUBLIC_URL` não está declarada no `docker-compose.yml` |
| `PLATFORM_PUBLIC_SCHEME` | `https` | conforme |
| `PLATFORM_TENANT_BASE_DOMAIN` | `vetorsmart.com.br` | conforme |
| `PLATFORM_TRUSTED_PROXIES` | `172.18.0.0/16` | rede Docker interna; o Nginx está em `172.18.0.20`. Cookies saem com `secure`, o que confirma o HTTPS reconhecido atrás do proxy |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | conforme |

Nginx, conferido por HTTP real:

| Requisito | Resultado |
| --- | --- |
| `/robots.txt` e `/sitemap.xml` chegam ao Laravel | 200 `text/plain` e 200 `application/xml` (o sitemap respondia 404 antes do deploy) |
| `www.vetorsmart.com.br` → `vetorsmart.com.br` | 301 em HTTP e HTTPS, preservando caminho e query |
| HTTPS mantido | HTTP → 301 para HTTPS; certificado válido |
| Rotas administrativas preservadas | `/dashboard`, `/rootadmin`, `/meu-site`, `/servicos` sem sessão → 302 para `/login` |
| Host desconhecido não chega a tenant | SNI desconhecido: handshake TLS recusado. SNI correto com `Host` forjado: 421. HTTP: conexão fechada (444) |
| Domínios próprios dependem de verificação e certificado | Não existe vhost nem certificado para domínio próprio ou subdomínio; nenhum chega à aplicação. Na aplicação, domínio pendente é recusado |

Nenhum domínio ou certificado foi criado.

## 7. Homologação

### 7.1 HTTP real, de fora

| Rota | Resultado |
| --- | --- |
| `/`, `/login`, `/register`, `/up` | 200 |
| `/robots.txt` | 200, com `Disallow: /meu-site/` e `Sitemap: https://vetorsmart.com.br/sitemap.xml` |
| `/sitemap.xml` | 200, XML válido, só a home (nenhum site publicado) |
| `/assistencia-tecnica/clima-total` e subpáginas (empresa real, site em rascunho) | 404 |
| Empresa inexistente, segmento errado | 404 |
| `/atendimento` no host central | 404 (a rota antiga foi removida, como previsto) |
| Cookies | sessão `secure; httponly; samesite=lax`; XSRF `secure; samesite=lax`; sem atributo `Domain` |
| User-Agent mobile | 200, com `meta viewport` |
| Outros SaaS (6 domínios) | no ar |

### 7.2 Fluxos funcionais: 92 de 92 verificações aprovadas

Método: container descartável da imagem nova, requisições completas pelo kernel HTTP do Laravel (middleware, CSRF real, cookies cifrados), banco de produção dentro de uma transação **revertida ao final**. Duas empresas temporárias (`Homolog R10 Alfa` e `Beta`, e-mails `.invalid`) existiram só dentro da transação. Contagens do banco antes e depois: idênticas (1 empresa, 2 usuários, 0 agendamentos, 0 solicitações, 0 conversas, 0 domínios, 8 auditorias, 0 jobs; site da Clima Total em `draft`).

| # | Item | Resultado |
| --- | --- | --- |
| 1 | Landing pública | 200 |
| 2 | Cadastro e login | Cadastro cria empresa e leva ao dashboard. Logout encerra a sessão. Senha errada não autentica; senha correta abre o dashboard. POST sem token CSRF: 419 |
| 3 | Dashboard do tenant | 200 |
| 4 | RootAdmin | Seis telas com 200 para administrador da plataforma; 403 para donos de empresa. **Sem MFA** (ver parecer) |
| 5 | Site em `/segmento/empresa` | 200 com o conteúdo publicado; página de serviços lista o serviço ativo; rascunho não aparece antes de publicar |
| 6 | Inexistente ou suspensa → 404 sem vazamento | 404 para inexistente, segmento errado, rascunho e suspensa (endereço padrão, atendimento e domínio próprio). Corpo do 404 idêntico entre inexistente e rascunho, sem nome da empresa |
| 7 | SEO, canonical, sitemap, robots | `title`, `description`, canonical e `og:url` iguais ao endereço padrão; JSON-LD válido (`ProfessionalService`); sem `noindex`. Sitemap central inclui o site publicado e exclui rascunho e suspensa. Com domínio próprio verificado, a canonical passa para ele e o endereço sai do sitemap central |
| 8 | Painel Meu Site | Mostra o endereço padrão, salva rascunho e serviços, prévia, publica e passa a `visible` |
| 9 | Atendimento IA e encaminhamento humano | Agendamento completo pelo chat (serviço → nome e telefone → amanhã → horário 1 → sim), com solicitação e agendamento gravados na empresa certa. "atendente" leva a `awaiting_human`; a equipe assume, responde, e a resposta chega ao visitante |
| 10 | Isolamento multi-tenant | A Beta recebe 404 na solicitação da Alfa, não vê os dados em solicitações nem na agenda e não responde a conversa da Alfa. Nada foi gravado na empresa real |
| 11 | Cookies e sessões entre hosts | Sessão do painel sem `Domain` (não vaza para outros hosts) e inválida no host da empresa (404). `vs_assistant` é `httpOnly`, `secure` e restrito a `/{segmento}/{empresa}/atendimento`, ou a `/atendimento` no domínio próprio. Token de visitante de uma empresa não abre conversa em outra |
| 12 | Logs | Sem erros (seção 8) |

Hosts, ainda na aplicação: domínio próprio verificado serve o site; `/login`, `/dashboard`, `/rootadmin`, `/meu-site`, `/register` e o endereço de outra empresa respondem 404 nele; domínio pendente e host desconhecido são recusados; subdomínio antigo redireciona 301 para o endereço padrão.

Empresa real, só leitura: dashboard, clientes, serviços, profissionais, agenda, solicitações, atendimento, Meu Site, equipe e configurações com 200. O Meu Site dela mostra `https://vetorsmart.com.br/assistencia-tecnica/clima-total`, ainda não visível porque o site está em rascunho.

Roteiro e saída completos: `homolog-release10.php` e `homolog-release10.out`, no diretório `.conf` do backup.

## 8. Monitoramento

- Logs dos três containers desde o deploy: 0 erros, exceções ou avisos. `storage/logs` vazio.
- Nginx: nenhum 500, 502 ou 503; nenhum `[error]`.
- Worker (`queue:work`) e scheduler (`schedule:work`) ativos; 0 jobs pendentes, 0 falhos.
- MySQL: disponível, 0 consultas lentas.
- Recursos: containers do VetorSmart com cerca de 40 MiB cada e CPU abaixo de 1%. Host com carga 0,82, 12 GB de memória disponível e disco em 34%.
- 24 de 24 containers saudáveis; os demais SaaS respondem normalmente.

## 9. Pendências e riscos

1. **RootAdmin sem MFA.** Decidir se a exigência vale e, se valer, implementar em etapa própria.
2. **Homologação visual em navegador** pendente em produção (desktop e mobile).
3. **Nenhum site publicado em produção.** O primeiro teste por HTTP externo de `/segmento/empresa` acontece quando a Clima Total publicar o site, ou com um tenant de homologação autorizado.
4. **Caches do Laravel não sobrevivem à recriação do container.** Para torná-los permanentes, gerar no `docker-entrypoint.sh` (depois das variáveis de ambiente, como exige a WEBSITES-09.1). Se `PLATFORM_CENTRAL_HOST` mudar, refazer `config:cache` e `route:cache`.
5. **`PLATFORM_PUBLIC_URL` não está declarada** no `docker-compose.yml`; vale o `APP_URL`. Correto hoje, mas implícito. Não alterei por ser arquivo da infraestrutura compartilhada.
6. **Domínios próprios e subdomínios antigos não funcionam de fora:** falta vhost e certificado no Nginx para cada um. O painel permite cadastrar e verificar o domínio, mas ele não chegará à aplicação até essa etapa de infraestrutura. O endereço padrão não depende disso.
7. **`/up` responde 200 em host de empresa.** É a rota de saúde do framework, fora do grupo `web`, então o `SiteHostGuard` não a cobre. Não expõe dados. Só seria alcançável com vhost de domínio próprio.
8. **Slug do segmento na URL:** a Clima Total está em `assistencia-tecnica`, não em `climatizacao`. Mudar o segmento ou o slug depois de divulgar o endereço quebra o link.
9. O usuário da empresa real e o administrador da plataforma estão com e-mail não verificado. Não impede o acesso.
10. Disco: o build acrescentou cerca de 4 GB (59 → 63 GB). Há 15 imagens `rollback-pre-*` acumuladas de cinco deploys. Não removi.
11. Pendências antigas mantidas: conexão MySQL sem `timezone` explícito, `package-lock.json` junto com `yarn.lock`, cabeçalho `x-powered-by` exposto.

## 10. Situação final

- `https://vetorsmart.com.br` no ar com `fd7fa5f`.
- Banco inalterado em schema e dados.
- Git: `main` em `fd7fa5f`, igual a `origin/main`. Sem commit: `correio.md`, `executed.md` e este relatório.
- Rollback disponível pelas imagens `rollback-pre-fd7fa5f` e pelo dump desta data.
