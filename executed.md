# Execução: VETORSMART-DEPLOY-08 — Implantação Controlada em Produção

Data: 16/11/2026, 16:56 a 17:05 UTC. Local: VPS de produção `srv1990862`, `gateway/vetorsmart/`.

## Análise do correio

`correio.md` difere do anterior. A última instrução executada era a `RELEASE-07.4` (no Git ainda consta a `BACKEND-05.3`); a atual é a `DEPLOY-08`. Ela foi executada. O conteúdo anterior deste arquivo foi apagado.

## Parecer

**APROVADO.** Produção em `ce8404b`, saudável. Relatório completo: `docs/VETORSMART-DEPLOY-08.md`.

Ressalva: o atendimento público não pôde ser testado de ponta a ponta (item 7).

## 1. Versão anterior e nova

- Antes: `d2aec27`. Depois: `ce8404b` (fast-forward de 4 commits).
- Conferido: 526 arquivos do commit com SHA-256 idêntico dentro do container.

## 2. Backup e recuperação

- `/opt/infra-abrasil/backups/vetorsmart_pre_deploy_ce8404b_20261116_165717.sql.gz`
- SHA-256 `3f283400e79755b2c018e8041c223e6415fbe3fa298d757556e74ff596f74d21`.
- Só o banco `vetorsmart`, com `--single-transaction`.
- Restauração testada num MySQL descartável e isolado: 33 tabelas e contagens iguais às da produção.
- Imagens anteriores marcadas como `rollback-pre-ce8404b`.
- Rollback documentado no relatório. Não exige restaurar o banco, porque a release não tem migrações.

## 3. Comandos executados

```
git fetch origin
git merge --ff-only ce8404b
mysqldump --single-transaction ... vetorsmart | gzip
docker run --rm --network none --tmpfs /var/lib/mysql mysql:8.4   (teste de restauração, removido)
docker tag infra-abrasil-<serviço>:latest infra-abrasil-<serviço>:rollback-pre-ce8404b
docker compose build vetorsmart vetorsmart-worker vetorsmart-scheduler
docker compose up -d --no-deps vetorsmart vetorsmart-worker vetorsmart-scheduler
```

O resto foi leitura.

## 4. Serviços atualizados

`vetorsmart`, `vetorsmart-worker`, `vetorsmart-scheduler`. Os outros 21 containers mantiveram o mesmo ID. MySQL, Redis e Nginx não foram reiniciados.

## 5. Saúde e smoke tests

- HTTPS: `/`, `/login`, `/register`, `/up` com 200. Rotas protegidas sem sessão: 302 para `/login`.
- Assets do build novo: 200.
- Operador da empresa: dashboard, clientes, serviços, profissionais, agenda, solicitações, atendimento, site, equipe e configurações com 200. `/rootadmin`: 403.
- RootAdmin: as quatro telas de `/rootadmin` com 200.
- As telas autenticadas foram testadas dentro do container, com sessão em memória e transação revertida. `audit_logs` (8) e `sessions` (25) não mudaram.
- Worker e scheduler ativos e saudáveis.
- Sem erros nos logs dos três containers, sem 5xx no Nginx.
- 24 de 24 containers saudáveis.

Não conferido nesta etapa: legibilidade visual do modo claro e escuro em navegador (o CSS com `.dark` está publicado), envio do formulário de login e teste cruzado entre empresas (só existe uma).

## 6. Migrações

Nenhuma. `ce8404b` não altera `database/`. `migrate:status`: 36 executadas, 0 pendentes.

## 7. Problemas identificados

1. Atendimento público não verificável de fora: `clima-total.vetorsmart.com.br` não resolve no DNS, o Nginx não tem certificado para subdomínios, e o site da única empresa está em `draft` (404, como esperado). Já era assim antes do deploy. Corrigir exige mudança na infraestrutura compartilhada, com autorização própria.
2. `/sitemap.xml` responde 404 no domínio central. Não investigado.
3. Conexão MySQL do Laravel ainda sem `timezone` explícito. Sem efeito hoje (tudo em UTC).
4. Avisos antigos do build (`package-lock.json` junto com `yarn.lock`, dependências pares do Vite).
5. Imagens `rollback-pre-*` de deploys anteriores acumuladas, não removidas.

## 8. Situação final

- `https://vetorsmart.com.br` no ar com `ce8404b`.
- Banco inalterado.
- `main` local igual a `origin/main`.
- Sem commit: `correio.md`, `executed.md`, `docs/VETORSMART-RELEASE-07.4.md`, `docs/VETORSMART-DEPLOY-08.md`.

## Restrições cumpridas

- Nenhum outro SaaS modificado.
- Sem `docker compose down`, sem recriar MySQL, Redis ou Nginx, sem alterar volumes.
- Sem migração, limpeza de dados, alteração de planos, de variáveis de ambiente ou do histórico de auditoria.
- Sem commit e sem push.
- Nenhuma credencial impressa.
