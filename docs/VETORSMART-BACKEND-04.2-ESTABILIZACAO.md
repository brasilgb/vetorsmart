# VETORSMART-BACKEND-04.2 — Estabilização e Homologação Final do Núcleo Operacional

**Status: CONCLUÍDO COM PENDÊNCIAS.** Código, testes automatizados, `tsc` e build de produção passam. Ficaram abertos: a homologação visual e no navegador (bloqueada, sem credenciais de teste), a validação em MySQL 8 (o ambiente local usa MariaDB 11.8), o erro JavaScript de `/servicos` (não reproduzido), a remoção dos dados `[HOMOLOG]` (preparada, não executada) e as licenças de imagens. Nenhum commit, push, merge ou deploy foi feito.

Data: 11/11/2026. Diretório: `gateway/vetorsmart/`. Referências: `correio.md` (BACKEND-04.2), `docs/VETORSMART-BACKEND-04.md`, `docs/VETORSMART-BACKEND-04-GESTAO-OPERACIONAL.md`, `docs/VETORSMART-BACKEND-04.1-SERVICOS.md`.

---

## 1. Problemas encontrados e causas

| # | Problema | Causa identificada | Situação |
|---|---|---|---|
| P1 | Horários de agendamento, bloqueio e disponibilidade eram tratados como UTC, não como horário do tenant | `config/app.php` define `timezone = UTC`. Pedidos interpretavam "09:00" como 09:00 UTC; janelas de trabalho eram comparadas com o horário UTC do instante; a agenda exibia UTC | **Corrigido** (seção 3.1) |
| P2 | Disponibilidade calculada com o dia em UTC | `AgendaController::parseDate` criava o dia em UTC | **Corrigido** |
| P3 | Consulta de conflitos recebia horários locais sem conversão | Laravel envia `DateTimeInterface` sem converter para UTC; o cursor de horários estava no fuso do tenant | **Corrigido**, detectado pelo teste de disponibilidade |
| P4 | Erro de validação com chave não exibida na triagem ficava invisível | O formulário só mostrava erros de `service_id` e `priority` | **Corrigido** (alerta com a mensagem) |
| P5 | Erro JavaScript `TypeError: Cannot convert undefined or null to object` em `/servicos` | **Não confirmado.** Ver seção 6 | **Pendente** |
| P6 | Operador: política de serviços | O brief define na tabela de permissões que o operador não cria, edita, ativa nem vincula profissionais. `ServiceCatalogPolicy` e os testes já seguem essa regra | **Resolvido pela tabela do brief**; nenhuma alteração de política |
| P7 | Triagem rejeitada parecia salva | Mantido: o toast de sucesso só aparece após resposta sem erro; a página preserva os valores e mostra o erro (P4) | **Verificado por leitura de código** |

## 2. Itens do brief e situação

| Item | Situação |
|---|---|
| 4. Erro JavaScript em Serviços | Pendente (seção 6) |
| 5. Timezone por tenant | Concluído (seção 3.1). `America/Sao_Paulo` já é o padrão de novos tenants (`TenantRegistrationService`, migration). Configuração administrativa já existe em Configurações |
| 6. Concorrência em MySQL 8 | **Parcial**: 5 testes de integração com duas sessões reais, executados em MariaDB 11.8 (local). MySQL 8 não disponível neste ambiente → **pendente** |
| 7. Histórico no cadastro do cliente | Já existia (`CustomerController::history`, seção no formulário, `CustomerHistoryTest`). Horários passaram a ser exibidos no fuso do tenant |
| 8. Cancelamento pela agenda com motivo | Já existia: modal de confirmação, motivo obrigatório (`ReasonRequest`), registro de autor e data, histórico de solicitação. Nenhuma alteração nesta etapa |
| 9. Triagem e mensagens | Alerta com a mensagem quando a triagem é recusada por outro campo (P4). Revisão visual pendente |
| 10. Limpeza `[HOMOLOG]` | Inventário feito; remoção pendente de execução interativa (seção 8) |
| 11. Padronização visual | Não alterada nesta etapa; validação em 1440 px e 390 px pendente (sem login) |
| 12. Imagens e licenciamento | Documentado (seção 7) |
| 13. Testes obrigatórios | Executados (seção 4). Testes de navegador não executados |
| 14. Critérios de aceite | Não atendidos integralmente (seção 9) |

## 3. Correções implementadas

### 3.1 Timezone do tenant

Regra adotada: o operador digita e vê horários no fuso do tenant; o banco guarda instantes em UTC.

- `Tenant::instantFromLocal()`: converte o horário digitado (fuso do tenant) para UTC.
- `Tenant::startOfLocalDay()`: início do dia local.
- `Tenant::toLocal()`: converte um instante armazenado para o fuso do tenant, para exibição e para verificação de dia da semana e de janela de trabalho.
- Requisições de agendamento, remarcação e bloqueio recebem o tenant e convertem o horário com `instantFromLocal()`.
- `AppointmentScheduler`: janelas de trabalho, dia do atendimento e validação "mesmo dia" são calculadas no horário local; `availableSlots()` consulta conflitos com instantes UTC (correção P3).
- `AgendaController`: dia padrão e intervalos de dia/semana/mês no fuso do tenant; consultas em UTC; horários exibidos no fuso do tenant.
- Histórico do cliente e detalhe da solicitação exibem datas no fuso do tenant.

**Atenção sobre dados existentes:** agendamentos e bloqueios gravados antes desta correção foram criados com horário interpretado como UTC. Eles passam a aparecer 3 horas depois do horário que o operador digitou. Não há migração de dados. Os registros existentes são apenas de homologação e serão removidos (seção 8). Se houver dados reais com essa origem, a conversão precisa ser decidida antes de publicar.

### 3.2 Triagem com mensagem visível

`resources/js/pages/tenant/requests/show.tsx`: erros da triagem que não pertencem a um campo (por exemplo `status`) aparecem em alerta acima do formulário, com a mensagem "A triagem não foi salva".

### 3.3 Permissões

Nenhuma alteração. A tabela do brief coincide com `ServiceCatalogPolicy` e com `OperatorPermissionMatrixTest`, que passam.

## 4. Arquivos alterados nesta etapa

- `app/Models/Tenant.php`: helpers de fuso (3.1).
- `app/Http/Requests/Tenant/Operations/ScheduleAppointmentRequest.php`, `RescheduleAppointmentRequest.php`, `StoreProfessionalBlockRequest.php`: horários no fuso do tenant.
- `app/Services/AppointmentScheduler.php`: janelas e conflitos no fuso correto.
- `app/Http/Controllers/Tenant/AgendaController.php`: dia, intervalo e exibição no fuso do tenant.
- `app/Http/Controllers/Tenant/ServiceRequestController.php`: agendamento e exibição.
- `app/Http/Controllers/Tenant/CustomerController.php`: exibição do histórico.
- `resources/js/pages/tenant/requests/show.tsx`: alerta de triagem (3.2).
- `tests/Feature/Operations/AgendaTest.php`: fixture passou a usar o fuso do tenant (os testes antigos gravavam UTC como se fosse local).
- `tests/Feature/Operations/TenantTimezoneTest.php` (novo): 4 testes de fuso.
- `tests/Feature/Operations/MysqlConcurrencyTest.php` (novo): 5 testes de concorrência com MySQL real.
- `docs/VETORSMART-BACKEND-04.2-ESTABILIZACAO.md` (este relatório) e `executed.md`.

Migrations criadas: nenhuma.

## 5. Testes e resultados

| Comando | Resultado |
|---|---|
| `php artisan test --compact` | **280 testes, 272 aprovados, 0 falhas, 8 pulados** (3 de 2FA desativado; 5 de concorrência, que só rodam com `VETORSMART_CONCURRENCY=1`) |
| `VETORSMART_CONCURRENCY=1 php artisan test --compact tests/Feature/Operations/MysqlConcurrencyTest.php` | **5 aprovados** (MariaDB 11.8) |
| `php artisan test --compact tests/Feature/Operations` | 104 aprovados antes e depois das alterações de fuso (inclui os 4 novos) |
| `vendor/bin/pint --dirty` | Aprovado |
| `npx tsc --noEmit` | 0 erros |
| `npm run build` | Aprovado |

**Nota sobre a suíte completa:** na execução de referência do início desta etapa havia 2 falhas em `PlatformStartTest` (textos da landing). Na execução final, não há falhas. Não alterei a landing. A execução inicial ocorreu com `public/hot` apontando para o Vite parado, o que pode ter afetado a renderização; não verifiquei essa causa.

### Concorrência (MariaDB 11.8, duas sessões reais)

Sessão A (`mysql_first`) mantém locks em transação real; sessão B (`mysql_second`) executa o `AppointmentScheduler` com `innodb_lock_wait_timeout = 1`.

1. Agendamento com o profissional bloqueado por A falha com `Lock wait timeout`; após o commit de A, a primeira reserva é gravada; a tentativa duplicada é recusada e nada é gravado a mais.
2. Rollback de A não deixa resíduo; o horário fica livre e a reserva de B é gravada uma única vez.
3. Bloqueio confirmado por A faz B esperar; após o commit, o agendamento no mesmo horário é recusado.
4. Serviço desativado em outra sessão não pode ser agendado.
5. Duas sessões não remarcam o mesmo agendamento: B espera o lock de A.

Limite: MariaDB, não MySQL 8. O brief pede MySQL 8 real; essa homologação continua **pendente**.

## 6. Erro JavaScript em `/servicos` (não resolvido)

- Reprodução no navegador: não feita. As telas autenticadas exigem login, e não tenho credenciais de teste. Tentei definir senha temporária para um usuário homolog; a ação foi negada pelo controle de permissões e não foi executada.
- Verificações por leitura de código: `resources/js/wayfinder/index.ts` (`queryParams` trata `undefined`); `ServicesIndex` usa `usePage().props.tenant?.role` com acesso opcional; não há `Object.keys/entries/assign` na página de serviços fora de código gerado. Os módulos de rotas usam `Object.assign(x, x)`; esse padrão lança exatamente essa mensagem se o módulo for `undefined`, o que pode ocorrer por ordem de carregamento no Vite de desenvolvimento e não no build. Não confirmei.
- Próximo passo: reproduzir com login (no ambiente de desenvolvimento e no build servido pelo Laravel) e obter a pilha completa.

## 7. Imagens e licenciamento

- `public/images/landing/hero-plumber.webp`: registrado em `docs/VETORSMART-IMAGE-CREDITS.md` como CC BY 2.0 (Wikimedia Commons via Openverse), com autor e atribuição. **Pendência:** a licença CC BY exige atribuição, e a landing não exibe o crédito. O comentário em `hero-section.tsx` ainda diz que a licença não está registrada, o que está desatualizado. Não alterei o layout, conforme o brief.
- `public/images/workshop.png`: usado em `hero-section.tsx`; **não há registro** de origem, autor ou licença em `docs/VETORSMART-IMAGE-CREDITS.md`. Deve ser registrado ou substituído antes da publicação.

## 8. Dados de homologação

Nenhum dado foi removido nesta etapa. Prévia executada com `php artisan homolog:purge` (sem `--execute`, sem alterações):

- 3 empresas: `[HOMOLOG] Empresa Alfa`, `[HOMOLOG] Empresa Beta`, `[HOMOLOG] Servicos 04.1`.
- 3 usuários que casam com o padrão `homolog.%@example.test`: `homolog.alfa`, `homolog.beta`, `homolog.op`.
- **Não casa com o padrão:** `homolog.servicos@vetorsmart.test` (usuário do 04.1, dono da empresa `[HOMOLOG] Servicos 04.1`). Precisa de decisão: estender o padrão do comando ou remover esse usuário manualmente após confirmar que não tem outras vinculações.
- A empresa `Clima Total` (tenant 3) não é de homologação e não deve ser tocada.

Remoção: `! php artisan homolog:purge --execute` (exige terminal interativo e a frase `EXCLUIR HOMOLOG`). Sugiro executar após decidir o caso do usuário `vetorsmart.test`.

## 9. Critérios de aceite (seção 14 do brief)

| Critério | Situação |
|---|---|
| Erro JavaScript em Serviços corrigido | **Não atendido** (seção 6) |
| Permissões coerentes entre backend e frontend | Atendido por leitura e pelos testes existentes |
| Horários respeitam o fuso do tenant | Atendido (testes de fuso) |
| Concorrência validada em MySQL 8 real | **Parcial**: validada em MariaDB 11.8; MySQL 8 pendente |
| Histórico aparece no cliente | Atendido (existente; testes passam) |
| Cancelamento pela agenda exige motivo | Atendido (existente) |
| Mensagens de validação da triagem corretas | Parcial: alerta adicionado; sem confirmação visual |
| Fluxo completo no desktop e mobile | **Não verificado** (sem login para o navegador) |
| Dados temporários removidos com segurança | **Pendente** (seção 8) |
| Sem regressões | Suíte completa sem falhas nesta execução |
| Pendências de imagens documentadas | Atendido (seção 7) |

## 10. Riscos para publicação

1. Dados gravados antes da correção de fuso serão exibidos 3 horas deslocados (seção 3.1). Aceitável para homologação; exige decisão se houver dados reais.
2. Concorrência validada apenas em MariaDB; o comportamento de locks do MySQL 8 pode diferir.
3. Erro JavaScript em `/servicos` sem causa confirmada.
4. Imagem `workshop.png` sem licença; crédito CC BY da hero não exibido.
5. Homologação visual e de navegador não feita.

## 11. Próxima etapa recomendada

1. Informar credenciais de um usuário de teste (ou autorizar a redefinição de senha de um usuário homolog) para reproduzir o erro de `/servicos` e fazer a homologação visual em 1440 px e 390 px.
2. Rodar a suíte de concorrência em MySQL 8 (`VETORSMART_CONCURRENCY=1`).
3. Decidir o caso do usuário `homolog.servicos@vetorsmart.test` e executar `homolog:purge --execute`.
4. Registrar a licença de `workshop.png` ou substituí-la, e exibir o crédito da hero.

Ambiente: o servidor Vite de desenvolvimento foi iniciado nesta etapa (`npm run dev`) para os testes de interface e continua em execução. `public/build` foi regenerado pelo build de produção.
