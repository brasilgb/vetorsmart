# Execução: VETORSMART-WILDCARD-01.1 — Homologação, Git e preparação SSL

Data: 17/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`. Nada foi feito na VPS.

## Análise do correio

O `correio.md` mudou: este arquivo registrava a execução da `WILDCARD-01` e o correio atual é a `WILDCARD-01.1`. A etapa foi executada e o conteúdo anterior deste arquivo foi substituído.

## Parecer

**APROVADO COM RESTRIÇÕES** (código e homologação local). **Wildcard NÃO habilitado em produção.** O relatório completo está em `docs/VETORSMART-WILDCARD-01.1.md`.

Restrições:

1. WebKit não testado: faltam bibliotecas do sistema, e instalá-las exige `sudo` com senha.
2. Hook DNS-01 da Hostinger escrito, mas não executado: não há Python nem token aqui.
3. `nginx -t` não executado: não há Nginx nem Docker neste WSL.
4. DNS: `vetorsmart.com.br` ainda está delegado ao Registro.br (`a.sec.dns.br`, `c.sec.dns.br`), sem registro A para o domínio principal, o `www` e o curinga.

## O que foi feito

| Item | Implementado | Testado | Implantado |
| --- | --- | --- | --- |
| Código da WILDCARD-01 | sim | sim | não |
| Correção: cadastro simultâneo com o mesmo nome falhava (7 de 8 com erro 500) | sim | sim (8 processos no MySQL: 8 de 8 ok, 0 duplicados) | não |
| Homologação em navegador (Chromium e Firefox; desktop e mobile; claro e escuro) | — | 124 de 124 | — |
| Hook DNS-01 da Hostinger (`docs/infra/hostinger_dns_hook.py`) | sim | **não executado** | não |
| Vhost `*.vetorsmart.com.br` (`docs/infra/vetorsmart-wildcard.conf`) | sim | **sem `nginx -t`** | não |
| Commits na aplicação | sim | — | push na `origin/main` |

## Verificações

- Suíte com `VETORSMART_CONCURRENCY=1`: **486 de 486, 0 pulados** (3126 asserções).
- PHPStan 0 erros, Pint ok, `tsc` 0 erros, build ok.
- axe WCAG 2.0/2.1 A e AA: 0 violações em 32 combinações.
- Credenciais: nada sensível nos commits.

## SSL

O plugin comunitário `certbot-dns-hostinger` foi recusado (3 estrelas, sem releases nem mantenedor identificado). Foi adotado um hook próprio, com a biblioteca padrão do Python e a API oficial (`PUT`/`DELETE /api/dns/v1/zones/{domain}`). O token fica em arquivo `600` e nunca é impresso. A renovação é feita por cron no host com `certbot renew`. A comprovação depende do DNS delegado à Hostinger e de um `--dry-run`.

## Commits

- `5d2ca56` `feat(sites)`: subdomínios públicos por empresa.
- `593ed5b` `fix(cadastro)`: slug único em cadastros simultâneos.
- `c2d5b21` `docs(infra)`: hook DNS-01 e vhost curinga, preparados.
- Commit seguinte: relatórios, `correio.md` e este arquivo.

Fora dos commits: `pnpm-lock.yaml`, que já estava modificado antes e não tem relação com este trabalho. Repositório da infraestrutura (HEAD destacado, conflito em `gateway/vetorleads`): não tocado.

## Pendências (Fase B, com autorização)

1. Conferir se `vetorsmart.com.br` resolve em produção agora (não há registro A visível).
2. Concluir a delegação à Hostinger e criar `A @` e `A *`.
3. Criar um token dedicado na Hostinger, rodar o `--dry-run` do hook, emitir o certificado, rodar `renew --dry-run` e configurar o cron.
4. `nginx -t` com o vhost, reload e verificação dos outros sistemas.
5. Migration, `sites:assign-subdomains --execute`, homologação HTTPS de um tenant `[HOMOLOG]` (incluindo Safari/WebKit) e só então `PLATFORM_SUBDOMAIN_SITES=true`.
