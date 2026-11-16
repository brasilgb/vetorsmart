# VETORSMART-RELEASE-07.5 — Consolidação local e publicação dos commits

Data: 16/11/2026. Branch: `main`. Ambiente: máquina local de desenvolvimento.

## Parecer

**Commits consolidados e publicados em `origin/main`. Nenhuma implantação foi feita.** A validação completa está verde e o histórico continua linear.

## 1. Máquina e diretório

- Máquina local `PC020109`, WSL2 (kernel `6.18.40.1-microsoft-standard-WSL2`), não a VPS.
- Diretório: `~/projects/laravel/infra-abrasil/gateway/vetorsmart`.
- Remoto: `origin` → `git@github-vetorsmart:brasilgb/vetorsmart.git`.

## 2. Estado inicial do Git

Os três commits da release já tinham sido criados e enviados nesta mesma sessão de trabalho, antes de esta instrução chegar, por um pedido direto de commit e push. Quando a 07.5 começou:

- `main` e `origin/main` estavam em `81b116d`, sem divergência (`0 0` em `HEAD...origin/main`).
- `d2aec27` era ancestral do HEAD, com três commits lineares sobre ele.
- Pendentes no diretório de trabalho, fora da release: `correio.md`, `executed.md` e `pnpm-lock.yaml`.
- Os relatórios BACKEND-07, 07.1, 07.2 e RELEASE-07.3 estavam presentes e versionados.

### Diferenças em relação ao que a instrução pede

- **Mensagens dos commits:** estão em português, no padrão do histórico (`tipo(escopo): descrição`), e não com os títulos em inglês da instrução. Como os commits já estavam publicados e a instrução proíbe reescrever o histórico e usar `--force`, eles foram mantidos. O conteúdo de cada um segue exatamente a divisão proposta na RELEASE-07.3.
- **Ordem da validação:** os commits e o push iniciais vieram antes da validação desta etapa. A validação da seção 4 foi feita depois, sobre o código já commitado, e confirmou o estado aprovado na RELEASE-07.3.
- **Relatório desta etapa:** o commit de documentação já estava publicado, então este relatório entrou num quarto commit de documentação, sem `amend`.

## 3. Arquivos incluídos e excluídos

| Commit | Conteúdo |
| --- | --- |
| `fix(operacao)` | 11 arquivos de `app/` (controllers, middleware, `AuditLog`, services), `app/Support/PlatformTime.php`, `config/platform.php`, `resources/views/errors/no-active-company.blade.php` e 6 testes (`Assistant`, `UniversalLogin`, `TenantScreens`, `LocalTimeDisplay`, `PlatformTimeDisplay`, `Website`) |
| `style(ui)` | `resources/css/app.css`, 40 arquivos em `resources/js/`, `resources/views/app.blade.php` e `resources/views/website/atendimento.blade.php` |
| `docs(release)` | `docs/VETORSMART-BACKEND-07.md`, `07.1.md`, `07.2.md` e `docs/VETORSMART-RELEASE-07.3.md` |
| `docs(release)` (07.5) | este relatório |

Excluídos: `correio.md` e `executed.md` (arquivos de controle) e `pnpm-lock.yaml`. O fluxo da release usa npm, e a troca de resolução do `@babel/core` nesse lockfile não é funcional. Nenhum arquivo foi adicionado com `git add .`. Não há capturas, dados de homologação, arquivos temporários ou credenciais nos commits. `public/build` é ignorado pelo Git.

## 4. Validação

| Verificação | Resultado |
| --- | --- |
| Suíte completa (`php artisan test`) | 429 testes: 420 aprovados, 9 pulados, 2548 asserções |
| Concorrência MySQL (`VETORSMART_CONCURRENCY=1`) | 9 aprovados, 39 asserções |
| PHPStan | 0 erros |
| Pint (`--test`) | aprovado |
| TypeScript (`tsc --noEmit`) | aprovado |
| Build (`npm run build`) | aprovado |
| `git diff --check d2aec27 HEAD` | sem erros |

Os 9 testes pulados na suíte comum são os de concorrência, executados à parte. Os números são iguais aos da RELEASE-07.3, sem regressões. Não houve alteração funcional depois da RELEASE-07.3, então a homologação no navegador (54/54) foi reaproveitada.

## 5. SHAs

1. `27ff760` — `fix(operacao): estabiliza operações do tenant e o fuso da plataforma`
2. `1d86af4` — `style(ui): refina a acessibilidade da interface operacional`
3. `81b116d` — `docs(release): registra relatórios da BACKEND-07, 07.1, 07.2 e RELEASE-07.3`
4. Commit deste relatório: veja `git log` (`docs(release): registra relatório da RELEASE-07.5`).

## 6. Push

- Push inicial: `d2aec27..81b116d main -> main`, normal e sem `--force`.
- Antes do push deste relatório: `git fetch origin` sem commits remotos desconhecidos, sem divergência e sem conflitos. O push foi normal e fast-forward.

## 7. HEAD local e `origin/main`

Depois do push deste relatório, o HEAD local e `origin/main` apontam para o mesmo commit, o que está registrado em `executed.md`.

## 8. Pendências e riscos

1. **Inspeção somente leitura da produção**, ainda pendente e bloqueante para o deploy: fuso do MySQL e do Laravel de produção, migrations aplicadas (inclusive `2026_11_16_120000_drop_two_factor_columns_from_users_table`) e versão implantada.
2. Decisão sobre os registros históricos de auditoria, conforme o fuso do MySQL de produção.
3. `pnpm-lock.yaml` continua modificado e fora dos commits, até alguém decidir adotar o pnpm.
4. Não bloqueantes: `homolog:purge` não remove solicitações e agendamentos, e o estilo do `select` desabilitado em `/configuracoes`.

Não houve deploy, alteração na VPS, migration em produção, reinício de containers nem limpeza de dados de homologação.
