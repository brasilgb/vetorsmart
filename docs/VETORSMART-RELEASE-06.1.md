# VETORSMART-RELEASE-06.1 — Auditoria de produção e preparação de implantação

Data: 14/11/2026. Diretório: `gateway/vetorsmart/`. Versão candidata: `f2b898b`. Base anterior: `6399bd4`.

**Recomendação final: BLOQUEADO.**

Esta etapa é somente de diagnóstico. Nenhum deploy, migração ou reinício foi executado, e esta etapa não fez push. Nenhum Artisan que altere caches ou estado foi rodado.

> **Atualização de 15/11/2026.** Os três commits (`18a7c2a`, `b7587be`, `f2b898b`) foram enviados a `origin/main` em 14/11/2026 às 14:57, conforme o reflog (`update by push`). Em 15/11/2026 também foi enviado `285a0bd` (ajuste da landing aos recursos entregues). Continua sem deploy e sem migração.

## 0. Limite desta auditoria

**A VPS não foi auditada.** Esta máquina é local. Não há chave SSH para a VPS em `~/.ssh` (só `known_hosts`), não há host da VPS configurado e não existe `/opt/infra-abrasil` aqui. A própria documentação registra isso (`infra-abrasil/executed.md`: "Esta máquina é local... não há host da VPS configurado"). Por isso, as seções 1 e 3 abaixo não tiveram leitura da VPS. Não foi tentada nenhuma conexão remota, e nenhum endereço foi inventado.

O que foi verificado é o que existe no repositório, no Git e na documentação da infraestrutura, todos em leitura.

## 1. Estado da VPS e recursos compartilhados

- **Não inspecionado**: versões de PHP, MySQL, Nginx e PHP-FPM na VPS; OPcache; RAM, CPU, disco; containers; workers; processos.
- **Recursos compartilhados identificados pela infraestrutura** (`infra-abrasil/docker-compose.yml` e `mysql-init/01-databases.sh`):
  - Um único MySQL 8.4 para todos os sistemas, com bancos `vetoros`, `vetorpet`, `abrasilsistemas`, `ab_prospect` e `desgarrados`.
  - Redis, phpMyAdmin, WAHA e n8n, compartilhados.
  - Nginx único (`nginx:1.27-alpine`) com vhosts de VetorOS, VetorPet, AbraSistemas, Desgarrados e outros.
  - Certbot e scripts de renovação de certificados.
- **Achado principal**: o compose compartilhado **não tem serviço, banco nem vhost do VetorSmart**. Implantar a release exigiria criar tudo isso na infraestrutura compartilhada. Isso é alteração de infraestrutura compartilhada, proibida nesta etapa e fora do escopo da release.

## 2. Configuração de produção

Verificada no repositório. Nenhum segredo foi lido ou exibido. Nenhum arquivo de ambiente foi modificado.

| Item | Situação no código | Risco se a produção não sobrescrever |
|---|---|---|
| `APP_ENV` | padrão `production` em `config/app.php` | seguro por padrão, mas `.env.example` traz `local` |
| `APP_DEBUG` | padrão `false` em `config/app.php` | **`.env.example` traz `true`**; se o arquivo for copiado, debug vai para produção |
| `APP_KEY` | não verificável (é segredo) | sem chave, a aplicação não sobe |
| `APP_URL` | `.env.example` traz `http://localhost:8000` | precisa ser o domínio HTTPS real |
| `SESSION_SECURE_COOKIE` | padrão `true` quando `APP_ENV=production` | seguro se `APP_ENV` estiver correto; recomendado definir explicitamente |
| Cookies de sessão | `httpOnly=true`, `SameSite=lax` (padrões) | adequado |
| `SESSION_ENCRYPT` | padrão `false`; `.env.example` traz `false` | payloads de sessão ficam sem criptografia no banco; recomendado `true` |
| Driver de sessão, cache e fila | `database` nos padrões e no `.env.example` | exige as tabelas migradas no banco de produção |
| `LOG_LEVEL` | padrão `debug`; `.env.example` traz `debug` | **logs de depuração em produção**; recomendado `warning` |
| Proxy confiável (`PLATFORM_TRUSTED_PROXIES`) | padrão vazio (`config/platform.php`) | **atrás do nginx sem essa variável, o Laravel não reconhece HTTPS**: afeta URLs geradas e a detecção de conexão segura do cookie do visitante |
| Hosts confiáveis | `trustHosts` com padrões de `TrustedHosts` | adequado, depende de `PLATFORM_CENTRAL_HOST` e `PLATFORM_TENANT_BASE_DOMAIN` corretos |
| Redis | não usado pelo VetorSmart (cache e sessão em banco) | nenhum |
| Conexão MySQL | `DB_DATABASE` do `.env.example` é `vetorsmart`; `DB_PASSWORD` é placeholder | não verificável sem acesso à VPS |
| Permissões de storage e cache | não verificáveis sem acesso à VPS | — |
| HTTPS e certificados | não verificáveis; não há vhost nem certificado do VetorSmart na infraestrutura | bloqueante (seção 1) |

Requisitos de produção que o `.env` de produção precisa declarar: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` HTTPS, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `LOG_LEVEL=warning`, `PLATFORM_TRUSTED_PROXIES` com o IP do nginx, `PLATFORM_CENTRAL_HOST` e `PLATFORM_TENANT_BASE_DOMAIN` reais. As variáveis `VETORSMART_TEST_DATABASE` e `VETORSMART_DESTRUCTIVE_DATABASE` **não devem** estar definidas em produção.

## 3. Banco de dados

- **Não executado**: `php artisan migrate:status` exige conexão com o banco de produção, e a VPS não é acessível daqui. Não foi usado nenhum banco de outra origem para simular o status.
- **Migrações pendentes**: nenhuma migração nova nesta release. Confirmado com `git diff 6399bd4 f2b898b -- database`: zero arquivos alterados. Pendências de releases anteriores, se houver, precisam ser verificadas com `migrate:status` na VPS.
- **Schema e índices**: as migrações e os índices do código foram conferidos no repositório (incluindo `appointments`, com índice `(tenant_id, status, starts_at)`). A comparação com o banco real depende de acesso à VPS.
- **Nome do banco**: o banco do VetorSmart previsto no `.env.example` é `vetorsmart`. Não existe na inicialização do MySQL compartilhado (`mysql-init/01-databases.sh`).

## 4. Backup e rollback

- **Backups do VetorSmart: nenhuma evidência.** Não há backup registrado na documentação, nem arquivo de dump local, nem procedimento de dump para o banco do VetorSmart na infraestrutura.
- **Integridade e restauração: não verificáveis.** Sem backup, não há o que restaurar.
- **Compatibilidade da base `6399bd4`**: a base é compatível em termos de schema, porque nenhuma migração foi adicionada desde ela. Verificado no Git.
- **Dependências anteriores**: o `composer.lock` de `6399bd4` difere do `f2b898b` somente pelos 15 pacotes do commit 3, que pode ser revertido isoladamente (`git revert f2b898b` ou checkout do lock anterior, seguido de `composer install`).
- **Caches e assets**: recriáveis com `optimize:clear` e `config:cache`/`route:cache`/`event:cache`/`view:cache`, e `npm run build` a partir do código da base.
- **Isolamento do rollback**: o rollback do VetorSmart não afeta os outros SaaS, porque eles não compartilham código, mas compartilham o MySQL e o nginx. Qualquer restauração de banco deve ser restrita ao banco do VetorSmart. Não restaurar o servidor MySQL inteiro.

Pela regra do correio, a ausência de evidência de recuperação **bloqueia a implantação**.

## 5. Git e release

- Sequência íntegra: `6399bd4` → `18a7c2a` → `b7587be` → `f2b898b`. `git fsck` sem erros. `git log` mostra os três commits na ordem prevista.
- Commits com 23, 16 e 1 arquivos. Verificado: sem arquivo não relacionado nos commits. `correio.md` e `executed.md` não estão em nenhum commit.
- Remoto `origin` configurado (`github.com/brasilgb/vetorsmart`). Os três commits **já estão em `origin/main`**, enviados em 14/11/2026 às 14:57; o branch `main` não está à frente do remoto nesses commits.
- `docs/VETORSMART-RELEASE-06.md` e este relatório ficaram fora dos commits da release e foram registrados em commit documental separado.
- Estado do diretório: `correio.md` e `executed.md` modificados, fora de qualquer commit.

## 6. Segurança e comportamento funcional (configuração)

| Requisito | Situação |
|---|---|
| Isolamento multi-tenant | coberto por testes automatizados (`CrossTenantWebTest`, `OperationalIsolationTest`, `TenantIsolationTest`), suíte 435 de 435 na RELEASE-06 |
| Autenticação e MFA | Fortify com 2FA configurado em `config/fortify.php`; limite de login de 5 tentativas por minuto por e-mail e IP (`FortifyServiceProvider`) |
| Tokens dos visitantes | aleatórios de 48 caracteres, guardados como SHA-256, comparados com escopo de empresa, cookie `httpOnly`, `SameSite=lax`, cifrado pelo Laravel; testes em `VisitorTokenSecurityTest` |
| Cookies seguros | depende de `SESSION_SECURE_COOKIE=true` e do proxy confiável (seção 2) |
| Retenção das conversas | aplicada na leitura por token desde o commit 1, e a purga existe em `PurgeAssistantConversations` |
| Rotas administrativas | protegidas por middleware de autenticação e por verificação de MFA para o root admin (`EnsureRootAdminHasTwoFactor`) |
| Rate limiting | login (5/min), dois fatores, e rotas públicas do atendimento (`assistant-public`, 30/min por empresa) |
| Logs sem dado sensível | auditados nas execuções anteriores; o log de falha de agendamento contém só referência, classe da exceção e IDs. **Pendente**: `LOG_LEVEL` padrão `debug` precisa ser sobrescrito em produção |

**Regra da triagem após falha de agendamento**: documentada em `docs/VETORSMART-BACKEND-05.7.md` (seção 6) e testada em `BookingFailureRuleTest` (6 testes). Verificado: a regra está registrada.

## 7. Estratégia de implantação (proposta, não executada)

Pré-condição: a VPS precisa ser acessível e auditada antes de qualquer passo. Sem isso, nenhum passo abaixo é executável.

1. **Janela de manutenção**: fora do horário de atendimento. Avisar as equipes de VetorOS, VetorPet, AbraSistemas, Desgarrados e AB Prospect, porque o MySQL e o nginx são compartilhados.
2. **Usuários ativos**: conferir conversas do atendimento abertas (`assistant_conversations` com status ativo ou aguardando atendente) e solicitações em andamento, e combinar o momento.
3. **Backup validado**: dump só do banco do VetorSmart com `mysqldump --single-transaction`, gravado fora do container, com SHA-256 registrado, e restauração testada em banco separado antes do deploy.
4. **Preparação dos artefatos**: checkout de `f2b898b` em diretório novo; `composer install --no-dev --optimize-autoloader`; `npm ci && npm run build`. Validar `.env` de produção com a lista da seção 2.
5. **Atualização controlada**: trocar o código do VetorSmart mantendo o diretório anterior para rollback imediato.
6. **Verificação de migrações**: `php artisan migrate:status` no banco do VetorSmart. Esperado: zero pendências desta release. Não executar `migrate --force` sem pendência identificada.
7. **Caches**: `optimize:clear`; depois `config:cache`, `route:cache`, `event:cache`, `view:cache`, como o usuário da aplicação.
8. **Reinício restrito**: somente o PHP-FPM do VetorSmart e o worker/scheduler dele, se existirem. Não reiniciar o nginx, o MySQL, o Redis nem os outros sistemas.
9. **Smoke tests**: login, dashboard, fila de atendimento, solicitações, agenda, histórico, e uma conversa de atendimento no site público em empresa de teste.
10. **Monitoramento inicial**: logs de erro do Laravel e do PHP-FPM, latência das cinco rotas, e conexões MySQL, por pelo menos 30 minutos.
11. **Critérios objetivos de rollback** (qualquer um aciona o rollback):
    - erro 5xx em mais de 1% das requisições nas cinco rotas durante 5 minutos;
    - falha em qualquer smoke test;
    - p95 acima de 1 s com 10 usuários nas rotas da agenda ou da fila;
    - qualquer registro de vazamento entre empresas;
    - falha de login para usuários válidos.

## 8. Plano de rollback

1. Voltar o código do VetorSmart para o diretório anterior, que roda `6399bd4`.
2. Reinstalar dependências a partir do `composer.lock` de `6399bd4` (`composer install --no-dev --optimize-autoloader`).
3. Recriar os caches com os comandos da seção 7.
4. Recarregar somente o PHP-FPM do VetorSmart.
5. Nenhuma migração a reverter, porque a release não tem migrações.
6. Restaurar o banco do VetorSmart **somente** a partir do backup validado, e apenas se houve escrita incompatível. Não restaurar o MySQL compartilhado. Antes de restaurar, confirmar com o responsável e registrar.
7. Repetir os smoke tests da seção 7.
8. Registrar o motivo do rollback e as conversas e solicitações criadas durante a janela.

## 9. Riscos residuais

1. **VPS não auditada**: estado dos serviços, recursos, versões e certificados desconhecidos.
2. **Infraestrutura do VetorSmart inexistente** no compose, no nginx e no MySQL compartilhados.
3. **Sem backup verificado** do banco do VetorSmart.
4. **Configuração de produção não verificável**: `.env` real, `APP_KEY`, `APP_URL`, proxy confiável, `LOG_LEVEL`, `SESSION_ENCRYPT`.
5. **Recursos compartilhados**: MySQL, Redis e nginx atendem outros sistemas. Um problema no VetorSmart pode afetá-los.
6. **Desempenho medido em laboratório** (RELEASE-06 e 05.7), não em produção.
7. **Logs de depuração** por padrão nos arquivos de exemplo.

## 10. Condições para liberar (APTO)

1. Acesso à VPS (host, chave e diretório da aplicação) e auditoria da seção 1 registrada.
2. Infraestrutura do VetorSmart definida pelo responsável da infraestrutura: serviço PHP-FPM e nginx, banco no MySQL compartilhado, vhost e certificado HTTPS. Isso é uma decisão de infraestrutura compartilhada, fora desta release.
3. Backup do banco do VetorSmart feito e restaurado com sucesso em banco separado.
4. `.env` de produção revisado contra a lista da seção 2.
5. `migrate:status` na VPS sem pendências inesperadas.
6. Autorização explícita para deploy. Os commits já estão no remoto.

## 11. Ações não realizadas

- Nenhum deploy, nenhuma migração, nenhum reinício. Nenhum push nesta etapa.
- Nenhuma conexão com a VPS, porque não há acesso configurado.
- Nenhuma alteração em infraestrutura compartilhada, VetorSales, VetorOS, VetorLeads ou nos demais SaaS.
- Nenhum Artisan que altere caches ou estado foi executado nesta etapa.
- Nenhum segredo, senha, token ou chave foi lido ou exibido.
