# VETORSMART-WILDCARD-01.5 — Certificado curinga, Nginx e implantação

Data: 18/11/2026, das 12:40 às 13:15 UTC. Local: VPS de produção `srv1990862` (`2.25.235.159`), Hostinger KVM 4.

## Parecer

**APROVADO COM RESTRIÇÕES.** O certificado curinga foi emitido e instalado, a renovação foi testada, a versão `1ec0a5c` foi implantada com a migration e os outros SaaS continuam operando. **`PLATFORM_SUBDOMAIN_SITES` continua desligada.** Nenhuma empresa tem site publicado, então ainda não dá para homologar o site público, os serviços, o chat, o orçamento e o agendamento pelo subdomínio.

## 1. Diagnóstico inicial: ok

| Verificação | Resultado |
|---|---|
| `vetorsmart.com.br` | A `2.25.235.159` (8.8.8.8, 1.1.1.1 e autoritativos) |
| `www.vetorsmart.com.br` | CNAME `vetorsmart.com.br` |
| `teste.vetorsmart.com.br` | A `2.25.235.159` (curinga) |
| IP público da VPS | `2.25.235.159` |
| Autoridade DNS | `cosmos` e `nova.dns-parking.com` (Hostinger) |
| AAAA | nenhum |
| Token | presente em `volumes/certbot/conf/hostinger/token`, permissão 600 (conteúdo não lido nem exibido) |
| API Hostinger | `GET /api/dns/v1/zones/vetorsmart.com.br` respondeu 200 (zona: `@ A`, `* A`, `www CNAME`) |
| Certbot | container `certbot/certbot` (perfil `certbot`), configuração em `volumes/certbot/conf` → `/etc/letsencrypt` |

## 2. Preparação SSL

- Backup antes de qualquer alteração: `backups/vetorsmart_wildcard01.5_20261118_124249/` (`certbot-conf.tgz`, sem o token, e `apps.conf.orig`).
- O hook foi copiado para `volumes/certbot/conf/hostinger/hostinger_dns_hook.py` (700). Assim ele fica disponível como `/etc/letsencrypt/hostinger/hostinger_dns_hook.py` em toda execução do certbot, sem alterar o compose.
- Dry-run em staging (`certonly --dry-run`): ok. TXT criados e removidos, e a zona voltou aos 3 registros originais.

## 3. Certificado curinga: emitido

- `--cert-name vetorsmart-wildcard`, domínios `vetorsmart.com.br` e `*.vetorsmart.com.br`, emissor Let's Encrypt (YE2), **vence em 16/02/2027**.
- A primeira tentativa falhou: a validação secundária do Let's Encrypt ainda via em cache (TTL 300 s) os TXT do dry-run. Depois de esperar a expiração dos caches públicos, a segunda tentativa (espera de propagação de 150 s) teve sucesso. Houve uma única falha de validação, longe do limite.
- O certificado `vetorsmart.com.br` (apex + www, HTTP-01) **não foi substituído**.
- Renovação gravada em `renewal/vetorsmart-wildcard.conf`: `authenticator = manual`, `pref_challs = dns-01`, com os hooks `auth` e `cleanup`.
- O `scripts/renew-certs.sh` já estava sem `--webroot` (alteração da 01.4). Cada certificado usa o autenticador do próprio `renewal/*.conf`.
- **`certbot renew --dry-run --no-random-sleep-on-renew`** (o mesmo comando do cron, em staging, com a espera padrão de 90 s do hook): **todas as 11 renovações simuladas passaram**, inclusive o curinga.
- O cron do host (`0 3 * * *`) cuida da renovação automática.

## 4. Nginx

- Os dois blocos de `docs/infra/vetorsmart-wildcard.conf` foram acrescentados ao final de `nginx/conf.d/apps.conf`: porta 80 com redirecionamento para HTTPS e porta 443 com o certificado curinga. Os blocos exatos de `vetorsmart.com.br` e `www` têm prioridade e continuam iguais.
- `nginx -t` ok, seguido de `nginx -s reload` (sem recriar o container).

| Verificação após o reload | Resultado |
|---|---|
| `https://vetorsmart.com.br` | 200 |
| `https://www.vetorsmart.com.br` | 301 → `https://vetorsmart.com.br/` |
| `http://vetorsmart.com.br` | 301 → HTTPS |
| `http://teste.vetorsmart.com.br` | 301 → `https://teste.vetorsmart.com.br/` |
| `https://teste.vetorsmart.com.br` | TLS ok (SAN `*.vetorsmart.com.br`, `vetorsmart.com.br`); 404 genérico (nome inexistente) |
| Host forjado | 421 (recusado) |
| VetorOS, VetorSales, VetorLeads, ABrasilSistemas, Desgarrados | 200 |
| VetorPet | 302 → VetorSales (comportamento anterior, não alterado) |
| Containers | todos healthy |

## 5. Sincronização Laravel

- `correio.md`, `executed.md` e os relatórios locais não versionados foram preservados. O checkout já estava em `origin/main` (`1ec0a5c`), sem commits pendentes.
- Testes (MySQL 8.4 descartável em loopback, removido no final): **486 testes: 477 aprovados, 9 pulados (concorrência), 0 falhas, 3087 asserções.**
  - Atenção: rodar a suíte no host exige `APP_URL=http://localhost`. O `.env` do checkout aponta para `https://vetorsmart.com.br`, e o `phpunit.xml` não sobrescreve `APP_URL`. Sem isso, cerca de 370 testes recebem 404 (host diferente de `PLATFORM_CENTRAL_HOST=localhost`). É problema de ambiente, não de código.
  - Os 9 testes de concorrência não foram repetidos (o filtro `--group` não os encontrou). Na 01.4, no mesmo commit, eles passaram (9/9).
- Implantação:
  - Imagens de rollback: `infra-abrasil-vetorsmart{,-worker,-scheduler}:rollback-pre-1ec0a5c`.
  - Backup do banco: `backups/vetorsmart_pre_migrate_20261118_124939.sql` (+ `.sha256`, permissão 600).
  - `docker compose build` de app, worker e scheduler, seguido de `up -d --no-deps`. Os três ficaram healthy.
  - `migrate --force`: `2026_11_17_134456_add_retired_at_to_tenant_domains_table` aplicada. `migrate:status` sem pendências.
  - Assets do build novo servidos (200).
- Atribuição de subdomínios: as 3 empresas existentes eram anteriores ao recurso e não tinham subdomínio. Executado `sites:assign-subdomains --execute`:
  - #1 `climatotal.vetorsmart.com.br`
  - #10 `homologhomologacaovetorsmart.vetorsmart.com.br`
  - #11 `homologempresabisolamento.vetorsmart.com.br`

## 6. Homologação

| Item | Resultado |
|---|---|
| Domínio principal com HTTPS | ok |
| Subdomínio com HTTPS (TLS curinga) | ok |
| Site público individual, serviços, chat, orçamento e agendamento no subdomínio | **não homologado**: nenhuma empresa tem site publicado (empresas 1 e 10: `unpublished`; 11: sem site). O resolvedor responde 404 genérico, como previsto. |
| Painel fora do domínio principal (`/login`, `/admin`, `/meu-site` no subdomínio) | 404 (SiteHostGuard ok) |
| Isolamento (caminho de outra empresa no subdomínio, host forjado) | 404 / 421 |
| Suíte automatizada (inclui isolamento multi-tenant) | ok |
| Responsividade e acessibilidade no subdomínio | não verificado (depende de site publicado) |
| Demais sistemas | ok |

**`PLATFORM_SUBDOMAIN_SITES` não foi habilitada**, porque a homologação funcional do site no subdomínio não pôde ser feita.

## Pendências

1. Publicar o site da empresa de homologação (#10), em Meu Site, e homologar no subdomínio: site, serviços, chat, orçamento, agendamento, responsividade e acessibilidade. Com a flag desligada, o subdomínio redireciona (301) para o endereço padrão.
2. Depois da aprovação, definir `PLATFORM_SUBDOMAIN_SITES=true` no ambiente do VetorSmart e recriar app, worker e scheduler.
3. Fazer o commit na infraestrutura de `nginx/conf.d/apps.conf` (blocos curinga) e de `scripts/renew-certs.sh`, que continuam sem commit.
4. Opcional: definir `APP_URL` no `phpunit.xml`, para que a suíte rode no host sem variável extra.

## Rollback

- **Nginx:** remover os dois blocos `*.vetorsmart.com.br` do final de `nginx/conf.d/apps.conf` (ou restaurar `backups/vetorsmart_wildcard01.5_20261118_124249/apps.conf.orig`), executar `nginx -t` e `nginx -s reload`.
- **Certificado:** `certbot delete --cert-name vetorsmart-wildcard` (o certificado `vetorsmart.com.br` não depende dele).
- **Aplicação:** retag `rollback-pre-1ec0a5c` → `latest` nas três imagens e `up -d --no-deps`. A migration só adiciona a coluna `retired_at`. Se for preciso reverter o banco, usar `migrate:rollback --step=1` ou restaurar `backups/vetorsmart_pre_migrate_20261118_124939.sql`.
- **Subdomínios:** apagar as linhas `kind = subdomain` das empresas 1, 10 e 11 em `tenant_domains` (com a flag desligada, elas não têm efeito além do redirecionamento).

Nenhuma credencial, token ou segredo aparece neste relatório.
