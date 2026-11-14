# VETORSMART-BACKEND-05.5 — Relatório de homologação funcional e fechamento da release

Data: 14/11/2026. Diretório: `gateway/vetorsmart/`. Banco de testes: MySQL 8.4.11 em container isolado, porta somente em `127.0.0.1`, removido ao final. Homologação de navegador: servidor local `127.0.0.1:8100`, Chromium 1243 (Playwright), tenant de teste "Clima Total" com usuários Owner, Admin e Operador de teste.

**Parecer final: BLOQUEADO.** Motivos: o gate oficial de tipos (`composer types:check`, PHPStan) falha com 88 erros pré-existentes; o isolamento entre tenants não foi homologado no navegador; e `composer.lock` aguarda decisão separada.

## 1. Preparação

- Estado do Git verificado no início: alterações pendentes em `app/Services/AppointmentScheduler.php`, `composer.lock`, `correio.md`, `docs/VETORSMART-BACKEND-05-ATENDIMENTO-IA.md`, `executed.md`, `tests/Feature/Assistant/AssistantTest.php` e `tests/Concurrency/` (não rastreado). Nenhuma alteração foi descartada.
- Sem deploy. Sem alteração de infraestrutura compartilhada. Nenhum commit.
- O servidor do VetorSales (porta 8000) foi apenas consultado e continua sem resposta. Nenhum serviço de outro projeto foi reiniciado.
- Não foram usados dados reais. Todos os usuários e clientes são de teste.

## 2. Homologação funcional no navegador

Visitante desktop em 1440 px. Operador e demais perfis em 1440 px e 390 px.

| # | Cenário | Resultado | Evidência |
|---|---|---|---|
| 1 | Cliente inicia atendimento pelo site público e pede atendente | Aprovado: a recepcionista respondeu "Certo, vou transferir você para um atendente. Ele responderá por aqui." | `v-handoff-desktop.png` |
| 2 | IA registra a solicitação | Aprovado: a conversa foi encaminhada com o motivo "Cliente pediu atendimento humano" | `p-detail-desktop.png` |
| 3 | Solicitação aparece na fila | Aprovado: "Pendentes de identificação" e "Aguardando atendente" exibidos no painel | `p-detail-desktop.png` |
| 4 | Operador assume | Aprovado: botão "Assumir" troca para "Devolver à IA / Encerrar / Enviar" | `p-identified-desktop.png` |
| 5 | Cliente identificado | Aprovado: nome e telefone gravados (Maria Souza e Joana Lima) | `p-identified-desktop.png` |
| 6 | Conversão em solicitação e triagem | Aprovado: solicitações #1 e #2 criadas, situação "Solicitado" depois "Em triagem" | `p-triaged-desktop.png` |
| 7 | Agendamento | Aprovado: #1 agendado para 15/11 09:00 e #2 para 16/11 10:00 (horário local), com "Carlos Técnico" | `p-scheduled-desktop.png` |
| 8 | Execução | Aprovado: "Iniciar atendimento" → situação "Em andamento" | fluxo do script |
| 9 | Conclusão | Aprovado: situação "Concluído" nas duas solicitações | `p-completed-desktop.png`, `p-flow2-completed-desktop.png` |
| 10 | Histórico | Aprovado: histórico completo e sem duplicação (uma solicitação por cliente, um agendamento por solicitação) | `p-completed-desktop.png` |

Problema encontrado no cenário 7 e corrigido (ver seção 6): o histórico da solicitação #1 registrava "Agendado para 15/11/2026 12:00", enquanto a agenda mostrava 09:00. Após a correção, a solicitação #2 registra "Agendado para 16/11/2026 10:00", correto. A entrada antiga da solicitação #1 não foi reescrita, pois o histórico é imutável.

### Permissões por perfil

- Owner (`owner@homolog.test`): acessa o painel, edita o nome exibido e a recepcionista, e abre solicitações. **Aprovado.**
- Admin (`admin@homolog.test`): mesmo resultado do Owner. **Aprovado.**
- Operador (`operator@homolog.test`): vê o painel, assume conversas e conclui serviços. O campo "Nome exibido no atendimento" não é editável, e a tela mostra "Somente leitura: apenas proprietários e administradores alteram a recepcionista." **Aprovado.**

### Isolamento entre tenants

- Não homologado no navegador nesta execução: não foi criado um segundo tenant. Cobertura existente: `tests/Feature/Tenancy/TenantIsolationTest.php`, `tests/Feature/Operations/OperationalIsolationTest.php` e `tests/Feature/Operations/CustomerTest.php`, todos aprovados na suíte. **Pendente de homologação visual.**

### Tratamento de erros, responsividade, legibilidade e indicadores

- Responsividade: em 390 px, `/atendimento`, `/atendimento-ia`, `/solicitacoes/2` e `/dashboard` não têm rolagem horizontal (`scrollWidth` igual a `clientWidth`). Capturas: `v-mobile-390.png`, `m-*-390.png`.
- Dashboard após a primeira execução do fluxo: Concluídos (30 dias) = 1; Conversão (30 dias) = 100%; Solicitações abertas = 0. **Aprovado.** Após a segunda execução, a conferência do Dashboard **não foi refeita**: a base de homologação foi apagada por uma rodada da suíte (ver seção 8, item 10).
- Legibilidade e acessibilidade: não avaliadas com ferramenta específica (sem auditoria de contraste ou leitor de tela). **Não executado.**
- Tratamento de erros da interface: não testado com entradas inválidas nesta execução. **Não executado.**

Capturas de desktop e mobile estão na pasta de evidências do scratchpad da sessão; não foram versionadas no repositório.

## 3. Concorrência e integridade

| Teste | Resultado |
|---|---|
| Reservas simultâneas do mesmo profissional (`tests/Concurrency/parallel_booking.php`, processos separados, MySQL) | Aprovado em 3 de 3 execuções. Cenário A: 1 agendamento e 5 recusas. Cenário B (reenvio): 1 agendamento e 1 solicitação. |
| Suíte MySQL de concorrência (`MysqlConcurrencyTest`, com `VETORSMART_CONCURRENCY=1`) | Aprovado. Cobre: espera pelo lock, rollback sem rastro, bloqueio concorrente, serviço desativado e remarcação concorrente da mesma consulta. |
| Reenvio de solicitações | Aprovado: `AssistantTest` "repeated booking with the same key returns the same appointment" e o cenário B acima. |
| Falha de persistência com rollback | Aprovado: `AssistantTest` "a failure while writing the appointment rolls back the request". |
| Queda de conexão MySQL no meio da transação | Aprovado: conexão encerrada com `KILL` durante a transação em tabela descartável. Commit falhou com `PDOException`, 0 linhas gravadas, e nova conexão gravou normalmente. Script: `kill_probe.php` no scratchpad. |
| Ausência de registros parciais | Aprovado, pelo teste de queda acima e pelo rollback da suíte. |
| Cenários cruzados completos (agendamento × remarcação) | Parcial: agendamento × agendamento, agendamento × bloqueio e remarcação × remarcação estão cobertos. Agendamento × remarcação exclusivo não tem teste dedicado. |

Testes destrutivos foram executados somente no container isolado.

## 4. Qualidade do código

- **Suíte completa:** 367 de 367 aprovados, 2250 asserções, 2 avisos. Com MySQL e concorrência habilitada. **Aprovado.**
- **Pint:** aprovado.
- **TypeScript (`tsc --noEmit`):** aprovado.
- **PHPStan no escopo do gate (`phpstan.neon`: `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`, nível 7):** 88 erros, em arquivos não modificados nesta release (`RootAdmin/TenantController.php`, `Site/AssistantController.php`, `Tenant/AgendaController.php`, `Tenant/AssistantController.php`). **Reprovado, pré-existente.** Registrados separadamente, conforme o correio, sem supressão.
- **PHPStan em `AppointmentScheduler.php` e `tests/Concurrency`:** 0 erros. **Aprovado.**
- **Integração PHPStan × Pest, investigação dos 201 erros de `AssistantTest.php`:**
  - O `phpstan.neon` não inclui `tests/`. Esses erros só aparecem quando o arquivo é analisado explicitamente. Não afetam o gate `composer types:check`.
  - Com a extensão oficial do Pest (`vendor/pestphp/pest/extension.neon`), o número cai de 201 para 131.
  - Os 131 restantes são tipagem de auxiliares: `test()` retorna `TestCall`, que o PHPStan não associa aos métodos do `TestCase` (`withCookie`, `get`, `post`, propriedades criadas no `beforeEach`), mais parâmetros `array` sem tipo de valor.
  - Nenhuma alteração foi feita em `AssistantTest.php` nesta etapa, para não ampliar o escopo. Recomendação: incluir a extensão do Pest em `phpstan.neon` com `tests/` em paths separados, ou excluir `tests/` do gate, em uma tarefa própria.

## 5. Dependências e Git

- `composer.lock` **não integra a release funcional.** Contém 15 pacotes atualizados não relacionados ao atendimento (por exemplo `laravel/framework` 13.40.0 → 13.42.0, `guzzlehttp/guzzle` 8.3.1 → 8.5.0, `laravel/fortify` 1.41.0 → 1.42.0, `laravel/tinker` 3.0.2 → 3.1.0, `doctrine/deprecations` 1.1.6 → 1.2.0). Não foi revertido.
- Proposta de commits, **sem execução** (aguarda autorização):
  1. `feat(atendimento): concorrência transacional e horário local na triagem`: `app/Services/AppointmentScheduler.php`, `tests/Concurrency/` (`parallel_booking.php`, `worker.php`), `tests/Feature/Assistant/AssistantTest.php`, `docs/VETORSMART-BACKEND-05-ATENDIMENTO-IA.md`, `docs/VETORSMART-BACKEND-05.5.md`.
  2. `chore(deps): atualiza dependências do composer`: `composer.lock`, em commit separado e com aprovação própria.
  3. Não incluir: `correio.md` e `executed.md`, que são arquivos de controle de execução.
- Arquivos gerados ignorados pelo Git (`vendor/`, `node_modules/`, `public/build/`, `bootstrap/cache/`, `storage/framework/`) não entram na release.
- Não há arquivo `.env` versionado nem criado nesta execução. A `APP_KEY` usada nos testes foi gerada apenas no ambiente da sessão.

## 6. Correções realizadas

1. **Horário do histórico em fuso UTC** (`app/Services/AppointmentScheduler.php`, linha 75). A mensagem "Agendado para ..." formatava o horário em UTC, enquanto a agenda mostrava o horário local da empresa (09:00 local aparecia como 12:00). Correção: converter para `$tenant->timezone` antes de formatar. Verificado no navegador: a solicitação #2 agora mostra 10:00. A suíte completa continua com 367 de 367. Esta correção ainda não tem teste dedicado e não cobre entradas antigas do histórico.

## 7. Desempenho

Critérios definidos antes da execução: p95 ≤ 500 ms e taxa de erro 0% em 200 requisições com 5 conexões simultâneas à página pública do atendimento (`GET /atendimento`).

| Métrica | Resultado |
|---|---|
| Requisições | 200 completas, 0 falhas |
| Latência p50 / p95 / p99 | 138 ms / 172 ms / 193 ms |
| Média | 143 ms por requisição, 35 req/s |
| Taxa de erros | 0% |
| CPU e memória | Não medidos. Sem coletor de métricas instalado. |
| Tempo de criação de agendamento sob carga | Não medido. |
| Integridade dos dados após a carga | Não verificada além das suítes de integridade. |

Limitações: a carga usou `php artisan serve` (servidor embutido, um processo), que não representa produção. A rota de envio de mensagens tem limite de 30 requisições por minuto (`assistant-public`), e não foi incluída na carga. Portanto, o resultado não aprova capacidade de produção. Não houve carga de painel autenticado.

## 8. Pendências e riscos

1. **Gate de tipos reprovado** (`composer types:check`, 88 erros pré-existentes). Bloqueia a execução do `composer test` como um todo. Bloqueante.
2. **Isolamento entre tenants não homologado no navegador.** Coberto pela suíte. Pendente.
3. **`composer.lock`** com atualizações não relacionadas. Decisão pendente.
4. **Erros de tipagem nos testes do atendimento** (131 após a extensão do Pest). Não bloqueiam o gate atual, pois `tests/` não está nele.
5. **Histórico antigo com horário UTC** (solicitação #1 desta homologação). Dados de teste, sem efeito em produção.
6. **Legibilidade, acessibilidade e tratamento de erros da interface**: não avaliados.
7. **Cenário cruzado agendamento × remarcação**: sem teste dedicado.
8. **Carga**: apenas página pública, sem painel autenticado, sem CPU/memória, sem limite de envio de mensagens. Limitada ao servidor embutido do PHP.
9. **Servidor VetorSales (porta 8000)**: continua sem resposta. A causa não foi confirmada: o histórico do shell mostra `php artisan serve --host=192.168.0.54 --port=8000` em primeiro plano, e os logs do projeto não registram o período. Nenhum serviço foi reiniciado.
10. **Base de homologação apagada durante a execução.** A suíte (`RefreshDatabase`) foi rodada contra o mesmo banco `vetorsmart_test` usado pelo seed do navegador. Depois dessa rodada, os usuários e as solicitações de teste deixaram de existir. Todas as evidências de fluxo, permissões, correção e carga foram coletadas antes dessa rodada. Não repetido: conferência do Dashboard após a segunda execução. Para as próximas homologações, usar um banco dedicado ao navegador, separado do banco da suíte.

## 9. Recomendação

**Não liberar.** A funcionalidade do atendimento foi homologada no navegador de ponta a ponta, com concorrência real, queda de conexão e perfis. Para liberar, é necessário:

1. Resolver o gate PHPStan ou aprovar formalmente os 88 erros pré-existentes.
2. Homologar o isolamento entre tenants no navegador.
3. Decidir sobre `composer.lock`.
4. Autorizar os commits propostos na seção 5.
