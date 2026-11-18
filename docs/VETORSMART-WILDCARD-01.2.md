# VETORSMART-WILDCARD-01.2 — DNS, SSL e implantação controlada

**Data:** 17/11/2026. **Ambiente:** VPS `srv1990862` (IP público `2.25.235.159`).
**Parecer:** **INTERROMPIDO NA ETAPA 1.** Nada foi alterado em produção.

O correio manda interromper a ativação se DNS, API ou configuração apresentarem problemas. Foram encontrados bloqueios nas três frentes.

## 1. Estado do DNS (consultado em `a.sec.dns.br`, `1.1.1.1` e `8.8.8.8`)

| Item | Esperado | Encontrado |
|---|---|---|
| Nameservers | Hostinger | `a.sec.dns.br`, `c.sec.dns.br` (**Registro.br**) |
| `A @` | `2.25.235.159` | **ausente** (NOERROR, só SOA `hostmaster.registro.br`, serial `2026321000`) |
| `A *` | `2.25.235.159` | **ausente** |
| `www` | domínio central | **ausente** |
| `AAAA` | nenhum incorreto | nenhum |
| CAA | sem restrição a Let's Encrypt | nenhum (não bloqueia) |
| DNSSEC | coerente | `DNSKEY` presente (assinatura do Registro.br) |

Consequência: `https://vetorsmart.com.br` **não resolve** no momento (`curl: Could not resolve host`). O certificado atual foi emitido quando havia registro A, que depois deixou de existir na zona. Isso precisa ser corrigido mesmo sem wildcard.

## 2. Artefatos que o correio considera preparados

Busca em `/opt/infra-abrasil`, `/etc`, `/root` e no histórico Git de `gateway/vetorsmart` (`main`):

| Artefato | Situação |
|---|---|
| `hostinger_dns_hook.py` | **não existe** |
| `vetorsmart-wildcard.conf` | **não existe** |
| Comando `sites:assign-subdomains` | **não existe** |
| Flag `PLATFORM_SUBDOMAIN_SITES` | **não existe** no código nem no compose |
| Migration aditiva de subdomínios | **não identificada** |
| Token da API Hostinger no `.env` | **ausente** |
| Certbot no host | ausente; roda em container via perfil `certbot` (webroot HTTP-01) |

Não há registro de uma etapa WILDCARD-01 ou 01.1 anterior em `docs/`.

## 3. Nginx e certificados atuais (somente leitura)

- `nginx -t` no container `infra-abrasil-nginx-1`: **ok**.
- Vhosts do VetorSmart em `nginx/conf.d/apps.conf`: `vetorsmart.com.br` e `www.vetorsmart.com.br` (porta 80 e 443).
- Certificado `live/vetorsmart.com.br`: SAN `vetorsmart.com.br`, `www.vetorsmart.com.br`; válido até **09/02/2027**.
- Renovação: cron diário às 03:00 com `scripts/renew-certs.sh` (HTTP-01 via webroot). **Não atende wildcard**, que exige DNS-01.
- Containers `vetorsmart`, `vetorsmart-worker`, `vetorsmart-scheduler` e `nginx`: healthy.

## 4. O que não foi executado (Etapa 2 inteira)

Teste DNS-01 em staging, emissão do wildcard, `certbot renew --dry-run`, novo vhost, migration, `sites:assign-subdomains`, homologação `[HOMOLOG]`. `PLATFORM_SUBDOMAIN_SITES` não foi criada nem habilitada. Nenhum certificado, vhost, registro DNS ou dado de tenant foi alterado.

## 5. Rollback

Não se aplica: não houve alteração. Nenhum backup foi necessário.

## 6. Para destravar

1. **Decidir o provedor DNS.** Opção A: migrar os nameservers para a Hostinger (exige o hook e o token da API). Opção B: manter o Registro.br e usar DNS-01 manual ou outro provedor com API (o Registro.br não tem API de DNS).
2. **Restaurar `A @` e `www`** para `2.25.235.159` e criar `A *` com o mesmo IP.
3. **Implementar o que falta** (hoje inexistente): hook DNS-01, vhost wildcard, flag, migration e comando `sites:assign-subdomains`.
4. Ajustar a renovação para DNS-01 antes de ativar o wildcard.
5. Depois disso, reexecutar este correio a partir da Etapa 1.
