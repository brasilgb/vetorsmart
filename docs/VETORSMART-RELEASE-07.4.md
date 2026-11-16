# VETORSMART-RELEASE-07.4 — Consolidação Git e inspeção de produção

Data: 16/11/2026 (16:33 UTC). Local: VPS de produção (`srv1990862`), `gateway/vetorsmart/`.

## Resumo

| Item | Resultado |
|---|---|
| Três commits da RELEASE-07.3 | **Não criados.** As alterações não existem neste repositório. |
| Inspeção da VPS (somente leitura) | **Realizada.** |
| Auditoria histórica de timestamps | **Realizada.** Nenhum registro precisa de correção. |
| Push, deploy, migrações, reinícios | **Nenhum executado.** |

## 1. Consolidação Git — bloqueada

Os commits planejados não foram criados porque não há o que incluir neles:

- A árvore de trabalho tem uma única alteração: `correio.md` (excluído dos commits pelo próprio correio).
- Não há stash, arquivo não rastreado nem worktree adicional.
- `main` está igual a `origin/main` em `d2aec27` (0 à frente, 0 atrás).
- Não existe `docs/VETORSMART-RELEASE-07.3.md` nem `docs/VETORSMART-BACKEND-07.1.md`. A única menção a "RELEASE-07" ou "BACKEND-07" em toda a árvore `/opt/infra-abrasil` está no `correio.md` e em dois documentos de visão de produto.
- A branch local `wip/vetorsmart-20261115` (`32a7b45`) é o backup anterior ao deploy de 15/11 e não contém o trabalho da 07.3.

Conclusão: o trabalho da RELEASE-07.3 (operações do tenant, horário da plataforma, acessibilidade da interface e documentos) foi feito em outra cópia do repositório e não chegou a esta máquina. Nenhum commit vazio ou aproximado foi criado.

**SHAs dos três commits:** inexistentes.

## 2. Inspeção da VPS (somente leitura)

| Item | Valor |
|---|---|
| Commit implantado | `d2aec27` — `chore(db): remove as colunas two_factor da tabela users` |
| Como foi confirmado | Os 395 arquivos rastreados de `app`, `config`, `routes`, `database`, `resources`, `lang`, `bootstrap/app.php`, `composer.lock` e `package.json` têm SHA-256 idêntico no container e no `HEAD` |
| Branch em produção | `main`, igual a `origin/main` |
| Imagens | Construídas em 16/11/2026 11:02 UTC; containers iniciados às 11:03 UTC |
| PHP | 8.4.27 |
| Laravel | 13.42.0 |
| MySQL | 8.4.11 |
| Ambiente | `production`, debug desligado, manutenção desligada |
| Containers | `vetorsmart`, `vetorsmart-worker`, `vetorsmart-scheduler`: em execução e saudáveis. `mysql`, `redis` e `nginx`: saudáveis |
| Cache | Configuração, rotas e eventos sem cache; views em cache |
| Drivers | Cache, fila e sessão em `database`; e-mail em `log`; assistente `none` |

### Timezone

| Camada | Valor efetivo |
|---|---|
| Laravel `app.timezone` | `UTC` |
| PHP (`date_default_timezone_get`) | `UTC` |
| Conexão MySQL no Laravel (`timezone`) | não definido (usa o padrão do servidor) |
| MySQL `@@global.time_zone` / `@@session.time_zone` | `SYSTEM` |
| MySQL `@@system_time_zone` | `UTC` |
| `NOW()` e `UTC_TIMESTAMP()` no MySQL | idênticos |
| Variável `TZ` nos containers da aplicação e do MySQL | não definida (relógio em UTC) |

Aplicação e banco estão no mesmo fuso (UTC). Não há defasagem entre o relógio do Laravel e o do MySQL.

### Migrações

36 migrações, todas executadas (lotes 1 a 4). **Nenhuma pendente.** A última é `2026_11_16_120000_drop_two_factor_columns_from_users_table`.

### Configurações necessárias à nova release

Não avaliadas: sem o código da RELEASE-07.3 não é possível saber quais variáveis ou migrações ela exige.

## 3. Auditoria histórica de timestamps

### Como os registros são gravados

- Colunas `created_at` e `updated_at` dos modelos: preenchidas pelo relógio da aplicação (UTC).
- Quatro colunas usam o relógio do MySQL (`DEFAULT CURRENT_TIMESTAMP`): `audit_logs.created_at`, `service_request_events.created_at`, `growth_events.occurred_at` e `failed_jobs.failed_at`.
- `appointments.starts_at/ends_at` e `professional_blocks.starts_at/ends_at` são `datetime` (sem conversão de fuso pelo MySQL); as demais são `timestamp`.

### Dados existentes em produção

| Tabela | Linhas | Intervalo de `created_at` (UTC) |
|---|---|---|
| `segments` | 10 | 12/11 19:08 |
| `users` | 2 | 13/11 17:54 a 16/11 10:41 |
| `audit_logs` | 8 | 13/11 17:54 a 16/11 10:42 |
| `tenants`, `tenant_users`, `tenant_settings` | 1 cada | 16/11 10:41 |
| `tenant_websites` | 1 | 16/11 10:42 |
| Demais tabelas (agendamentos, solicitações, atendimento, clientes, etc.) | 0 | — |

### Classificação

- **Gravados corretamente em UTC:** todos os 24 registros. O primeiro `audit_logs` (relógio do MySQL) tem o mesmo segundo do primeiro `users` (relógio da aplicação): 13/11 17:54:29. Os dois relógios coincidem.
- **Possivelmente gravados em horário local do MySQL:** nenhum identificado. O MySQL opera em UTC; nenhum registro tem data futura.
- **Novos, pelo relógio da aplicação:** continuarão em UTC.

Limite da evidência: a configuração foi lida hoje. Não há registro histórico provando que o MySQL esteve sempre em UTC, mas o `docker-compose.yml` não define `TZ` para o serviço e os dados são coerentes com UTC.

### Decisão recomendada

**Não corrigir nenhum timestamp histórico.** Não há registro afetado e as tabelas de agenda estão vazias.

Recomendação para a próxima release: definir explicitamente `'timezone' => '+00:00'` na conexão MySQL do Laravel (ou `TZ=UTC` no serviço `mysql`), para que as quatro colunas com `CURRENT_TIMESTAMP` não dependam do fuso do servidor.

## 4. Verificação final

| Verificação | Resultado |
|---|---|
| Integridade dos três commits | Não se aplica: commits não criados |
| Regressões | Nenhuma alteração de código feita; testes não reexecutados |
| Compatibilidade preliminar com produção | Não avaliada para a 07.3 (código ausente). A produção atual está íntegra e igual ao `main` |
| Alterações nos demais SaaS | Nenhuma |
| Operações destrutivas | Nenhuma. Apenas leituras (`SELECT`, `migrate:status`, `about`, `docker inspect`) |

## 5. Estado final do Git

- Branch `main` em `d2aec27`, igual a `origin/main`.
- Modificado: `correio.md`, `executed.md`.
- Novo, não rastreado: `docs/VETORSMART-RELEASE-07.4.md`.
- Nenhum commit, push, reset ou alteração de histórico.

## 6. Riscos e bloqueios

1. **Bloqueio principal:** as alterações da RELEASE-07.3 não estão nesta máquina. É preciso localizar a cópia onde foram feitas e trazê-las (por branch ou patch) antes de consolidar.
2. O `executed.md` versionado continha marcadores de conflito de merge (`<<<<<<<`, `>>>>>>>`). Foi substituído nesta etapa, mas a versão com marcadores permanece no histórico.
3. A branch `wip/vetorsmart-20261115` diverge do `main` em 88 arquivos e continua sem destino definido.
4. Quatro colunas dependem do fuso do servidor MySQL (ver seção 3).
5. `public/storage` não está vinculado no container (`NOT LINKED`); os arquivos públicos são servidos pelo volume montado no nginx. Apenas registrado, sem alteração.

## 7. Parecer sobre o deploy

**Deploy não recomendado neste momento.** Não há release a implantar: a produção já executa o último commit do `main` e o conteúdo da RELEASE-07.3 não está disponível para revisão. A autorização deve ser reavaliada depois que os três commits existirem, forem revisados e suas exigências de configuração e migração forem comparadas com esta inspeção.

Encerrado sem implantação.
