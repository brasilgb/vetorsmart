# VETORSMART-DEPLOY-08 — Implantação Controlada em Produção

Data: 16/11/2026, 16:56 a 17:05 UTC. Local: VPS de produção `srv1990862`, `/opt/infra-abrasil/gateway/vetorsmart`.

## Parecer

**APROVADO.** A produção executa `ce8404b`, os três containers do VetorSmart estão saudáveis e os demais 21 containers do stack não foram tocados.

Ressalva: o atendimento público não pôde ser exercitado de ponta a ponta, porque não existe site de empresa publicado nem DNS/TLS para subdomínios (seção 7). Isso já era assim antes do deploy.

## 1. Versões

| Item | Antes | Depois |
|---|---|---|
| Commit | `d2aec27` | `ce8404b` |
| Imagem `vetorsmart` | `1471e3942ecd` | `4cef542ad53d` |
| Imagem `vetorsmart-worker` | `dfd4539f2d43` | `9283afc61dc8` |
| Imagem `vetorsmart-scheduler` | `5d702a5bbcab` | `dd3844b34aa4` |

Commits implantados: `27ff760` (operações do tenant e fuso da plataforma), `1d86af4` (acessibilidade da interface), `81b116d` e `ce8404b` (documentação).

Conferência da versão: os 526 arquivos do commit que entram na imagem têm SHA-256 idêntico dentro do container. Os 13 restantes são `.gitignore` de `storage/` e `bootstrap/cache`, e o `.env.example`, que não fazem parte da comparação.

## 2. Pré-deploy

| Verificação | Resultado |
|---|---|
| Máquina | VPS de produção, stack `infra-abrasil` com 24 containers saudáveis |
| Repositório | `main` em `d2aec27`, `git fsck` sem erros |
| `origin/main` | contém `ce8404b`, 4 commits lineares sobre `d2aec27` (fast-forward) |
| Arquivos locais | `correio.md`, `executed.md` e `docs/VETORSMART-RELEASE-07.4.md`, preservados |
| Laravel | `production`, debug desligado, `app.timezone = UTC` |
| MySQL | `time_zone = SYSTEM` (UTC), `NOW()` igual a `UTC_TIMESTAMP()`, conexão sem `timezone` explícito |
| `migrate:status` | 36 executadas, nenhuma pendente |
| Schema | nenhuma migração nova em `ce8404b` |
| Dependências | `composer.json`, `composer.lock`, `package.json`, `yarn.lock` e `Dockerfile` sem alteração |
| Configuração | só `config/platform.php`: nova chave `display_timezone`, com padrão `America/Sao_Paulo` |

Nenhuma divergência inesperada. A variável `PLATFORM_DISPLAY_TIMEZONE` é opcional e não foi definida; o padrão está em vigor.

## 3. Backup e recuperação

- Arquivo: `/opt/infra-abrasil/backups/vetorsmart_pre_deploy_ce8404b_20261116_165717.sql.gz` (9.713 bytes, permissão 600).
- SHA-256: `3f283400e79755b2c018e8041c223e6415fbe3fa298d757556e74ff596f74d21`, gravado em `.sha256` ao lado.
- Conteúdo: somente o banco `vetorsmart`, com `mysqldump --single-transaction`, usando o usuário `vetorsmart_user`. Nenhum outro banco incluído.
- Integridade: `gzip -t` aprovado, dump completo, 33 tabelas.
- Restauração testada: carregado num container `mysql:8.4` descartável, sem rede e com dados em memória. Resultado igual ao da produção: 33 tabelas, 2 `users`, 1 `tenants`, 8 `audit_logs`, 10 `segments`, 36 `migrations`. O container foi removido em seguida. O MySQL compartilhado não recebeu nenhum banco temporário.
- Imagens anteriores marcadas como `rollback-pre-ce8404b` nos três repositórios de imagem.

### Procedimento de rollback

1. `cd /opt/infra-abrasil/gateway/vetorsmart && git checkout d2aec27` (ou `git reset --hard d2aec27`, depois de guardar `correio.md` e `executed.md`).
2. Para cada serviço `vetorsmart`, `vetorsmart-worker` e `vetorsmart-scheduler`: `docker tag infra-abrasil-<serviço>:rollback-pre-ce8404b infra-abrasil-<serviço>:latest`.
3. `cd /opt/infra-abrasil && docker compose up -d --no-deps vetorsmart vetorsmart-worker vetorsmart-scheduler`.
4. Conferir saúde e `https://vetorsmart.com.br/login`.

A release não tem migrações, então o rollback de código não exige restaurar o banco. O dump só deve ser restaurado em caso de corrupção de dados, restrito ao banco `vetorsmart`, e depois de avaliar o que foi gravado desde 16:57 UTC.

## 4. Comandos executados

```
git fetch origin
git merge --ff-only ce8404b
mysqldump --single-transaction --no-tablespaces --routines --triggers vetorsmart | gzip   (via docker exec no mysql)
docker run --rm --network none --tmpfs /var/lib/mysql mysql:8.4                          (teste de restauração, removido)
docker tag infra-abrasil-{vetorsmart,vetorsmart-worker,vetorsmart-scheduler}:latest ...:rollback-pre-ce8404b
docker compose build vetorsmart vetorsmart-worker vetorsmart-scheduler
docker compose up -d --no-deps vetorsmart vetorsmart-worker vetorsmart-scheduler
```

Leituras: `docker ps`, `docker inspect`, `docker logs`, `php artisan migrate:status`, `php artisan route:list`, `curl` e consultas `SELECT`.

Não executados: `docker compose down`, `migrate`, reinício ou recriação de MySQL, Redis ou Nginx, alteração de volumes, de `.env` ou de credenciais, push, commit.

## 5. Serviços atualizados

`vetorsmart`, `vetorsmart-worker` e `vetorsmart-scheduler`, recriados às 17:01 UTC e saudáveis em menos de 20 segundos. O build é o procedimento do projeto (`Dockerfile` com `composer install --no-dev` e `yarn build`) e terminou sem erros.

Os outros 21 containers mantiveram o mesmo ID antes e depois.

## 6. Saúde e smoke tests

### Externo, por HTTPS

| Rota | Resultado |
|---|---|
| `/`, `/login`, `/register`, `/up` | 200 |
| `/dashboard`, `/clientes`, `/servicos`, `/profissionais`, `/agenda`, `/rootadmin` sem sessão | 302 para `/login` |
| Cookies de sessão e XSRF | `secure`, sessão `httponly`, `samesite=lax` |
| Assets do build novo (`app-D-6rwPkm.js`, `app-DOsuXVnx.css` e outros) | 200 |
| `robots.txt` | 200 |

### Telas autenticadas

Não havia credenciais para um login pelo navegador, e um login real gravaria sessão e auditoria. As telas foram exercitadas dentro do container, pelo kernel HTTP do Laravel, com os dois usuários reais de produção, sessão em memória e uma transação revertida ao final. `audit_logs` (8) e `sessions` (25) ficaram com a mesma contagem antes e depois.

| Usuário | Rotas | Resultado |
|---|---|---|
| Operador da empresa (não root) | `/dashboard`, `/clientes`, `/servicos`, `/profissionais`, `/agenda`, `/solicitacoes`, `/atendimento-ia`, `/meu-site`, `/equipe`, `/configuracoes` | 200, cada uma com o componente esperado |
| Operador da empresa | `/rootadmin`, `/rootadmin/tenants`, `/rootadmin/users`, `/rootadmin/audit` | 403 |
| RootAdmin | as quatro rotas de `/rootadmin` | 200 |
| RootAdmin | rotas de empresa | 302 para `/rootadmin` |

Isso cobre dashboard, clientes, serviços, profissionais, agenda, permissões administrativas e a separação entre console da plataforma e área da empresa.

### Demais itens

- **Worker e scheduler:** processos `queue:work database` e `schedule:work` ativos, containers saudáveis.
- **Logs:** nenhuma linha de erro ou exceção nos três containers, nenhum 5xx no Nginx, `storage/logs` vazio.
- **Modo claro e escuro:** o CSS publicado contém as regras `.dark` e a página carrega o script de aparência. A legibilidade visual não foi conferida em navegador nesta etapa; vale a homologação da RELEASE-07.3 (54/54) sobre o mesmo código.
- **Isolamento multi-tenant:** há uma única empresa em produção, então não existe par de empresas para um teste cruzado. O bloqueio de `/rootadmin` ao operador e os testes automatizados da release (420 aprovados) são a evidência disponível.
- **Autenticação:** página de login, proteção das rotas e emissão de cookies conferidas. O envio do formulário não foi testado, para não gravar auditoria.

## 7. Migrações

Nenhuma executada. `ce8404b` não altera `database/`, e `migrate:status` continua com 36 executadas e 0 pendentes.

## 8. Problemas identificados

1. **Atendimento público não verificável de fora.** `clima-total.vetorsmart.com.br` não resolve no DNS, e o Nginx não tem certificado para subdomínios (`unrecognized name`). Além disso, o site da única empresa está em `draft`, e a aplicação responde 404 para `/` e `/atendimento` nesse host, o que é o esperado para site não publicado. Nada disso veio desta release. Antes de divulgar o atendimento, é preciso DNS curinga, certificado curinga e bloco de servidor no Nginx, que são mudanças de infraestrutura compartilhada e precisam de autorização própria.
2. `/sitemap.xml` responde 404 no domínio central. A rota já existia em `d2aec27`; o comportamento não foi investigado.
3. A conexão MySQL do Laravel segue sem `timezone` explícito. Sem efeito hoje, porque servidor e aplicação estão em UTC. Continua valendo a recomendação da RELEASE-07.4 de fixar `+00:00`.
4. Avisos do build: `package-lock.json` junto com `yarn.lock` e dependências pares do Vite não satisfeitas. Já existiam e não impediram o build.
5. Há imagens `rollback-pre-*` de quatro deploys anteriores acumuladas. Não foram removidas.

## 9. Situação final da produção

- `https://vetorsmart.com.br` no ar com `ce8404b`.
- 24 de 24 containers saudáveis.
- Banco inalterado: mesmo schema, mesmas contagens.
- Git local: `main` em `ce8404b`, igual a `origin/main`. Sem commit: `correio.md`, `executed.md`, `docs/VETORSMART-RELEASE-07.4.md` e este relatório.
- Rollback disponível pelas imagens `rollback-pre-ce8404b` e pelo dump desta data.
