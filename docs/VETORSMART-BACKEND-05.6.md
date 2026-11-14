# VETORSMART-BACKEND-05.6 — Relatório de estabilização técnica e liberação dos bloqueios

Data: 14/11/2026. Diretório: `gateway/vetorsmart/`.

Ambientes usados, todos isolados e descartáveis:
- Banco de testes automatizados: `vetorsmart_test` (MySQL 8.4.11, container `vs-test-mysql-0516`, porta só em `127.0.0.1:33107`).
- Banco de homologação do navegador: `vetorsmart_homolog`, no mesmo container, separado do banco de testes.
- Servidor de homologação: `127.0.0.1:8100` com `APP_ENV=local`, Chromium 1243 via Playwright.

**Parecer final: BLOQUEADO.** Os bloqueios técnicos da 05.5 foram resolvidos (PHPStan, isolamento, timezone, concorrência, dependências auditadas). A liberação continua bloqueada por um critério de desempenho definido antes da execução e não atendido: p95 das telas autenticadas sob 10 requisições simultâneas. A detalhe na seção 8.

## 1. Segurança multi-tenant

Dois tenants de homologação com usuários e dados próprios: "Clima Total" (empresa A, Owner, Admin e Operador) e "Pet Center" (empresa B, Owner, com um cliente e um pedido próprios).

### Evidências no navegador (empresa A logada, IDs da empresa B)

| Teste | Resultado |
|---|---|
| Operador da A abre `/solicitacoes/1` (pedido da B) | HTTP 404, página "Página não encontrada". Nenhum dado da B exibido. |
| Operador da A abre `/clientes/1/editar` (cliente da B) | HTTP 404. |
| Operador da A abre `/solicitacoes?tenant_id=2` | HTTP 200, lista da A. O pedido da B não aparece. A troca de tenant pelo parâmetro é ignorada. |
| Operador da A abre `/rootadmin` | HTTP 403, "Acesso negado — Sua conta não tem permissão para acessar esta área." |
| Visitante sem login abre `/solicitacoes` | Redireciona para `/login`. |
| Owner da B abre o próprio pedido | Vê os próprios dados. |

Capturas: `ux-404-cross-tenant.png` e `ux-cancelar-dialogo.png` na pasta de evidências do scratchpad (`/tmp/claude-1000/-home-anderson-projects-laravel-infra-abrasil-gateway-vetorsmart/7f96eb77-e5ef-4e61-b930-dcc2d4405947/scratchpad/`).

### Testes automatizados novos: `tests/Feature/Tenancy/CrossTenantWebTest.php` (15 testes)

Cobrem: 404 em pedido, cliente, agendamento e conversa de outra empresa (com o registro inalterado depois); lista sem dados de outra empresa; sessão apontando para outra empresa sem exposição; `tenant_id` manipulado na URL e no corpo ignorado; operador sem permissão recebendo 403; membership inativa sem acesso; API com 404 para cliente de outra empresa; usuário sem vínculo recusado; acesso anônimo sem dados.

Todos aprovados. Os testes existentes de isolamento (`TenantIsolationTest`, `OperationalIsolationTest`) continuam aprovados.

### Lacuna

O token de visitante usado num host de outra empresa não foi testado de forma explícita nesta execução. Critério de aprovação ("nenhuma exposição ou modificação cruzada") atendido nas rotas testadas, com esta lacuna registrada.

## 2. PHPStan

Gate oficial (`composer types:check` = `phpstan analyse`, nível 7, `phpstan.neon` inalterado): **88 erros → 0 erros.**

Causas encontradas, agrupadas:

1. **Tipos de modelo não declarados (maior parte dos erros).** Nos modelos, o PHPStan lia campos com cast de enum, `array` ou `datetime` como `string`. Corrigido com `@property` coerente com o cast em `AssistantConversation` (`state`, `status`, `collected`, `last_activity_at`), `Appointment` (`cancelled_at`) e `ServiceRequest` (`closed_at`). Nenhuma mudança de runtime.
2. **`?->` dentro de `??`.** Redundante: `??` já trata cadeia nula sem erro. Trocado por `->` em `TenantController`, `AgendaController`, `ServiceRequestController`, `AssistantConversations` e `Tenant/AssistantController`. Comportamento idêntico.
3. **Cookie do visitante tratado como `mixed`.** Problema real: um cookie enviado como array (`nome[]=...`) chegaria ao serviço. Corrigido com o helper `visitorToken()` em `Site/AssistantController`, que aceita apenas string.
4. **Listeners do Fortify.** Assumiam `payload->user` sempre presente. Agora verificam `instanceof User` antes de gravar a auditoria.
5. **Checagens mortas em regex.** `$match[n] !== ''` em grupos que não podem ser vazios, em `AssistantText`. Removidas.
6. **Formas de retorno.** `CustomerController::history()` (shape do paginador), `messagesAfter()` e `statusOptions()` com listas explícitas.
7. **Contexto do provedor de IA** (`AssistantFlow::classifyWithProvider`): genérico e lista declarados.

O que **não** foi feito: nenhum `@phpstan-ignore`, baseline, supressão, redução de nível ou alargamento de tipo.

Fora do gate: `tests/Feature/Assistant/AssistantTest.php` gera erros de tipagem dos closures do Pest. Com a extensão oficial do Pest, o número cai para 131 (de 201). Não entra no `phpstan.neon`, então não afeta o gate. Fica registrado como dívida, sem alteração no arquivo de teste.

## 3. Isolamento dos ambientes de teste

- **`vetorsmart_test`:** única base da suíte automatizada e da concorrência real.
- **`vetorsmart_homolog`:** única base da homologação de navegador. A suíte não toca nela.

Guards em `app/Providers/AppServiceProvider.php` (método `guardDatabaseSafety`):
- Com `APP_ENV=testing` e MySQL, o boot recusa qualquer banco cujo nome não termine em `_test`. Isso impede que `RefreshDatabase` apague dados de outro banco.
- Os comandos `migrate:fresh`, `migrate:refresh`, `migrate:reset` e `db:wipe` são recusados em MySQL cujo banco não termine em `_test`.

Verificado: `php artisan test` com `DB_DATABASE=vetorsmart_homolog` foi recusado com a mensagem de segurança, e `migrate:fresh` nesse banco também foi recusado.

Incidente desta execução: na rodada anterior, a suíte rodou contra o mesmo banco do seed do navegador e apagou os dados de homologação. Corrigido pela separação acima. Todas as evidências desta seção foram coletadas depois.

## 4. Concorrência e consistência

Novos testes reais em `tests/Feature/Operations/MysqlConcurrencyTest.php`, com sessões MySQL separadas (cenários que disputam o mesmo profissional e horário):

| Cenário | Resultado |
|---|---|
| Agendamento novo × remarcação do mesmo horário | Remarcação com lock aberto: o agendamento novo espera e falha por timeout de lock. Após o commit, o agendamento novo é recusado. Um único agendamento no horário. |
| Dois operadores remarcando o mesmo profissional para o mesmo horário | O segundo espera pelo lock e falha. Depois do commit, é recusado. Nenhuma dupla reserva. |
| Reenvio duplicado durante remarcação | Enquanto a remarcação está aberta, o reenvio espera. Depois, repetir a remarcação para o mesmo horário é idempotente: um agendamento, no horário novo. |
| Queda de conexão dentro da transação real de agendamento | A conexão é encerrada com `KILL CONNECTION` logo após o histórico ser gravado, dentro da transação. A transação falha; nenhum agendamento, nenhum evento "agendado" e a solicitação continua triada. Depois, uma nova conexão agenda normalmente (um agendamento). |

Os 5 testes anteriores do arquivo continuam aprovados. Arquivo: 9 de 9.

Execuções de concorrência por processos separados (`tests/Concurrency/parallel_booking.php`): 3 de 3 aprovadas. Cenário A: 1 agendamento e 5 recusas. Cenário B (reenvio): 1 agendamento e 1 solicitação.

Queda de MySQL em tabela descartável, sem o fluxo de agendamento: commit com `PDOException`, 0 linhas gravadas e recuperação em nova conexão. Esse teste foi feito na 05.5 e não foi repetido.

Integridade após rollback: os testes acima verificam solicitação, agendamento e histórico juntos, depois da falha.

## 5. Timezone

Novo `tests/Feature/Operations/TimezoneTest.php` (13 testes), com dados parametrizados:

- **Armazenamento em UTC e exibição no fuso da empresa:** Manaus (UTC-4), São Paulo (UTC-3), Nova York em julho (UTC-4, verão), Nova York em janeiro (UTC-5, inverno) e Lisboa em julho (UTC+1, verão). Em todos os casos, o valor bruto no banco é o UTC esperado e a exibição volta o horário local escolhido.
- **Histórico:** a mensagem "Agendado para dd/mm/aaaa HH:MM." mostra o horário local. Caso de 22:00 em Manaus, que cai no dia seguinte em UTC: o histórico mostra a data local.
- **Remarcação:** grava em UTC e exibe o horário local novo.
- **Dois tenants em fusos diferentes:** não interferem entre si.

Histórico antigo: não foi reescrito. A correção da 05.5 (horário local no histórico) continua válida só para agendamentos feitos depois dela.

## 6. Auditoria do `composer.lock`

Os 15 pacotes alterados em relação ao `HEAD`:

| Pacote | Versão anterior → atual |
|---|---|
| laravel/framework | v13.40.0 → v13.42.0 |
| guzzlehttp/guzzle | 8.3.1 → 8.5.0 |
| guzzlehttp/psr7 | 3.2.0 → 3.4.0 |
| laravel/fortify | v1.41.0 → v1.42.0 |
| laravel/tinker | v3.0.2 → v3.1.0 |
| laravel/mcp | v1.1.2 → v1.2.0 |
| laravel/pail | v1.2.7 → v1.3.0 |
| laravel/sail | v1.69.0 → v1.70.0 |
| laravel/pint | v1.32.1 → v1.32.3 |
| laravel/serializable-closure | v2.1.0 → v2.2.0 |
| larastan/larastan | v3.14.0 → v3.15.0 |
| brianium/paratest | v7.27.5 → v7.28.0 |
| doctrine/deprecations | 1.1.6 → 1.2.0 |
| filp/whoops | 2.19.2 → 2.19.3 |
| nunomaduro/collision | v8.9.5 → v8.9.6 |

Verificações:
- **Vulnerabilidades:** `composer audit` → "No security vulnerability advisories found."
- **Compatibilidade de PHP:** o ambiente é PHP 8.4.27 e `composer check-platform-reqs` passou. Os requisitos de PHP dos pacotes alterados são iguais aos anteriores, com uma exceção: `doctrine/deprecations` deixou de aceitar PHP 7.1–7.3 (agora `^7.4 || ^8.0`), sem efeito para o projeto, que exige PHP `^8.3`.
- **Compatibilidade com Laravel:** os requisitos de Laravel dos pacotes são os mesmos. Os 401 testes passaram com essas versões.
- **Instalado × lockfile:** o `vendor/` instalado coincide com o `composer.lock` atual nas 154 entradas. Nenhuma das 15 versões antigas está instalada.

Decisão proposta: **preservar as atualizações em commit próprio (commit 3), sujeito a aprovação.** Motivos: suíte, PHPStan, TypeScript e concorrência foram validados com essas versões, e a auditoria não encontrou vulnerabilidades. Restaurar o lockfile antigo exigiria reinstalar o `vendor/` com versões antigas e repetir toda a validação. Os commits funcionais (1 e 2) foram validados com as versões novas. Se o commit 3 for recusado, a suíte precisa ser rodada de novo antes da liberação.

## 7. Experiência do usuário

Homologado no navegador (desktop 1440 px e mobile 390 px):

| Item | Resultado |
|---|---|
| Mensagens de erro de login | Estavam em inglês ("These credentials do not match our records."). **Corrigido:** `lang/pt_BR/auth.php`. Agora: "Essas credenciais não correspondem aos nossos registros." |
| Nomes de campo em erros de validação | Apareciam crus ("O campo service id é obrigatório."). **Corrigido:** nomes em `lang/pt_BR/validation.php` ("O campo serviço é obrigatório."). |
| Ações destrutivas | "Cancelar solicitação" e "Registrar como não realizado" (solicitação) e "Encerrar" (conversa do atendimento IA) executavam **sem confirmação**. A solicitação de teste foi cancelada com um clique. **Corrigido:** as três passam a usar o diálogo de confirmação já existente no projeto (`ConfirmAction`). Verificado: o diálogo abre; Esc não altera a situação ("Solicitado"); confirmar cancela ("Cancelado"). |
| Foco por teclado | Tab percorre a navegação com foco visível (anel de 3 px e borda azul). |
| Rótulos | Nenhum campo sem rótulo na triagem. Botões com texto. O checkbox "Lembrar acesso" é um componente Radix com rótulo associado. |
| Permissões | Owner e Admin editam a recepcionista; Operador tem somente leitura, com a mensagem "Somente leitura: apenas proprietários e administradores alteram a recepcionista." |
| Mensagens de acesso negado | 403 e 404 com texto em português e saída para a área do usuário. |
| Responsividade | Sem rolagem horizontal em 390 px em `/login`, `/dashboard`, `/solicitacoes`, `/solicitacoes/1`, `/clientes` e `/agenda`. |

Pendências desta frente (não corrigidas):
- **Envio vazio do login:** não aparece mensagem na própria tela. A validação é só a nativa do HTML5 (tooltip do navegador), sem `aria-invalid`. Corrigir exige mudança no formulário.
- **Feedback durante operações demoradas:** não medido. Não há verificação de estado de carregamento nos botões.
- **Contraste do anel de foco:** o anel tem 30% de opacidade sobre fundo branco. Não medido contra WCAG.
- **Legibilidade visual:** avaliada por capturas, sem auditoria formal.
- **Formatação do front:** `vp check` reprova formatação em 80 arquivos, inclusive vários não tocados. Não reformatei: `vp fmt` nos dois arquivos alterados reescreveu 891 linhas de 26. Dívida anterior, fora do escopo.

## 8. Desempenho

Critérios definidos antes da execução, por operação autenticada: **p95 ≤ 500 ms; p99 ≤ 1000 ms; falhas de conexão = 0; respostas fora de 2xx = 0.** Operações: Dashboard, Fila de atendimento (`/atendimento-ia`), Lista de solicitações (`/solicitacoes`), Agenda e Histórico do cliente (`/clientes/{id}/editar`).

Resultados válidos (300 requisições por operação, 10 conexões simultâneas):

**Rodada A** (`php artisan serve` sem `PHP_CLI_SERVER_WORKERS`, 1 sessão):

| Operação | p50 | p95 | p99 | Falhas |
|---|---|---|---|---|
| Dashboard | 482 | 631 | 689 | 0 |
| Fila de atendimento | 486 | 550 | 611 | 0 |
| Solicitações | 491 | 565 | 687 | 0 |
| Agenda | 508 | 620 | 679 | 0 |
| Histórico do cliente | 569 | 646 | 661 | 0 |

**Rodada B** (`php artisan serve`, 8 workers, 10 sessões distintas, gerador em Python):

| Operação | Vazão | p50 | p95 | p99 | Falhas |
|---|---|---|---|---|---|
| Dashboard | 12,1 req/s | 743 | 1156 | 1511 | 0 |
| Fila de atendimento | 13,6 req/s | 720 | 861 | 908 | 0 |
| Solicitações | 11,2 req/s | 729 | 2212 | 2661 | 0 |
| Agenda | 13,3 req/s | 737 | 834 | 895 | 0 |
| Histórico do cliente | 10,3 req/s | 859 | 1850 | 2419 | 0 |

**Referência, 1 conexão** (8 workers, 1 sessão, 100 requisições): Dashboard p50 68 / p95 128 / p99 232 ms; Agenda p50 63 / p95 85 / p99 122 ms.

Leitura: uma requisição isolada custa cerca de 65–70 ms. Com concorrência, a vazão não passa de 12–20 req/s e a latência cresce com a fila, com ou sem sessões distintas. Isso indica que o gargalo está na topologia de teste (servidor de desenvolvimento, um host, MySQL local), não no custo das telas. A rodada B usou 8 workers e foi pior que a A, o que sugere contenção adicional neste host. Não foi investigada.

Recursos (amostragem de `ps` a cada 1 s, soma dos processos PHP): RSS entre 276 e 284 MB; CPU de pico de 65%, com 4 núcleos.

Resultado: **critério de p95 não atendido em todas as operações** (nas duas rodadas). Todos os critérios de erro atendidos. Não há base para aprovar capacidade de produção. Para aprovar, é preciso repetir a carga em uma stack equivalente à de produção (php-fpm com servidor web, banco em host separado, concorrência por usuário distinto), em ambiente isolado.

Carga de painel autenticado foi feita com 10 usuários de teste da empresa A (`carga1` a `carga10`, senha de homologação) para evitar o limite de login de 5 tentativas por minuto por e-mail e IP.

Nota: uma primeira rodada com 8 workers foi invalidada (todas as respostas viraram 302 para o login) porque a `APP_KEY` mudou entre as execuções e os cookies antigos deixaram de valer. Não entra nos números acima.

## 9. Controle de release

Arquivos modificados e não rastreados, revisados:

- `app/` (controllers, models, services, providers): tipagem, auditoria, isolamento de cookie, guard de banco, correção de timezone do histórico (05.5).
- `lang/pt_BR/auth.php` (novo), `lang/pt_BR/validation.php`: mensagens em português.
- `resources/js/pages/tenant/requests/show.tsx`, `resources/js/pages/tenant/assistant/index.tsx`: confirmações de ações destrutivas.
- `tests/Concurrency/` (novo), `tests/Feature/Operations/MysqlConcurrencyTest.php`, `tests/Feature/Operations/TimezoneTest.php` (novo), `tests/Feature/Tenancy/CrossTenantWebTest.php` (novo), `tests/Feature/Localization/` (novo), `tests/Feature/Assistant/AssistantTest.php` (alteração anterior à 05.6, não feita nesta execução; ver risco 8).
- `docs/VETORSMART-BACKEND-05-ATENDIMENTO-IA.md` (alteração anterior), `docs/VETORSMART-BACKEND-05.5.md` (novo), `docs/VETORSMART-BACKEND-05.6.md` (este relatório).
- `composer.lock`: ver seção 6.
- `correio.md` e `executed.md`: arquivos de controle, fora de qualquer commit funcional.

Ignorados pelo Git e não entram na release: `public/build/` (recompilado com `npm run build`), `public/hot` (arquivo de dev server antigo, removido porque fazia o `local` carregar assets de uma porta fechada), `vendor/`, `node_modules/`, `storage/framework/`, `bootstrap/cache/`.

Proposta de commits, **sem execução** (aguarda autorização):

1. **`feat(atendimento): concorrência, timezone e isolamento entre empresas`**: `app/Services/AppointmentScheduler.php`, `app/Http/Controllers/Site/AssistantController.php` (token do visitante), `app/Providers/AppServiceProvider.php` (guard de banco), `tests/Concurrency/`, `tests/Feature/Operations/MysqlConcurrencyTest.php`, `tests/Feature/Operations/TimezoneTest.php`, `tests/Feature/Tenancy/CrossTenantWebTest.php`, `tests/Feature/Assistant/AssistantTest.php`, `docs/VETORSMART-BACKEND-05-ATENDIMENTO-IA.md`, `docs/VETORSMART-BACKEND-05.5.md`, `docs/VETORSMART-BACKEND-05.6.md`.
2. **`fix(qualidade): tipagem do PHPStan, mensagens em português e confirmação de ações destrutivas`**: models com `@property`, controllers e services com ajustes de tipo, `app/Providers/FortifyServiceProvider.php`, `lang/pt_BR/*`, `resources/js/pages/tenant/requests/show.tsx`, `resources/js/pages/tenant/assistant/index.tsx`, `tests/Feature/Localization/`.
3. **`chore(deps): atualiza dependências do composer`**: somente `composer.lock`, após decisão específica.

Os arquivos dos commits 1 e 2 estão divididos por responsabilidade, mas alguns arquivos (por exemplo, `Services/Assistant/AssistantConversations.php`) têm mudança de tipo e de comportamento juntas. A separação exata deve ser conferida com `git add -p` antes do commit.

## 10. Riscos residuais e parecer

Riscos:
1. **Desempenho autenticado não atendido** no critério de p95 sob concorrência (seção 8). Bloqueante para liberação.
2. **Lacuna no teste do token de visitante entre empresas** (seção 1).
3. **131 erros de tipagem em `AssistantTest.php`** fora do gate (seção 2).
4. **Envio vazio do login sem mensagem na tela**, feedback de carregamento não medido, contraste do foco não medido (seção 7).
5. **`composer.lock`** com 15 pacotes atualizados: decisão pendente (seção 6).
6. **Histórico antigo com horário UTC** de agendamentos feitos antes da correção de 05.5: não reescrito, por política do correio.
7. **Dependência de ambiente** para a verificação de concorrência: exige MySQL com `innodb_lock_wait_timeout` e permissão de `KILL`, só em banco descartável.
8. **Expectativa alterada em teste de falha** (`AssistantTest.php`, cenário de falha de gravação do agendamento): o teste passou a afirmar que a solicitação de triagem (handoff) permanece, triada, quando o agendamento falha. O comportamento é coerente com a intenção de não perder o atendimento, mas é uma regra de produto. Confirmar antes do commit 1.

Parecer de liberação: **BLOQUEADO.**

Condições para liberar:
1. Repetir a carga autenticada em stack equivalente à de produção, com p95 ≤ 500 ms nas cinco operações.
2. Testar explicitamente o token de visitante entre empresas.
3. Autorizar os commits propostos (seção 9) e a decisão sobre `composer.lock`.

Não foram feitos: deploy, commit, alteração do VetorSales, VetorOS, VetorLeads ou de infraestrutura Docker compartilhada.
