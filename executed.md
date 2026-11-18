# Execução: VETORSMART-WILDCARD-01.4 — Validação DNS e SSL

Data: 18/11/2026, das 12:01 às 12:15 UTC. Local: VPS de produção `srv1990862` (`2.25.235.159`).

## Análise do correio

O `correio.md` mudou: este arquivo registrava a WILDCARD-01.3 e o correio atual é a WILDCARD-01.4. A etapa foi executada e o conteúdo anterior deste arquivo foi substituído.

## Parecer

**APROVADO COM RESTRIÇÕES: interrompido antes da emissão do certificado.** O token da API Hostinger não está na VPS, e o correio proíbe emitir sem validar o DNS-01. Relatório completo: `docs/VETORSMART-WILDCARD-01.4.md`.

## Resultados

1. **DNS: ok.** Nos autoritativos da Hostinger e em 8.8.8.8, 1.1.1.1 e 9.9.9.9: `vetorsmart.com.br` → `2.25.235.159`, `www` → CNAME para o domínio principal, `teste` → `2.25.235.159` (o curinga existe). Não há AAAA.
2. **Git: ok.** Fast-forward de 4 commits até `1ec0a5c`. O `correio.md` e o `executed.md` foram preservados (cópia em `backups/vetorsmart_wildcard01.4_20261118_120159/`).
   - Suíte: 477 aprovados, 9 pulados, 0 falhas. Concorrência MySQL: 9/9.
   - PHPStan: 0 erros. TypeScript: ok. Build: ok.
3. **SSL: parcial.**
   - `nginx -t` ok.
   - O dry-run da renovação HTTP-01 do certificado atual (vence em 09/02/2027) passou. O cron diário das 03:00 está ativo.
   - O hook DNS-01 está ok, mas **sem token**. Nenhum certificado foi emitido.
4. **Homologação: pendente.**
   - HTTPS 200 no domínio principal. O `teste.vetorsmart.com.br` falha no TLS, porque ainda não há certificado curinga nem vhost.
   - Os outros SaaS respondem 200 e os containers estão healthy.
   - `PLATFORM_SUBDOMAIN_SITES` continua desligada.

## Risco encontrado e corrigido

O `scripts/renew-certs.sh` forçava `--webroot` em todos os certificados, e o futuro curinga emitido por DNS-01 falharia na renovação automática. **Corrigido com autorização:** o flag foi removido e cada certificado agora usa o autenticador do seu `renewal/*.conf`. O dry-run em staging com o mesmo comando teve sucesso nos 10 certificados. Backup do script original: `backups/vetorsmart_wildcard01.4_20261118_120159/renew-certs.sh.orig`. A alteração ainda não tem commit na infraestrutura.

## Para destravar (proprietário)

1. Gravar o token da API Hostinger (só DNS) em `volumes/certbot/conf/hostinger/token`, com permissão `600`.
2. Autorizar o deploy da `1ec0a5c` (traz a migration `retired_at`) e reenviar o correio.

## Restrições cumpridas

Fora o `renew-certs.sh` (autorizado), DNS, certificados, vhosts, containers de produção, banco, tenants e outros SaaS não foram alterados. Não houve deploy, commit nem push. Nenhuma credencial nos relatórios.
