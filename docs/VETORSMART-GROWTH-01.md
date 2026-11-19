# VETORSMART-GROWTH-01 — Evolução funcional do Meu Crescimento e da jornada comercial

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

**Restrições cumpridas:**
- Sem push, deploy, VPS ou produção.
- **Sem migrations.**
- Sem dependências novas.
- Sem alterações em Efí, assinaturas, cobrança, autenticação ou RootAdmin.
- Sem chamadas a modelos de IA.
- A base de demonstração não foi modificada.

**Consolidação da SEED-DEMO-01:** commit `c1c8582`. O diff foi revisado, nenhuma senha foi encontrada nos arquivos, e `correio.md`, `executed.md` e `.env` ficaram fora do commit.

## Parecer final

**Concluída.** O Meu Crescimento passou a ser uma ferramenta de gestão comercial. A página foi organizada por prioridade:
1. o que fazer agora;
2. resultados do atendimento;
3. financeiro declarado, separado dos indicadores operacionais;
4. clientes e cancelamentos;
5. origens;
6. evolução;
7. site.

Todos os números saem dos registros reais. Cada indicador leva aos registros correspondentes na lista de Solicitações, com os mesmos filtros.

| # | Critério de aceite | Situação | Evidência |
|---|---|---|---|
| 1 | Indicadores com dados reais | Atendido | Consultas agrupadas sobre solicitações, agendamentos e conversas; nenhum total gravado |
| 2 | Cálculos financeiros corretos | Atendido | Centavos inteiros. O ticket médio exclui os serviços sem valor e arredonda meio centavo para cima (testado) |
| 3 | Origem comercial preservada | Atendido | Conversa da IA identificada por um operador continua com a origem "Site / IA" (testado); portal e site já preservavam |
| 4 | Filtros funcionam | Atendido | Período, origem e responsável; os links levam os mesmos filtros |
| 5 | Chegar aos registros | Atendido | Em navegador, 14 links por empresa abrem exatamente os registros contados |
| 6 | Recomendações com ações úteis | Atendido | 6 tipos, cada um com contagem e link |
| 7 | Sem vazamento entre empresas | Atendido | Dados de outra empresa nunca entram nos números, nos motivos nem nas recomendações (testado) |
| 8 | Desktop e celular | Atendido | Sem rolagem horizontal da página nas 3 empresas |
| 9 | Temas claro e escuro | Atendido | Capturas de tela conferidas |
| 10 | Sem regressões nos testes direcionados | Atendido | 191 de 191 |

## 1. O que já existia e foi reaproveitado

- **Filtros:** período, origem e responsável, no fuso horário da empresa, com comparação com o período anterior de mesma duração.
- **Indicadores:**
  - solicitações recebidas;
  - serviços concluídos;
  - cancelados ou não realizados;
  - receita declarada, com os serviços sem valor contados à parte (INTEGRATION-02).
- **Origens:** tabela por origem que acompanha cada solicitação recebida até o agendamento, a conclusão ou o cancelamento, e receita por origem.
- **Evolução dos últimos seis meses:** com barras simples e receita por mês.
- **Lista de Solicitações:** já filtrava por situação, origem, responsável e data de recebimento.
- **Preservação da origem:**
  - Portal Regional e site individual (`SiteRequestController`);
  - IA (`AssistantOperations::registerRequest`), inclusive quando o operador identifica o cliente;
  - o agendamento copia a origem da solicitação.

  A auditoria confirmou isso no código, e um teste novo cobre a passagem da IA para a equipe.

## 2. O que foi implementado ou completado

**Indicadores comerciais:**

- **Convertidas em serviço (antes incompleto):** passam a ser as solicitações **recebidas no período** que tiveram o serviço concluído.
  - Antes, a taxa dividia os serviços concluídos no período (que podiam ter chegado antes dele) pelas solicitações recebidas no período, misturando dois grupos.
  - Agora: **taxa de conversão = convertidas ÷ solicitações recebidas no período (o denominador)**, exibida a partir de 5 solicitações.
  - O denominador é explicado na tela. Os "serviços concluídos no período" continuam disponíveis, com link próprio.
- **Ticket médio:** receita registrada ÷ serviços **com valor informado**, calculado com inteiros (arredondamento de meio centavo para cima). Serviços sem valor nunca entram como R$ 0,00. Existe no total e por origem.
- **Clientes novos e recorrentes:**
  - **novo:** a primeira solicitação do cliente à empresa foi feita no período;
  - **recorrente:** já tinha feito uma solicitação antes, por qualquer origem.
- **Cancelamentos e motivos:** solicitações encerradas no período sem o serviço, agrupadas pelo motivo registrado pela equipe, das mais frequentes para as menos (até 5), com link para os registros.

**O que fazer agora** (`GrowthRecommendations`, consultas determinísticas):

| Recomendação | Regra | Ação |
|---|---|---|
| Solicitações aguardando triagem | Situação "Solicitada" | Lista filtrada |
| Conversas aguardando a equipe | Conversa da IA passada para atendimento humano | Atendimento IA |
| Solicitações sem movimentação recente | Em aberto, criadas há mais de 7 dias e sem registro no histórico nos últimos 7 dias. Agendadas só contam quando não há visita futura | Lista filtrada |
| Agendamentos nas próximas 48 horas | Agendamentos ativos | Agenda |
| Serviços concluídos sem valor registrado | Concluídos sem valor informado | Lista filtrada |
| Queda de conversão | Somente quando os dois períodos têm taxa válida (5 ou mais solicitações) e a queda é de 10 ou mais pontos percentuais | Motivos de cancelamento |

**Ligação com os registros:**
- Os indicadores levam à lista de Solicitações com os mesmos filtros de período, origem e responsável.
- A lista ganhou:
  - `status=sem-servico` (canceladas ou não realizadas, também disponível no seletor);
  - `data=encerramento` (o período passa a valer pela data de encerramento);
  - `sem_valor=1`;
  - `parada=1`.
- Os filtros especiais aparecem como etiquetas removíveis.
- A regra de "sem movimentação" é uma única regra de consulta no modelo (escopo `ServiceRequest::stalled`), usada tanto pela recomendação quanto pelo filtro, para que os números coincidam.

**Experiência:**
- Seções por prioridade. Os cartões redundantes do portal deram lugar a uma coluna "Recebidas no período anterior" na tabela de origens.
- O bloco financeiro tem fundo e título próprios e o aviso "não são comprovante de pagamento".
- O gráfico de evolução continua sendo uma tabela com barras simples, agora também para a receita, com dica ao passar o mouse.
- Estados vazios explicam o que fazer.
- O layout é responsivo, e os temas claro e escuro foram conferidos.

## 3. Arquivos

**Novos:**
- `app/Services/Growth/GrowthRecommendations.php`
- `tests/Feature/Tenancy/GrowthCommercialTest.php`
- `docs/VETORSMART-GROWTH-01.md`

**Alterados:**
- `app/Services/Growth/GrowthMetrics.php`: convertidas, clientes, motivos, ticket médio.
- `app/Http/Controllers/Tenant/GrowthController.php`
- `app/Http/Controllers/Tenant/ServiceRequestController.php`: novos filtros da lista.
- `app/Models/ServiceRequest.php`: escopo `stalled`.
- `resources/js/pages/tenant/growth/index.tsx`
- `resources/js/pages/tenant/requests/index.tsx`
- `tests/Feature/Websites/RegionalPortalTest.php`: a comparação das linhas de origem passou a ignorar o novo link (`href`). O comportamento testado não mudou.

**Migrations:** nenhuma.

## 4. Testes e verificações

| Verificação | Resultado |
|---|---|
| `GrowthCommercialTest` (novo): conversão por grupo de recebidas, ticket sem valores desconhecidos e arredondamento, clientes novos e recorrentes, motivos, recomendações e isolamento, queda de conversão só com base válida, links abrindo os registros certos, origem IA preservada | **8 de 8** |
| Testes afetados: Growth, OperationalManagement, IntegrationJourney, ServiceRequestWorkflow, OperationalIsolation, PlatformAdministration, RegionalPortal, DemoSeeder e Assistant | **191 de 191** |
| PHPStan, Pint, TypeScript e build | ok |
| Navegador headless: Meu Crescimento dos 3 tenants de demonstração (desktop com tema claro e celular com tema escuro) | 6 de 6 telas: HTTP 200, sem erros, sem rolagem horizontal, tema escuro aplicado |
| Links do painel (14 por empresa) | Todos com HTTP 200 e a quantidade esperada |

**Conferência com a base de demonstração** (últimos 3 meses, Silva Elétrica):

| Indicador | Valor |
|---|---|
| Solicitações recebidas | 15 |
| Convertidas | 6 |
| Taxa de conversão | 40% |
| Cancelados | 2 |
| Receita registrada | R$ 1.535,00 |
| Ticket médio | R$ 383,75 (= 1.535,00 ÷ 4) |
| Concluídos sem valor | 2 |
| Clientes | 8 novos |

Os mesmos números foram obtidos ao abrir as listas pelos links.

## 5. Limitações e pendências reais

1. **Motivos de cancelamento:** são texto livre, agrupados por texto idêntico. Motivos escritos de formas diferentes aparecem separados; uma lista padronizada exigiria migration.
2. **Origem de uma conversa da IA iniciada a partir do portal:** fica "Site / IA". O sistema não guarda de onde o visitante chegou ao site antes de abrir o chat.
3. **Recomendações de agenda e de solicitações paradas** não aparecem na base de demonstração: o primeiro agendamento é daqui a 3 dias e todas as solicitações abertas tiveram movimento recente. Ambas estão cobertas por testes.
4. **Painel de crescimento da plataforma (RootAdmin):** continua com a sua própria definição de conversão (concluídos no período ÷ recebidas no período), porque não foi alterado nesta etapa.

## 6. Git

- `c1c8582`: SEED-DEMO-01.
- **GROWTH-01 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**

Nenhuma outra etapa foi iniciada.
