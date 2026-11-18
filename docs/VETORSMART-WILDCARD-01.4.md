# VETORSMART-WILDCARD-01.4 — Validação DNS e SSL

Data: 18/11/2026, das 12:01 às 12:15 UTC. Local: VPS de produção `srv1990862` (`2.25.235.159`), Hostinger KVM 4.

## Parecer

**APROVADO COM RESTRIÇÕES: DNS e Git ok; interrompido antes da emissão do certificado (Etapa 3).** O token da API Hostinger ainda não está na VPS, então não dá para validar o desafio DNS-01. Como o correio manda, nenhum certificado foi emitido, e a homologação (Etapa 4) e a ativação de `PLATFORM_SUBDOMAIN_SITES` ficaram pendentes.

## 1. DNS: ok

Consultados os autoritativos (`cosmos` e `nova.dns-parking.com`, SOA `2026111802`) e os resolvedores 8.8.8.8, 1.1.1.1 e 9.9.9.9:

| Nome | Resultado (em todos os servidores) |
|---|---|
| `vetorsmart.com.br` | A `2.25.235.159` |
| `www.vetorsmart.com.br` | CNAME `vetorsmart.com.br` → `2.25.235.159` |
| `teste.vetorsmart.com.br` | A `2.25.235.159` (curinga `*.vetorsmart.com.br` existe) |

Não há registros AAAA. Não há divergência entre os servidores. Os dois impedimentos de DNS da 01.3 (`A @` na CDN da Hostinger e falta do curinga) foram resolvidos pelo proprietário.

## 2. Git: ok

- `git fetch`: o checkout estava 4 commits atrás da `origin/main`.
- O `correio.md` e o `executed.md` locais foram copiados para `/opt/infra-abrasil/backups/vetorsmart_wildcard01.4_20261118_120159/`. Depois: `checkout` desses dois arquivos, `merge --ff-only origin/main` e restauração das cópias locais. Nada foi perdido.
- HEAD agora: `1ec0a5c` (inclui `5d2ca56`, `593ed5b`, `c2d5b21` e `1ec0a5c`). Os outros projetos e a infraestrutura não foram tocados.

Testes (MySQL 8.4 descartável em loopback, removido no final):

| Verificação | Resultado |
|---|---|
| Suíte Laravel | 477 aprovados, 9 pulados (os de concorrência), 0 falhas |
| Concorrência MySQL (`VETORSMART_CONCURRENCY=1`) | 9/9 aprovados |
| PHPStan | 0 erros |
| TypeScript (`tsc --noEmit`) | ok, depois de o build regenerar as rotas Wayfinder |
| Build de produção (`npm run build`) | ok |

Nota: a primeira rodada da suíte teve 26 erros de "MySQL server has gone away", porque o MySQL ainda estava reiniciando no fim da inicialização. A segunda rodada, completa, passou. O primeiro `tsc` falhou porque `resources/js/routes` (gerado, fora do Git) estava desatualizado; o build regenerou esses arquivos. O build local vai para `public/build`, que o container do VetorSmart não monta.

**Deploy não realizado.** O container `infra-abrasil-vetorsmart-9` continua com o código anterior: o correio não pede deploy, e a nova versão traz a migration `add_retired_at_to_tenant_domains_table`.

## 3. SSL: interrompido

- Nginx: `nginx -t` ok. Não existe vhost curinga ativo; o modelo está em `docs/infra/vetorsmart-wildcard.conf`.
- Certificado atual: `vetorsmart.com.br` e `www`, ECDSA, emitido por HTTP-01 (webroot), vence em 09/02/2027.
- **Renovação HTTP-01 do certificado atual:** `certbot renew --dry-run --no-random-sleep-on-renew --cert-name vetorsmart.com.br` (staging) passou. Na 01.3, ela falharia.
- Renovação automática: cron do host, `0 3 * * * scripts/renew-certs.sh`. A última execução (18/11, 03:00) rodou normalmente.
- Hook DNS-01 (`docs/infra/hostinger_dns_hook.py`): a sintaxe está ok. Sem token, ele falha de forma controlada: "arquivo do token não encontrado".
- **Token da API Hostinger: ausente.** O diretório `volumes/certbot/conf/hostinger/` não existe. Por isso o DNS-01 não foi validado e nenhum certificado foi emitido.
- HTTPS em `teste.vetorsmart.com.br`: o handshake TLS falha, como esperado sem certificado curinga e sem vhost.

### Risco encontrado para depois da emissão

O `scripts/renew-certs.sh` chama `certbot renew --webroot -w /var/www/certbot`, e isso força o webroot em **todos** os certificados. Um certificado curinga emitido por DNS-01 seria renovado por webroot e falharia, porque um curinga não pode ser validado por HTTP-01. **Corrigido em 18/11/2026, com autorização do proprietário:** o `--webroot -w /var/www/certbot` foi removido do script. Agora cada certificado usa o autenticador gravado no seu `renewal/*.conf`. Os 10 certificados atuais já registram `authenticator = webroot` com o caminho do webroot, então o comportamento deles não muda. O curinga usará o DNS-01 com hook. Validação: `certbot renew --dry-run --no-random-sleep-on-renew` (staging, mesmo comando do script) teve sucesso nos 10 certificados. A versão anterior foi copiada para `backups/vetorsmart_wildcard01.4_20261118_120159/renew-certs.sh.orig`. A alteração ainda não tem commit na infraestrutura.

## 4. Homologação: não realizada

Depende do certificado curinga, do vhost e do deploy. Situação atual:

| Verificação | Resultado |
|---|---|
| HTTPS `vetorsmart.com.br` | 200 |
| HTTPS `www.vetorsmart.com.br` | 301 |
| HTTPS `teste.vetorsmart.com.br` | falha no TLS (pendente) |
| VetorOS, VetorSales, VetorLeads, ABrasilSistemas | 200 |
| Containers `nginx` e `vetorsmart*` | healthy |

`PLATFORM_SUBDOMAIN_SITES` continua desligada.

## Pendências para o proprietário

1. Criar um token da API Hostinger só para DNS e gravá-lo em `/opt/infra-abrasil/volumes/certbot/conf/hostinger/token`, com permissão `600`, sem ecoar no terminal.
2. ~~Autorizar a correção do `scripts/renew-certs.sh`~~: feita (ver acima).
3. Autorizar o deploy da `1ec0a5c` (com a migration) e reenviar o correio para emitir o curinga, ativar o vhost e homologar.

## Restrições cumpridas

DNS, certificados, vhosts, containers de produção, banco, dados de tenants e outros SaaS não foram alterados. O único teste de certificado foi um dry-run em staging. Não houve commit nem push. Nenhuma credencial aparece neste relatório.
