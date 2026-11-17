# VETORSMART-WILDCARD-01.1 — Homologação, Git e preparação SSL

Data: 17/11/2026. Ambiente: desenvolvimento WSL, `gateway/vetorsmart/`. Base: `466a363`.

## Parecer

**APROVADO COM RESTRIÇÕES** para o código e a homologação local. **Wildcard não habilitado em produção**, como o correio exige: a automação do certificado não foi comprovada e o DNS não está pronto.

Restrições:

1. **WebKit não testado.** O navegador exige bibliotecas do sistema (GTK 4, GStreamer, ICU 76) que precisam de `sudo`, e o `sudo` pede senha neste ambiente. Chromium e Firefox: 124 de 124 verificações aprovadas.
2. **Hook DNS-01 não executado.** Foi escrito com base na especificação OpenAPI oficial da Hostinger, mas não há Python nem token neste ambiente, e o download de um Python avulso foi bloqueado pela política de permissões. A primeira execução real deve ser um `--dry-run` contra o ambiente de testes do Let's Encrypt.
3. **`nginx -t` não executado.** Não há Nginx nem Docker neste WSL. A configuração ficou preparada em `docs/infra/vetorsmart-wildcard.conf`.
4. **DNS sem delegação para a Hostinger e sem registros A** (seção 6).

## 1. Diagnóstico inicial

| Item | Situação |
| --- | --- |
| Git da aplicação | `main` = `origin/main` = `466a363`; alterações da WILDCARD-01 só na árvore de trabalho; `pnpm-lock.yaml` já modificado antes, sem relação com este trabalho (fora dos commits). |
| Migrations | Uma pendente, aditiva: `2026_11_17_134456_add_retired_at_to_tenant_domains_table`. Aplicada só nos bancos de teste. |
| Testes | 475 aprovados e 9 pulados (concorrência, que só roda com `VETORSMART_CONCURRENCY=1`). |
| Infraestrutura (`infra-abrasil`) | HEAD destacado e conflito em `gateway/vetorleads`. **Não foi tocada.** Não contém nada de wildcard: a `origin/main` só tem vhost e certificado de `vetorsmart.com.br` e `www`. |
| Outros projetos | Alterações locais em `abrasilsistemas`, `vetoros`, `vetorpet` e `vetorsales` (no repositório da infraestrutura). Nenhuma foi tocada. |

## 2. Arquivos modificados

Os da WILDCARD-01 (ver `docs/VETORSMART-WILDCARD-01.md`), mais:

| Arquivo | Alteração |
| --- | --- |
| `app/Services/TenantRegistrationService.php` | **Correção:** slug único em cadastros simultâneos (savepoint e nova tentativa). |
| `tests/Feature/Tenancy/RegistrationTest.php` | Regressão da corrida do slug. |
| `tests/Feature/Websites/SubdomainSiteTest.php` | Regressão da corrida do subdomínio (22 testes no arquivo). |
| `resources/js/pages/tenant/website/index.tsx` | A lista de endereços anteriores não quebra mais o nome no meio da palavra. |
| `docs/infra/hostinger_dns_hook.py` | **Novo:** hook DNS-01 (Hostinger). |
| `docs/infra/vetorsmart-wildcard.conf` | **Novo:** vhost `*.vetorsmart.com.br`. |

## 3. Commits

| Commit | Conteúdo |
| --- | --- |
| `5d2ca56` | `feat(sites)`: subdomínios públicos por empresa (WILDCARD-01). |
| `593ed5b` | `fix(cadastro)`: slug único em cadastros simultâneos. |
| `c2d5b21` | `docs(infra)`: hook DNS-01 e vhost curinga, preparados. |
| commit seguinte | `docs(release)` e `chore(controle)`: relatórios, `correio.md` e `executed.md`. |

## 4. Testes

| Gate | Resultado |
| --- | --- |
| Suíte Laravel com `VETORSMART_CONCURRENCY=1` | **486 de 486 aprovados, 0 pulados** (3126 asserções), MySQL `vetorsmart_test` |
| Concorrência real (8 processos simultâneos, MySQL `vetorsmart_wildcard011`) | Antes da correção: **7 de 8 cadastros falharam** (`tenants_slug_unique`). Depois: **8 de 8**, 8 slugs e 8 subdomínios distintos, 0 duplicados |
| PHPStan | 0 erros |
| Pint | ok |
| TypeScript (`tsc --noEmit`) | 0 erros |
| Build (`npm run build`) | ok |
| Navegador (Playwright: Chromium 145, Firefox) | **124 de 124 aprovadas**; WebKit indisponível (restrição 1) |
| Acessibilidade (axe-core, WCAG 2.0/2.1 A e AA) | 0 violações em 32 combinações de página, navegador, tamanho de tela e tema |
| Verificação de credenciais | Nenhum token, senha ou chave nos arquivos commitados; o hook lê o token de arquivo `600` e não o imprime |

Banco de homologação: `vetorsmart_wildcard011` (isolado, dados fictícios com e-mails `.invalid`), servidor PHP local e um proxy local que entrega `*.vetorsmart.test` à aplicação. `PLATFORM_SUBDOMAIN_SITES=true` só nesse ambiente.

## 5. Homologação funcional e visual

| Verificação | Resultado |
| --- | --- |
| Cadastro automático: "Clima Total" → `climatotal`; segundo "Clima Total" → `climatotal2` | aprovado |
| Site, serviços e canônica no subdomínio | aprovado |
| Isolamento: cada subdomínio mostra só a própria empresa | aprovado |
| `/dashboard`, `/login`, `/meu-site`, `/rootadmin`, `/register` e `/settings/profile` no subdomínio | 404 (aprovado) |
| Subdomínio inexistente e sub-subdomínio | 404 sem dados da empresa (aprovado) |
| Sitemap e robots do subdomínio; sitemap central sem duplicar | aprovado |
| Cookies host-only (sem `Domain`); POST sem token CSRF → 419; nenhum `Access-Control-Allow-Origin` | aprovado |
| Painel: nome reservado (`admin`), de outra empresa (`climatotal2`) e inválido (`xn--abc`) recusados com mensagem | aprovado |
| Alteração (`clima-campinas`); nome anterior → 301 com caminho e query; retomada do nome anterior | aprovado |
| Domínio próprio verificado vira a canônica; o subdomínio continua servindo | aprovado |
| Atendimento IA + agendamento de ponta a ponta no subdomínio (serviço → nome/telefone → data → horário → confirmação) | aprovado (0 → 1 agendamento) |
| Site, serviços, atendimento e painel × Chromium/Firefox × desktop 1440×900 / mobile 390×844 × claro/escuro: carregamento, sem rolagem horizontal, axe | 96 de 96 aprovadas |

Observações:

- **O site público só tem tema claro.** Com o navegador em modo escuro ele aparece igual, legível e sem violações de contraste. O painel segue o tema do sistema.
- Em ambiente `local`, o `TrustHosts` do Laravel não age; um host fora da lista recebe 404 do `SiteHostGuard`. Em produção, recebe 400 antes do roteamento.
- As capturas ficaram no scratchpad da sessão (temporário), não no repositório.

## 6. Situação do DNS

Consulta pelo resolvedor do sistema em 17/11/2026, por volta de 14:30 UTC:

| Consulta | Resposta |
| --- | --- |
| NS `vetorsmart.com.br` | `a.sec.dns.br`, `c.sec.dns.br` (DNS do Registro.br) |
| SOA | `a.sec.dns.br`, `hostmaster.registro.br`, serial `2026321000` |
| A `vetorsmart.com.br` | sem registro |
| A `www.vetorsmart.com.br` | inexistente |
| A `*.vetorsmart.com.br` (nome de teste) | inexistente |
| TXT `_acme-challenge` | inexistente |
| CAA | sem registro (qualquer CA pode emitir) |

A delegação para a Hostinger ainda não aparece, e não há registro A nem mesmo para o domínio principal. Pode ser cache do resolvedor local, mas **é preciso conferir se `vetorsmart.com.br` está resolvendo em produção agora.** O hook só funciona depois que a zona estiver delegada à Hostinger.

## 7. SSL wildcard e renovação automática

### Avaliação

| Opção | Parecer |
| --- | --- |
| API oficial de DNS da Hostinger (`developers.hostinger.com`, token Bearer criado no hPanel → API, 90 req/min) | **Adotada.** Endpoints conferidos na especificação OpenAPI oficial. |
| Plugin `certbot-dns-hostinger` (BackBenchDevs) | **Recusado.** Comunitário, com 3 estrelas, sem releases nem mantenedor identificado. Não passa na avaliação de segurança exigida pelo correio. |
| Certbot `--manual` sem hook | Recusado: exige ação manual a cada 60 a 90 dias. |

### Solução: hook próprio e auditável (`docs/infra/hostinger_dns_hook.py`)

- Cerca de 100 linhas, só com a biblioteca padrão do Python, rodando no contêiner `certbot/certbot` já existente. Não instala nada.
- `auth`: `PUT /api/dns/v1/zones/vetorsmart.com.br` com `overwrite:false`, acrescentando o TXT `_acme-challenge` (o domínio principal e o curinga usam o mesmo nome, com dois valores ao mesmo tempo). Em seguida confere pela própria API que o valor está na zona e aguarda a propagação (90 s por padrão).
- `cleanup`: remove só o valor validado e preserva qualquer outro (`PUT overwrite:true` com os restantes, ou `DELETE` com `filters` quando não sobra nada).
- O token fica em `volumes/certbot/conf/hostinger/token` (visto no contêiner como `/etc/letsencrypt/hostinger/token`). O hook recusa o arquivo se ele não tiver permissão `600`. O token nunca vai para a linha de comando, os logs ou os relatórios. Recomendação: um token dedicado, com expiração, criado por um usuário com acesso só a DNS.
- Trata o limite da API (429 com `Retry-After`).

### Emissão e renovação (Fase B, na VPS, com autorização)

```sh
cd /opt/infra-abrasil
install -d -m 700 volumes/certbot/conf/hostinger
install -m 700 gateway/vetorsmart/docs/infra/hostinger_dns_hook.py volumes/certbot/conf/hostinger/
# token: criar o arquivo com umask 077 e colar o valor sem ecoar no terminal
( umask 077; read -rs T && printf '%s' "$T" > volumes/certbot/conf/hostinger/token )

# 1) teste no ambiente de testes do Let's Encrypt (não emite certificado real)
docker compose run --rm certbot certonly --dry-run --manual --preferred-challenges dns \
  --manual-auth-hook "/etc/letsencrypt/hostinger/hostinger_dns_hook.py auth" \
  --manual-cleanup-hook "/etc/letsencrypt/hostinger/hostinger_dns_hook.py cleanup" \
  --cert-name vetorsmart-wildcard -d vetorsmart.com.br -d '*.vetorsmart.com.br' \
  --agree-tos -m "$CERTBOT_EMAIL" --no-eff-email

# 2) emissão real: mesmo comando sem --dry-run
# 3) prova da renovação automática
docker compose run --rm certbot renew --cert-name vetorsmart-wildcard --dry-run
```

O nome `vetorsmart-wildcard` mantém o certificado atual de `vetorsmart.com.br` intacto. Renovação automática: um cron no host (o stack não tem agendador do Certbot):

```cron
17 3 * * * cd /opt/infra-abrasil && docker compose run --rm certbot renew --quiet && docker compose exec -T nginx nginx -s reload
```

## 8. Nginx

`docs/infra/vetorsmart-wildcard.conf`: dois blocos `*.vetorsmart.com.br` (HTTP para ACME e redirecionamento; HTTPS com o certificado `vetorsmart-wildcard`), usando o mesmo contêiner `vetorsmart:9000`. Nenhum contêiner por tenant, e nenhum vhost de outro sistema muda. Nomes exatos têm prioridade sobre o curinga no Nginx, então os blocos atuais de `vetorsmart.com.br` e `www` continuam valendo. **Não aplicado.**

## 9. Riscos e pendências

| Risco ou pendência | Tratamento |
| --- | --- |
| DNS: sem delegação à Hostinger e sem registro A | Concluir a delegação e criar `A @` e `A *` para o IP da VPS. Conferir o domínio principal em produção. |
| Hook não executado | `--dry-run` no ambiente de testes antes da emissão real. |
| WebKit não homologado | Rodar `sudo npx playwright install-deps webkit` e repetir o roteiro, ou validar no Safari/iOS na Fase B. |
| `nginx -t` não executado | Executar na VPS com o certificado já emitido. |
| Token da API com permissões amplas | Usar um usuário só de DNS e token com expiração; renovar o token antes de expirar. |
| Site público sem tema escuro | Fora do escopo desta etapa; legível nos dois modos. |
| `SESSION_DOMAIN` de produção | Conferir que está vazio antes de ativar. |

## 10. Implantação e rollback

Implantação (Fase B, com aprovação explícita, nesta ordem):

1. Deploy do código. `PLATFORM_SUBDOMAIN_SITES=false`. `php artisan migrate --force` (aditiva).
2. `php artisan sites:assign-subdomains` (prévia), revisão e `--execute`.
3. DNS delegado à Hostinger; `A @` e `A *` respondendo.
4. Certificado: `--dry-run`, emissão real, `renew --dry-run`, cron.
5. Vhost: incluir o bloco, `nginx -t`, reload; verificar VetorOS, VetorSales, VetorLeads e ABrasilSistemas.
6. Tenant `[HOMOLOG]` em HTTPS, desktop e mobile.
7. Aprovação: `PLATFORM_SUBDOMAIN_SITES=true`, recriar o contêiner (ou `config:cache`).

Rollback:

- Flag: `PLATFORM_SUBDOMAIN_SITES=false` e refazer o cache de config. Os subdomínios voltam a redirecionar para o endereço padrão.
- Nginx: remover os dois blocos, `nginx -t` e reload. O certificado atual nunca é substituído.
- Certificado: `certbot delete --cert-name vetorsmart-wildcard`; o cron passa a não ter o que renovar.
- Código: `git revert` dos commits; a coluna `retired_at` pode ficar (é nula e inerte).
