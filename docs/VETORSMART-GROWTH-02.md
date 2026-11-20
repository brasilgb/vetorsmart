# VETORSMART-GROWTH-02 — Meu Crescimento e Inteligência Comercial

Data: 20/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

**Restrições cumpridas:**
- Sem push, deploy ou VPS.
- **Sem migrations.**
- Sem dependências novas e sem bibliotecas de gráfico: as barras são HTML e CSS.
- Não foram alterados: agenda, Efí, cobrança, assinaturas, autenticação, RootAdmin, Portal Regional, sites públicos, recepcionista IA e jornada pública.

**Consolidação da OPERATIONS-02:** commit `7b8289d`. O diff foi revisado, nenhuma senha aparece nele, e `correio.md` e `executed.md` ficaram fora do commit.

## Parecer final

**Concluída.** O Meu Crescimento responde às perguntas do correio a partir dos registros da própria empresa:

| Pergunta do correio | Onde aparece |
|---|---|
| Quantos clientes estamos conquistando? | Clientes novos e atendidos no período |
| Quantas oportunidades recebemos? | Oportunidades recebidas, com comparação |
| Quantas se transformaram em serviços? | Taxa de conversão e funil |
| Qual a receita dos serviços concluídos? | Receita atribuível: valor registrado, serviços com valor, ticket médio, por canal, evolução |
| Quantos clientes voltaram a contratar? | "Voltaram a contratar", recorrentes e frequência |
| Quais canais geram mais oportunidades? | Origem das oportunidades: origem comercial e canal, separados |
| Onde estamos perdendo conversões? | Funil com o maior gargalo destacado, motivos de cancelamento e a situação de hoje |

Não há gráficos decorativos nem números fictícios. Os estados vazios mostram zeros e textos explicativos.

## 1. Recursos existentes reaproveitados

- **`GrowthMetrics` (GROWTH-01 e INTEGRATION-02):** números do período (recebidas, convertidas, concluídos, cancelados, receita, ticket), comparação com o período anterior, tabelas por canal, receita por canal, evolução de 6 meses, motivos e recomendações.
- **`CustomerPortfolio` (OPERATIONS-02):** mesmas definições da carteira de clientes: recorrente com 2 ou mais serviços concluídos; "sem atendimento recente" contextualizado pelo período.
- **Descoberta (JOURNEY-01):** coluna `discovery` gravada somente por chegada confirmada pelo portal.
- **Permissões:** `viewGrowth`, que permite só Owner e Admin. O Operador recebe 403 (testado).
- **Valores:** `Money`, em centavos inteiros.

Para não duplicar regras, o cálculo antigo de clientes no `GrowthMetrics::customers` foi **removido**. Ele contava recorrência por pedidos enviados, e o relacionamento passou a vir só do `CustomerPortfolio`.

## 2. Entregas

**1. Resumo do crescimento** (cada indicador leva aos registros):
- oportunidades;
- serviços concluídos;
- taxa de conversão (convertidas ÷ recebidas, com o denominador explicado);
- canceladas;
- clientes novos;
- voltaram a contratar;
- receita registrada;
- ticket médio.

Cada variação mostra o valor anterior ("antes: X"). Quando o período anterior não tem dados, nenhum percentual é calculado: aparece "sem base de comparação".

**2. Períodos:** novos períodos **Últimos 30, 60 e 90 dias**, contados até hoje no fuso da empresa. A comparação é com os N dias imediatamente anteriores.
- Continuam disponíveis: este mês, mês anterior, 3, 6 e 12 meses.
- O padrão continua "Este mês", para não mudar o comportamento já existente.
- Um período desconhecido volta para o padrão (validado no servidor).

**3. Evolução:** últimos 6 meses, com oportunidades, concluídos e receita por mês. As barras horizontais têm o número sempre escrito, e não há tabela larga.

**4. Funil de conversão** (`GrowthMetrics::funnel`):
- **Etapas:** oportunidade recebida → triagem → agendamento → serviço concluído.
- **Coorte:** todas as etapas acompanham as mesmas oportunidades recebidas no período, nunca somadas como oportunidades diferentes.
- **Etapa alcançada:** deduzida do histórico e da situação atual juntos, de modo que uma etapa posterior sempre implica as anteriores, inclusive em registros antigos sem histórico.
- **Gargalo:** a maior perda entre etapas é destacada, e há um link para os motivos de cancelamento.
- **Estoque separado:** a situação de hoje (`GrowthMetrics::stock`: aguardando triagem, aguardando agendamento, agendadas, em andamento) aparece em quadros tracejados, com o aviso de que não são taxas de conversão.

**5. Origem das oportunidades:**
- **Como o cliente encontrou a empresa** (`GrowthMetrics::byDiscovery`): "Portal Regional" só com a chegada confirmada pelo link assinado; as demais aparecem **à parte, como "Não identificada"**, sem presumir nada.
- **Por onde a solicitação chegou (canal):** formulário do site, formulário vindo do portal, atendimento IA, painel, com agendadas, concluídas e o número do período anterior.
- **Sem confusão entre os dois:**
  - Uma conversa da IA de visita direta é canal IA e origem não identificada.
  - Uma conversa da IA após chegada pelo portal é canal IA e origem Portal Regional.

**6. Receita atribuível:**
- valor registrado no período;
- serviços concluídos com valor;
- ticket médio;
- concluídos sem valor, fora da soma e do ticket;
- receita por canal, com ticket;
- receita dos clientes que chegaram pelo portal;
- evolução mensal.

O texto informa que não são pagamentos recebidos, saldo bancário nem faturamento fiscal. Nenhum valor de assinatura Efí é usado.

**7. Relacionamento e recorrência** (`CustomerPortfolio::growth`, com consultas agrupadas, nenhuma por cliente):

| Indicador | Regra |
|---|---|
| Clientes novos | Primeiro contato no período |
| Atendidos | Com serviço concluído no período |
| Voltaram a contratar | Atendidos no período que já tinham um serviço concluído antes |
| Recorrentes | 2 ou mais serviços concluídos |
| Sem atendimento recente | Já teve serviço concluído, mas nenhum no período, com o aviso de que não indica abandono |
| Frequência | Intervalo médio entre os serviços dos clientes recorrentes |

Os indicadores levam à carteira de clientes já filtrada.

**8. Interface:** a ordem segue a sugerida no correio: resumo, o que fazer agora, evolução, funil, origem, receita, relacionamento, cancelamentos e site.
- **Celular:** resumo em duas colunas, sem rolagem horizontal; listas no lugar de tabelas.
- **Temas claro e escuro.**
- **Valores:** formatados no servidor (R$ 1.535,00).

## 3. Decisões técnicas

1. **Filtros de canal e responsável no relacionamento:** não se aplicam, porque o relacionamento olha o cliente e não a solicitação. A tela informa isso.
2. **Clientes novos:** contam o cadastro do cliente no período, a mesma regra da carteira.
3. **"Sem atendimento recente" em períodos mensais:** usa o número de dias entre o início do período e hoje.
4. **Funil:** a etapa "triagem" também conta solicitações que chegaram já triadas, por exemplo o site com o serviço escolhido, porque elas efetivamente passaram por essa etapa.

## 4. Arquivos

**Alterados:**
- `app/Services/Growth/GrowthMetrics.php`: acrescentados `funnel`, `stock` e `byDiscovery`; removido o `customers` antigo.
- `app/Services/Customers/CustomerPortfolio.php`: método `growth`.
- `app/Http/Controllers/Tenant/GrowthController.php`: períodos em dias e as novas seções.
- `resources/js/pages/tenant/growth/index.tsx`: página reorganizada.
- `tests/Feature/Tenancy/GrowthCommercialTest.php`: o teste de "clientes" passou para a regra nova, com recorrência por serviços concluídos; mais 4 testes novos.

**Novos:** `docs/VETORSMART-GROWTH-02.md`.

**Migrations:** nenhuma.

## 5. Verificações

| Verificação | Resultado |
|---|---|
| `GrowthCommercialTest` com os novos testes: funil por coorte sem dupla contagem e com etapas encadeadas; estoque separado; origem comercial separada do canal, com "não identificada" à parte; IA sem atribuição indevida ao portal; períodos de 30, 60 e 90 dias; comparação sem percentual sobre base zero; período inválido; relacionamento por serviços concluídos e isolamento; estado vazio | **12 de 12** |
| `GrowthAndSupportTest` (permissões: Operador sem acesso) | aprovado (26 de 26 junto com o anterior) |
| Testes afetados: Tenancy, Operations, Portal, atribuição, demonstração e RootAdmin | **385 testes: 376 aprovados, 9 ignorados (já eram antes), 0 falhas** |
| PHPStan, Pint, TypeScript e build | ok |
| Navegador headless: 3 Owners das empresas de demonstração, 30 e 90 dias, celular com tema escuro e desktop com tema claro | **12 de 12 telas**: as 8 seções presentes, sem rolagem horizontal, sem erros, números iguais à base |
| Navegador headless: Operador da ClimaSul | **403** nas 2 telas |

**Conferência com a base de demonstração** (Silva Elétrica, 90 dias):
- 15 oportunidades;
- funil 15 → 12 triadas, com as 3 perdas sendo 2 aguardando triagem e 1 cancelada antes;
- Portal Regional: 6, as mesmas solicitações de origem portal;
- não identificada: 9;
- receita de R$ 1.535,00 e ticket médio de R$ 383,75.

## 6. Limitações

1. **Origem comercial:** só "Portal Regional" é identificável hoje. Não há dados confiáveis para outras origens (indicação, anúncio). Elas aparecem como não identificadas e não são inventadas.
2. **Período padrão:** continua "Este mês". Os períodos de 30, 60 e 90 dias estão no seletor.
3. **Evolução:** continua mensal, com os últimos 6 meses, sem acompanhar o período escolhido.
4. **Base de demonstração:** os registros não têm conversas da IA vindas do portal, então a combinação "canal IA + origem portal" só aparece nos testes.

## 7. Git

- `7b8289d`: OPERATIONS-02.
- **GROWTH-02 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**

Nenhuma outra etapa foi iniciada.
