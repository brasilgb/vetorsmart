# VETORSMART-BACKEND-05.7 — Desempenho realista e preparação final da release

Data: 14/11/2026. Diretório: `gateway/vetorsmart/`.

**Parecer final: APROVADO PARA PREPARAR OS COMMITS, sujeito à autorização explícita.** Desempenho autenticado atende o critério (p95 ≤ 500 ms com 10 usuários simultâneos, sem erros, nas cinco rotas). Isolamento de token, regra de falha do agendamento e guard de banco têm testes automatizados passando. Suíte completa: 434 de 434. Nada foi commitado nem implantado.

## 1. Arquitetura da medição

Ambiente isolado, montado nesta execução com containers próprios, sem tocar na infraestrutura compartilhada dos outros SaaS:

| Componente | Configuração |
|---|---|
| Host | 4 vCPU, 16 GB RAM, mesmo host para todos os componentes (sem separação de rede) |
| HTTP | `nginx:1.27-alpine`, 2 workers, FastCGI para o PHP-FPM, logs com `rt` e `urt` |
| PHP | `php:8.4-fpm-alpine` com `pdo_mysql` e OPcache (`validate_timestamps=0`, 192 MB) |
| PHP-FPM | `pm = dynamic`, `max_children = 16`, `start_servers = 8`, `min/max spare = 4/12`, `max_requests = 2000`, status em `/fpm-status` |
| Laravel | `APP_ENV=production`, `APP_DEBUG=false`, caches de configuração, rotas, eventos e views gerados; sessão e cache em banco |
| MySQL | `mysql:8.4`, `innodb_buffer_pool_size=512M`, `max_connections=200`, slow log com `long_query_time=0.05` |
| Conta MySQL | usuário de aplicação com privilégios só no banco de desempenho (não root) |
| Assets | `public/build` compilado com `npm run build` |
| Carga | gerador Python com 10 usuários distintos (`carga1` a `carga10`), cada um com sessão própria, login feito uma vez |

Diferenças conhecidas em relação à produção, registradas para não serem omitidas:
- **Sem TLS.** O ambiente roda em HTTP puro, então `SESSION_SECURE_COOKIE=false` foi definido. Em produção, o TLS termina antes do nginx.
- **Mesmo host para todos os componentes**, com CPU compartilhada entre gerador de carga, PHP, nginx e MySQL. Isso tende a piorar a latência medida.
- **Dataset no limite de capacidade**: 6 profissionais, consultas de 1 h, ocupação quase total (cerca de 60 agendamentos por dia). É um cenário pesado de propósito.
- Dados: 3000 solicitações, 11 300 eventos de histórico, 2000 agendamentos, 1500 clientes, 300 conversas de atendimento. Volume de seed reprodutível.

Os caches de produção ficaram num volume Docker próprio (`APP_CONFIG_CACHE` e similares), para não tocar em `bootstrap/cache` do repositório (ver seção 9, riscos).

## 2. Métricas por endpoint

Cinco endpoints autenticados, 300 requisições por combinação, 1, 5 e 10 usuários simultâneos. Latências em milissegundos, medidas no cliente. `php_p95` é o tempo dentro do PHP-FPM, medido pelo nginx (`urt`).

| Operação | Usuários | Vazão (req/s) | p50 | p95 | p99 | Erros | PHP p95 |
|---|---|---|---|---|---|---|---|
| Dashboard | 1 | 27,2 | 35 | 43 | 52 | 0 | 43 |
| Dashboard | 5 | 85,7 | 55 | 86 | 120 | 0 | 85 |
| Dashboard | **10** | 88,5 | 110 | **161** | 196 | 0 | 159 |
| Fila de atendimento | 1 | 20,4 | 47 | 57 | 68 | 0 | 56 |
| Fila de atendimento | 5 | 66,2 | 69 | 108 | 123 | 0 | 105 |
| Fila de atendimento | **10** | 68,1 | 144 | **215** | 242 | 0 | 214 |
| Solicitações | 1 | 36,1 | 27 | 31 | 36 | 0 | 30 |
| Solicitações | 5 | 115,3 | 41 | 62 | 70 | 0 | 59 |
| Solicitações | **10** | 120,0 | 82 | **115** | 128 | 0 | 112 |
| Agenda | 1 | 15,6 | 63 | 70 | 85 | 0 | 68 |
| Agenda | 5 | 52,3 | 91 | 130 | 156 | 0 | 129 |
| Agenda | **10** | 52,8 | 181 | **272** | 360 | 0 | 269 |
| Histórico do cliente | 1 | 26,7 | 36 | 43 | 61 | 0 | 41 |
| Histórico do cliente | 5 | 85,1 | 53 | 84 | 134 | 0 | 82 |
| Histórico do cliente | **10** | 89,4 | 109 | **165** | 189 | 0 | 163 |

Critério: p95 ≤ 500 ms com 10 usuários, sem erros. **Atendido nas cinco rotas.** Nenhuma resposta fora de 2xx em nenhuma das 4 500 requisições.

Recursos (amostragem a cada 2 s durante a janela de carga, 228 amostras): CPU dos processos PHP com pico de 327% (4 núcleos) e média de 83%; CPU do MySQL com pico de 132% e média de 17%; conexões MySQL com pico de 11 e média de 2,7; workers PHP-FPM ativos com pico de 11 de 16 configurados. Memória estável: container PHP em cerca de 71–80 MB, MySQL em cerca de 537–552 MB. Esses valores são de todo o período de amostragem, que inclui as rodadas de diagnóstico.

## 3. Diagnóstico de gargalos

Primeira rodada completa (linha de base, antes das correções):

| Operação | p95 com 10 usuários | Observação |
|---|---|---|
| Dashboard | 251 | ok |
| Fila de atendimento | 448 | próximo do limite |
| Solicitações | 245 | ok |
| **Agenda** | **1205** | **acima do critério** |
| Histórico do cliente | 178 | ok |

**Origem medida da latência da agenda, não atribuída por suposição:**

1. **O servidor de desenvolvimento não era o problema.** O servidor usado nesta execução foi o nginx com PHP-FPM. O tempo `urt` (dentro do PHP) acompanhou o tempo do cliente, então a fila de rede e o nginx não explicam a diferença.
2. **O MySQL também não era o problema.** O log geral de 20 requisições da agenda mostrou cerca de 14 instruções por requisição, quase todas pequenas (autenticação, sessão, empresa, serviços, clientes, profissionais, agendamentos), cada uma na faixa de décimos de milissegundo. A consulta principal dos agendamentos só foi reconhecida como lenta ao somar o custo por linha.
3. **O custo cresce com o número de linhas do período.** Medido com um usuário: visão diária (63 ms, 37 KB), semanal (170 ms, 138 KB, cerca de 480 agendamentos), mensal (569 ms, 503 KB).
4. **A causa é a hidratação Eloquent por linha.** A listagem carregava cada agendamento como modelo e, para cada um, duas relações aninhadas (solicitação → cliente e solicitação → serviço), além do profissional. Isso são cerca de 1500 modelos para 480 agendamentos. A conversão de horário era feita quatro vezes por linha.

## 4. Correções aplicadas (pontuais)

Única alteração de desempenho: `app/Http/Controllers/Tenant/AgendaController.php`, método `index`.

- A listagem de agendamentos passou a ser uma consulta de leitura com junções (`appointments` × `service_requests` × `professionals` × `customers` × `services`), que devolve os nomes prontos. Sem hidratação de modelos nem de relações por linha.
- O escopo de empresa foi declarado explicitamente na consulta (`tenant_id`), porque essa leitura não passa pelo scope do modelo.
- A conversão para o fuso da empresa é feita uma vez por linha para início e fim.
- **Contrato da resposta preservado**: as mesmas chaves e os mesmos valores (`id`, `request_id`, `date`, `time`, `ends`, `professional`, `customer`, `service`, `status`, `status_label`, `can_change`, `starts_at`).

Efeito medido, mesma carga (10 usuários, 300 requisições):

| Métrica | Antes | Depois |
|---|---|---|
| p50 | 633 ms | 181 ms |
| p95 | 1205 ms | 272 ms |
| p99 | 1647 ms | 360 ms |
| Vazão | 13,8 req/s | 52,8 req/s |

Testes repetidos após a correção: rodada final completa (seção 2), que confirma o resultado. Nota de variância: a rodada final também melhorou dashboard, fila e histórico, que não foram alterados. Isso é consistente com o reinício do PHP-FPM e com a carga mais estável dessa rodada, e não é atribuído ao código dessas rotas.

Não foi necessário ampliar infraestrutura. Não foram alterados índices nem configuração do MySQL.

## 5. Segurança dos tokens de visitante

Testes automatizados em `tests/Feature/Assistant/VisitorTokenSecurityTest.php`, todos HTTP, no host real de cada empresa, com a cookie criptografada como no navegador. **11 de 11 aprovados.** Cobertura:

- **Token gerado na empresa A, usado na B:** leitura não retorna nada da A; envio não cria conversa na B nem grava mensagem (verificado com contagens de banco).
- **Cookie adulterada:** sem acesso à conversa.
- **Token inválido, cifrado corretamente:** não encontra conversa.
- **Token ausente:** não lê e não grava mensagem.
- **Conversa vencida (`retention_until` no passado):** o token não a acessa, e o `start` cria conversa nova sem reabrir a antiga. **Esta brecha existia e foi corrigida**: `findByToken` e a busca de conversa existente no `start` não conferiam a data de retenção. A purga periódica removia a conversa, mas até lá ela era acessível.
- **Dois visitantes na mesma empresa:** um não lê a conversa do outro.
- **Identificadores forjados no corpo** (`conversation_id`, `tenant_id`, `session_hash`): ignorados; só o token seleciona a conversa.
- **Cookie em forma de array** (`vs_assistant[]=...`): ignorada, não é repassada como token.
- **Conversa encerrada:** não aceita mensagens.

Critério "nenhum token permite acesso cruzado": atendido nos cenários testados. O token é aleatório de 48 caracteres, guardado só como hash SHA-256 e comparado com escopo de empresa na consulta.

## 6. Regra de falha do agendamento

Regra formalizada: **quando o agendamento falha, a solicitação de triagem é preservada.** Implementação e testes em `tests/Feature/Assistant/BookingFailureRuleTest.php`. **6 de 6 aprovados.**

O que já existia e foi confirmado: quando o cliente está identificado, a falha no agendamento passa por `transfer()`, que registra a solicitação triada depois do rollback da transação de agendamento. Assim a solicitação sobrevive e o operador a recebe.

O que foi acrescentado:
- **Referência de diagnóstico**: cada falha gera uma referência de 8 caracteres. Ela aparece no motivo do atendimento visto pelo operador (`Falha técnica ao concluir o agendamento (ref. XXXXXXXX). A solicitação foi preservada na triagem; nenhum horário foi reservado.`) e no log `assistant.booking_failed`, com o mesmo valor.
- **Log sem dado sensível**: o contexto contém `tenant_id`, `conversation_id`, `reference` e a classe da exceção. Não contém mensagem, SQL nem bindings. Verificado pelo teste.

Verificado pelos testes:
- A solicitação fica triada, com serviço e origem IA, em estado recuperável.
- Nenhum agendamento é confirmado ao visitante nem gravado; a mensagem não contém SQL nem nomes de tabela.
- O histórico da solicitação mostra o resultado real: contém `triaged` e nunca `scheduled`.
- O operador pode tentar de novo com o serviço real: um agendamento. Uma segunda tentativa na mesma solicitação é recusada: nenhuma duplicação.
- Um reenvio da confirmação pelo visitante depois da falha não cria agendamento.

## 7. Guard de banco

Revisado em `AppServiceProvider::guardDatabaseSafety`, que agora delega a `App\Support\DatabaseGuard::refusal` — função pura, sem acesso a banco, testada em `tests/Unit/DatabaseGuardTest.php` (aprovada).

Antes, a proteção dependia quase só do sufixo `_test`. Agora uma operação de teste ou destrutiva só passa se **todas** as condições forem verdadeiras:

1. **Ambiente**: `APP_ENV` diferente de `production`.
2. **Conexão**: MySQL com host de loopback (`127.0.0.1`, `localhost` ou `::1`). Host remoto é recusado, mesmo com o nome correto. SQLite só é aceito fora de produção. Qualquer outro driver é recusado.
3. **Nome**: termina em `_test`.
4. **Confirmação explícita**: a configuração `VETORSMART_TEST_DATABASE` (testes) ou `VETORSMART_DESTRUCTIVE_DATABASE` (comandos destrutivos) repete exatamente o nome do banco. O nome sozinho não basta.

Comandos cobertos: `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`.

Homologação legítima continua permitida: o banco `vetorsmart_homolog` em `APP_ENV=local`, com uso comum (não-destrutivo), não é bloqueado. Está coberto por teste.

`phpunit.xml` passou a declarar `VETORSMART_TEST_DATABASE=vetorsmart_test`. Como o PHPUnit não sobrescreve variáveis já definidas no shell, a proteção continua valendo se alguém apontar o teste para outro banco pelo ambiente.

Permissões do MySQL: o usuário de aplicação usado na carga tem privilégios só no banco de desempenho, sem acesso a outros bancos. Como o guard não consegue checar permissões no servidor, essa parte depende da configuração de cada ambiente, e fica como recomendação (seção 9).

## 8. Revisão dos commits

Três commits propostos, **nenhum executado**. Não há autorização explícita do usuário para commit.

**Commit 1 — Funcional** (atendimento, concorrência, timezone, segurança multi-tenant, desempenho da agenda):
- `app/Services/AppointmentScheduler.php` (horário local no histórico, 05.5)
- `app/Services/Assistant/AssistantFlow.php` (referência de falha) e `app/Services/Assistant/AssistantConversations.php` (retenção no token)
- `app/Http/Controllers/Site/AssistantController.php` (token só como string)
- `app/Http/Controllers/Tenant/AgendaController.php` (desempenho)
- `app/Support/DatabaseGuard.php`, `app/Providers/AppServiceProvider.php`, `config/platform.php`, `phpunit.xml`
- `tests/Concurrency/`, `tests/Feature/Operations/MysqlConcurrencyTest.php`, `tests/Feature/Operations/TimezoneTest.php`, `tests/Feature/Tenancy/CrossTenantWebTest.php`, `tests/Feature/Assistant/VisitorTokenSecurityTest.php`, `tests/Feature/Assistant/BookingFailureRuleTest.php`, `tests/Support/VisitorSite.php`, `tests/Unit/DatabaseGuardTest.php`
- `tests/Feature/Assistant/AssistantTest.php` (expectativa de falha, pré-existente — ver risco 2)
- Documentos: `docs/VETORSMART-BACKEND-05-ATENDIMENTO-IA.md`, `docs/VETORSMART-BACKEND-05.5.md`, `docs/VETORSMART-BACKEND-05.6.md`, `docs/VETORSMART-BACKEND-05.7.md`

**Commit 2 — Qualidade** (PHPStan, localização, UX, confirmações):
- Models com `@property`: `app/Models/Appointment.php`, `app/Models/AssistantConversation.php`, `app/Models/ServiceRequest.php`
- Controllers com ajuste de tipo: `RootAdmin/TenantController.php`, `Tenant/AssistantController.php`, `Tenant/CustomerController.php`, `Tenant/ServiceRequestController.php`
- Services com ajuste de tipo: `app/Services/Assistant/AssistantText.php`
- `app/Providers/FortifyServiceProvider.php`
- `lang/pt_BR/auth.php` (novo), `lang/pt_BR/validation.php`
- `resources/js/pages/tenant/requests/show.tsx`, `resources/js/pages/tenant/assistant/index.tsx` (confirmações)

**Commit 3 — Dependências**: somente `composer.lock`, preservando as atualizações já auditadas (seção 6 do relatório 05.6). Sem nova atualização.

**Arquivos com responsabilidades misturadas** (exigem `git add -p` com cuidado):
- `app/Services/Assistant/AssistantFlow.php`: a referência de falha é do commit 1; ajustes de tipo que aparecem no mesmo arquivo não existem nele, mas conferir.
- `app/Services/Assistant/AssistantConversations.php`: retenção no token (commit 1) e ajustes de tipo (commit 2) no mesmo arquivo.
- `app/Http/Controllers/Tenant/AssistantController.php`: ajustes de tipo apenas (commit 2), mas o mesmo arquivo tem alterações anteriores de outras execuções — revisar.
- `tests/Feature/Assistant/AssistantTest.php`: pré-existente, veja risco 2.

**Validação dos três juntos:** a suíte foi rodada com todos os arquivos juntos, que é o estado equivalente aos três commits somados. Não foi validado commit a commit com staging seletivo; isso precisa ser feito antes do commit, com a suíte rodada em cada etapa.

**Arquivos fora de commit**: `correio.md` e `executed.md` (controle de execução), e artefatos ignorados pelo Git (`public/build/`, `vendor/`, `node_modules/`, `storage/`).

## 9. Riscos residuais

1. **Caches de produção criados como root em `bootstrap/cache/` do repositório** (`config.php`, `routes-v7.php`, `events.php`), gerados durante esta execução por um comando `php artisan config:cache` rodado num container. **Eles não foram removidos**: a remoção foi bloqueada pela verificação de segurança e não foi contornada. Enquanto existirem, **qualquer `php artisan` no host lê a configuração de produção gerada (banco de desempenho)**, inclusive `php artisan test`. Ação necessária do usuário: remover esses arquivos (`bootstrap/cache/config.php`, `bootstrap/cache/routes-v7.php`, `bootstrap/cache/events.php`) ou rodar `php artisan optimize:clear` a partir do host, e confirmar com `php artisan config:show database.connections.mysql.database`.
2. **Expectativa alterada em `AssistantTest.php`** (cenário de falha de gravação): o teste afirma que a solicitação de triagem permanece, o que agora está formalizado como regra de produto (seção 6). Confirmar que a regra é intencional antes do commit 1.
3. **Ambiente de desempenho não é produção**: sem TLS, mesmo host, dataset no limite de ocupação. Os números servem como evidência de que o código atende o critério sob essas condições, não como garantia de capacidade em produção. Repetir em infraestrutura de produção antes de declarar capacidade.
4. **Variância entre rodadas**: a linha de base e a rodada final divergiram em rotas que não mudaram (dashboard, fila, histórico). Os números finais são de uma rodada única; a margem de dashboard, fila e histórico continua alta mas não extrema (fila: 215 ms de p95 com 10 usuários).
5. **Login limitado a 5 tentativas por minuto por e-mail e IP.** Foi contornado com um login por usuário de carga. Em teste de produção, a carga precisa de sessões pré-autenticadas ou de um limite específico para o teste.
6. **Permissões do MySQL** não são verificadas pelo guard (seção 7). Recomendação: usuário de aplicação sem `DROP` em bancos que não sejam o próprio, e usuário de migração separado.
7. **Lacuna**: o teste de token não cobre a cookie em array com token válido de outra empresa combinado; cobre cookie em array isoladamente.
8. **`composer.lock`**: decisão de preservar já registrada (commit 3), sem nova auditoria nesta execução.

## 10. Verificação final

| Verificação | Resultado |
|---|---|
| Suíte completa com MySQL e concorrência | **434 de 434**, 2571 asserções |
| Testes de concorrência real (processos separados) | 3 de 3 |
| PHPStan nível 7 (gate `composer types:check`) | **0 erros** |
| Pint | aprovado |
| TypeScript (`tsc --noEmit`) | aprovado |
| Build de produção (`npm run build`) | aprovado |
| Isolamento multi-tenant | 15 testes (CrossTenantWebTest) + existentes |
| Token de visitante | 11 testes HTTP |
| Regra de falha do agendamento | 6 testes |
| Guard de banco | testes unitários de regra + verificação manual de recusa com banco de homologação |
| Smoke tests funcionais (ambiente de produção) | cinco rotas autenticadas com 200; login; dashboard |

Não foi usado nenhum banco de produção nos testes automatizados. Os bancos da suíte (`vetorsmart_test`) e da carga (`vetorsmart_perf`) são descartáveis e foram criados nesta execução.

## 11. Ações não realizadas

- Nenhum deploy.
- Nenhum commit (aguarda autorização explícita).
- Nenhuma alteração em infraestrutura Docker compartilhada. Os containers criados para esta execução foram removidos ao final.
- VetorSales, VetorOS e VetorLeads não foram tocados.
- Nenhuma funcionalidade comercial nova.
- Nenhuma métrica mascarada ou critério flexibilizado.
