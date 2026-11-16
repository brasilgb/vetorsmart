# VETORSMART-RELEASE-07.3 — Fechamento e liberação técnica

Data: 16/11/2026. Base revisada: `d2aec27`. Branch: `main`. Ambiente: desenvolvimento local.

## Parecer

**Tecnicamente pronta, implantação ainda bloqueada.** O código, os testes e a homologação complementar no navegador foram aprovados. A inspeção somente leitura da produção não foi feita por falta de acesso. Sem ela não há como confirmar o fuso do MySQL de produção, as migrations aplicadas e a versão implantada.

## 1. Estado do Git

- Branch `main`, HEAD `d2aec27`, sem commits posteriores à base.
- 70 entradas no diretório de trabalho: 62 arquivos modificados e 8 não rastreados, todos preservados.
- `git diff --check`: sem erros de espaço em branco.
- Varredura do diff e dos arquivos novos atrás de chaves privadas, tokens AWS/GitHub/API, senhas literais e e-mails pessoais: nenhuma ocorrência.
- O build (`public/build`) é ignorado pelo Git e não altera o conjunto de alterações.
- Arquivos de controle: `correio.md` e `executed.md` ficam fora dos commits funcionais.

### Lockfiles

O projeto tem `package-lock.json`, `pnpm-lock.yaml` e `yarn.lock`. Os scripts e o build da release usam `npm` (`npm run build` e `npm run types:check`). A alteração em `pnpm-lock.yaml` (158 inclusões e 150 remoções) troca a resolução de `@babel/core` 8.x por 7.29.x no `@rolldown/plugin-babel`, e não é necessária para o fluxo npm. Ela continua preservada no diretório de trabalho e deve ficar fora dos commits até alguém decidir explicitamente adotar o pnpm.

## 2. Produção — somente leitura

**Não executada: não há acesso autorizado.** O `~/.ssh/config` local só define o host `github-vetorsmart` (GitHub). Existe uma chave `vetorsmart_deploy`, mas não há host de VPS configurado nem autorização para usá-la. Nada foi presumido. Continuam sem confirmação:

- o timezone do MySQL de produção e o do Laravel de produção;
- as migrations aplicadas em produção;
- a versão atualmente implantada;
- a compatibilidade entre a configuração de produção e o código preparado.

Referência local, que não serve como evidência de produção: Laravel em UTC, exibição em `America/Sao_Paulo` (`PLATFORM_DISPLAY_TIMEZONE`) e MySQL local com fuso `SYSTEM` (UTC−3). A release traz a migration `2026_11_16_120000_drop_two_factor_columns_from_users_table`, já em `d2aec27`, que precisa ser confirmada em produção.

## 3. Homologação complementar no navegador

Executada em um ambiente isolado, criado só para esta etapa:

- banco MySQL dedicado `vetorsmart_release073`, migrado do zero, sem dados do banco local `vetorsmart`;
- servidor `php artisan serve` na porta 8073, com host central `localhost` e site público em `clima-homolog-073.localhost`;
- contas de teste com o domínio reservado `.invalid` (`owner.073`, `admin.073` e `operador.073@homolog.invalid`);
- Chromium 153 headless (cache do Playwright), controlado por CDP, sem dependência nova no projeto;
- viewports de 1440 × 900 (desktop) e 390 × 844 (mobile).

Resultado: **54 de 54 verificações aprovadas.**

| Cenário | Resultado |
| --- | --- |
| Operador em `/configuracoes` | Campos desabilitados, aviso "Somente leitura", nenhum botão Salvar (desktop e mobile) |
| Operador em `/meu-site` | Abas Conteúdo e Publicação sem "Salvar conteúdo" e sem "Publicar" (desktop e mobile) |
| Operador tentando gravar | `PATCH /configuracoes`, `PATCH /meu-site/rascunho`, `POST /meu-site/dominios` e `POST /meu-site/publicar` devolveram 403 |
| Owner e Admin | Telas editáveis; gravação de configurações persistida; rascunho, subdomínio e publicação aceitos (redirect); 4 versões de site criadas |
| Saudação personalizada | "Bem-vindo à Clima Homolog!" aparece uma única vez, sem a saudação padrão (desktop e mobile) |
| Mensagens genéricas | "Olá" e "Preciso de ajuda" respondem "Posso ajudar com o agendamento. Nossos serviços: …" |
| Confirmação após persistência | Fluxo completo até "Agendamento confirmado … Número da solicitação: #N". O banco tem 2 agendamentos `scheduled` ligados às solicitações #1 e #2, e 08:00 BRT foi gravado como 11:00 UTC |
| Layout | Nenhuma rolagem horizontal nas telas verificadas |

Na primeira rodada, 4 verificações do `/meu-site` (Owner/Admin) falharam por defeito do roteiro: os botões ficam dentro das abas e o roteiro não as abria. Corrigido o roteiro, todas passaram. O produto não foi alterado.

Observação visual, não bloqueante: no modo somente leitura de `/configuracoes`, o `select` de Segmento fica desabilitado, mas sem o esmaecimento dos outros campos.

As capturas, a transcrição e o `results.json` ficaram no diretório temporário da sessão e não fazem parte do repositório.

## 4. Dados de homologação

`homolog:purge` não foi executado, nem em modo prévia. O inventário abaixo veio de consultas somente leitura ao banco local `vetorsmart`.

| Tenant | Nome | Criado em | IDs de usuários |
| --- | --- | --- | --- |
| 1 | [HOMOLOG] Empresa Alfa | 09/11 | 2 (`homolog.alfa`), 4 (`homolog.op`) |
| 2 | [HOMOLOG] Empresa Beta | 09/11 | 3 (`homolog.beta`) |
| 4 | [HOMOLOG] Servicos 04.1 | 11/11 | 6 (`homolog.servicos`) |
| 5 | [HOMOLOG] Empresa 04.3 | 11/11 | 7, 8, 9 (owner, admin e operador 04.3) |
| 9 | [HOMOLOG] 07.1 Empresa A | 16/11 | 15 (owner 07.1A), 17 (operador 07.1) |
| 10 | [HOMOLOG] 07.1 Empresa B | 16/11 | 16 (owner 07.1B) |

Esses tenants também têm 17 solicitações, 8 agendamentos, 12 clientes, 3 conversas e 128 entradas de auditoria. Nenhum desses usuários tem vínculo com tenants fora do conjunto [HOMOLOG].

**Risco encontrado:** `HomologPurgeService::execute()` não remove `service_requests`, `service_request_events`, `appointments` nem `professional_blocks`. As chaves estrangeiras desses registros para `tenants` e `professionals` são `RESTRICT`, então com os dados atuais a remoção falharia. Como roda numa transação, tudo seria desfeito, sem remoção parcial. O comando não serve, portanto, para limpar estes dados.

Estratégia de limpeza por identificação exata, se for autorizada depois:

1. Fixar a lista explícita de IDs (por exemplo, só os tenants 9 e 10 e os usuários 15, 16 e 17 da BACKEND-07.1), sem usar o filtro por prefixo.
2. Remover, numa transação e nesta ordem: eventos de solicitação → agendamentos → solicitações → bloqueios → disponibilidades e vínculos de profissional → profissionais, serviços e categorias → clientes → versões, sites e domínios → configurações → vínculos → tenants → usuários sem outros vínculos.
3. Manter a auditoria (`tenant_id` vira `NULL` pela própria chave).
4. Gerar a prévia das contagens por ID e pedir autorização antes de executar.

O banco isolado `vetorsmart_release073` pode ser descartado inteiro com `DROP DATABASE vetorsmart_release073`. A limpeza não é requisito da release, porque nenhum desses dados vai para a produção.

## 5. Validação final

| Verificação | Resultado |
| --- | --- |
| Suíte completa (`php artisan test`) | 429 testes: 420 aprovados, 9 pulados, 2548 asserções |
| Concorrência MySQL (`VETORSMART_CONCURRENCY=1`) | 9 aprovados, 39 asserções |
| PHPStan | 0 erros |
| TypeScript (`tsc --noEmit`) | aprovado |
| Build (`npm run build`) | aprovado |
| Pint | aprovado |
| Homologação no navegador | 54/54 |

Os 9 testes pulados na suíte comum são os de concorrência, executados à parte. Nenhuma regressão encontrada.

## 6. Proposta de commits

Nenhum commit, push ou deploy foi feito.

1. `fix: stabilize tenant operations and platform time`
   - `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/RootAdmin/{AuditLogController,DashboardController,TenantController}.php`, `app/Http/Controllers/Tenant/{AssistantController,ServiceRequestController}.php`, `app/Http/Middleware/ResolveTenant.php`, `app/Models/AuditLog.php`, `app/Services/AppointmentScheduler.php`, `app/Services/Assistant/AssistantFlow.php`, `app/Services/Websites/WebsitePresenter.php`, `app/Support/PlatformTime.php`, `config/platform.php` e `resources/views/errors/no-active-company.blade.php`.
   - Testes: `tests/Feature/Assistant/AssistantTest.php`, `tests/Feature/Auth/UniversalLoginTest.php`, `tests/Feature/Management/TenantScreensTest.php`, `tests/Feature/Operations/LocalTimeDisplayTest.php`, `tests/Feature/RootAdmin/PlatformTimeDisplayTest.php` e `tests/Feature/Websites/WebsiteTest.php`.
2. `style: refine operational interface accessibility`
   - `resources/css/app.css`, os 40 arquivos alterados em `resources/js/` e `resources/views/{app,website/atendimento}.blade.php`.
3. `docs: record backend and release validation`
   - `docs/VETORSMART-BACKEND-07.md`, `docs/VETORSMART-BACKEND-07.1.md`, `docs/VETORSMART-BACKEND-07.2.md` e `docs/VETORSMART-RELEASE-07.3.md`.

Fora dos commits: `correio.md`, `executed.md` e `pnpm-lock.yaml`.

## 7. Bloqueios restantes

1. Inspeção somente leitura da produção (fuso do MySQL, fuso do Laravel, migrations, versão implantada), que depende de acesso autorizado à VPS.
2. Decisão sobre o tratamento dos registros históricos de auditoria, conforme o fuso do MySQL de produção.
3. Revisão humana e autorização para criar os três commits propostos.

Não bloqueantes: o defeito de `homolog:purge` com solicitações e agendamentos, e o estilo do `select` desabilitado em `/configuracoes`.

## Parecer final sobre a implantação

O código está apto para release: gates locais verdes, nenhuma regressão e os comportamentos da BACKEND-07.2 confirmados em navegador real. **A implantação não deve começar** antes da inspeção somente leitura da produção e da autorização explícita para commit e deploy.
