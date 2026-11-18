# VETORSMART-WILDCARD-01.3 — Sites públicos por subdomínio

**Data:** 18/11/2026, por volta de 11:25 UTC. **Ambiente:** VPS de produção `srv1990862` (IP `2.25.235.159`).
**Parecer:** **REPROVADO: interrompido na Etapa 1, com impedimentos críticos.** Nada foi alterado em produção.

## 1. Comparação com a última execução

- O `executed.md` local registrava a RELEASE-10.3. O correio atual é a WILDCARD-01.3, portanto diferente.
- Havia um relatório da WILDCARD-01.2 (`docs/VETORSMART-WILDCARD-01.2.md`, 17/11, 14:57 UTC, sem commit), que concluiu que a implementação não existia. **Essa conclusão estava errada:** o checkout da VPS não tinha feito `fetch`. A implementação foi enviada à `origin/main` às 14:41 UTC do mesmo dia, a partir do ambiente de desenvolvimento:

| Commit | Conteúdo |
|---|---|
| `5d2ca56` | `feat(sites)`: subdomínios por empresa, `TenantSubdomainService`, comando `sites:assign-subdomains`, migration aditiva `retired_at` |
| `593ed5b` | `fix(cadastro)`: slug único em cadastros simultâneos |
| `c2d5b21` | `docs/infra/hostinger_dns_hook.py` e `docs/infra/vetorsmart-wildcard.conf` |
| `1ec0a5c` | relatórios WILDCARD-01 e 01.1, `correio.md` e `executed.md` |

## 2. Git

| Repositório | Estado |
|---|---|
| Aplicação (`gateway/vetorsmart`) | `main` em `466a363`, **4 commits atrás** da `origin/main` (`1ec0a5c`). `correio.md` modificado (WILDCARD-01.3) e `docs/VETORSMART-WILDCARD-01.2.md` sem commit. O `pull` vai conflitar com o `correio.md` e o `executed.md` locais |
| Infraestrutura (`/opt/infra-abrasil`) | `main` = `origin/main` (`bbc0462`). Alterações locais de outros SaaS, não tocadas. Nenhum vhost ou certificado curinga |
| Container em produção | Código sem a implementação: `sites:assign-subdomains` inexistente, 0 migrations pendentes |

## 3. DNS (consultas diretas aos autoritativos, a `1.1.1.1` e a `8.8.8.8`)

| Item | Esperado | Encontrado |
|---|---|---|
| Nameservers (delegação no `.br`) | autoridade com API | `cosmos.dns-parking.com`, `nova.dns-parking.com` (**Hostinger**; SOA `dns.hostinger.com`, serial `2026111702`) |
| `A vetorsmart.com.br` | `2.25.235.159` | **`2.57.91.91`**: CDN da Hostinger (`Server: hcdn`), não é a VPS |
| `www.vetorsmart.com.br` | domínio principal | CNAME para `vetorsmart.com.br`, portanto também `2.57.91.91` |
| `teste.vetorsmart.com.br` / `A *` | `2.25.235.159` | **inexistente** |
| AAAA | nenhum incorreto | nenhum |
| CAA | sem restrição | nenhum |
| TXT `_acme-challenge` | — | inexistente |

Mudou desde a WILDCARD-01.2: a delegação saiu do Registro.br e foi para a Hostinger, que passou a responder com um registro A padrão (CDN ou estacionamento).

**Consequência em produção:** `https://vetorsmart.com.br` está **fora do ar para o público** (timeout em 8 s pela rede externa). Pela VPS, com `--resolve` para `127.0.0.1`, o site responde 200. VetorOS, VetorSales, VetorLeads e ABrasilSistemas apontam para `2.25.235.159` e responderam 200.

## 4. Proxy, containers e certificados (somente leitura)

- `nginx -t` em `infra-abrasil-nginx-1`: ok. Os containers `nginx`, `vetorsmart-9`, `vetorsmart-worker-1` e `vetorsmart-scheduler-1` estão healthy.
- Vhosts do VetorSmart: só `vetorsmart.com.br` e `www` (`nginx/conf.d/apps.conf`). Não há bloco `*.vetorsmart.com.br`.
- Certificado `live/vetorsmart.com.br`: SAN `vetorsmart.com.br` e `www`, válido até 09/02/2027. Não existe `vetorsmart-wildcard`.
- Renovação: cron às 03:00, `scripts/renew-certs.sh` (HTTP-01 via webroot). Com o A atual, **a renovação HTTP-01 do certificado existente também vai falhar**, porque o desafio chega à Hostinger e não à VPS.
- `SESSION_DOMAIN=null` (cookies host-only, como exige a Etapa 4). `PLATFORM_SUBDOMAIN_SITES` não está definida (falso por padrão).

## 5. Método SSL para o provedor autoritativo

Como a zona agora está na Hostinger, o método previsto na WILDCARD-01.1 é aplicável: DNS-01 com o hook próprio (`hostinger_dns_hook.py`, API oficial `/api/dns/v1/zones`), executado no container `certbot`. Faltam:

- **Token da API Hostinger:** ausente (`volumes/certbot/conf/hostinger/` não existe e não há variável no `.env`).
- **Prova de autoridade da API sobre a zona:** depende do token. A delegação para os NS da Hostinger indica que ela deve existir.

## 6. Impedimentos críticos

1. **O DNS não aponta para a VPS.** `A @` e `www` estão em `2.57.91.91`. Sem a correção, nem o domínio principal funciona, e um certificado obtido pelo webroot não renova.
2. **Não há registro curinga** (`A *`), por isso `teste.vetorsmart.com.br` não resolve.
3. **Não há token da API Hostinger** para o DNS-01. A criação do token cabe ao proprietário.
4. **O código em produção não tem a implementação.** O deploy depende do `pull`, que conflita com o `correio.md` e o `executed.md` locais, e da migration.

Não executado (Etapas 2 a 6): emissão do certificado, vhost, migration, `sites:assign-subdomains`, homologação e ativação da flag. Nenhum DNS, certificado, vhost, container, dado de tenant ou commit foi alterado.

## 7. Para destravar (ações do proprietário)

1. **Urgente, mesmo sem wildcard:** no hPanel, em DNS de `vetorsmart.com.br`, trocar `A @` de `2.57.91.91` para `2.25.235.159` e criar `A *` com `2.25.235.159`. O `www` pode ficar como CNAME para o domínio principal. Remover registros padrão da Hostinger que conflitem (por exemplo, ALIAS ou CDN).
2. Criar um token da API Hostinger dedicado, de preferência com acesso só a DNS e com validade. Gravá-lo na VPS sem ecoar no terminal:
   `install -d -m 700 /opt/infra-abrasil/volumes/certbot/conf/hostinger && ( umask 077; read -rs T && printf '%s' "$T" > /opt/infra-abrasil/volumes/certbot/conf/hostinger/token )`
3. Autorizar a sincronização do Git da aplicação (`pull` da `origin/main`, preservando o `correio.md` da 01.3) e reexecutar este correio a partir da Etapa 1.

## 8. Rollback

Não se aplica: não houve alteração.

## 9. Arquivos modificados

- `docs/VETORSMART-WILDCARD-01.3.md` (este relatório) e `executed.md`. Sem commit, para não divergir da `origin/main`, que está à frente.
