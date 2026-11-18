# VETORSMART-BILLING-01.2 — Proposta técnica de créditos de IA

Data: 18/11/2026. Documento de apoio à decisão comercial. **Nenhum valor desta proposta foi aplicado**: a franquia segue vazia, nenhum pacote foi criado e a compra de créditos continua desligada.

## 1. Situação atual

- **Modelos disponíveis: nenhum provedor está integrado.** `ASSISTANT_PROVIDER=none`, e o assistente responde só pelo fluxo determinístico.
- A arquitetura está pronta para um provedor (interface `AssistantProvider`), mas não existe contrato, chave ou tabela de preços de modelo no projeto.
- Por isso, **os custos abaixo são faixas hipotéticas** e precisam ser substituídas pelo preço real do modelo contratado.

## 2. Consumo medido no código

**A única operação paga de IA é a classificação do serviço** (`AssistantFlow::classifyWithProvider`):

- Ela só é usada quando o texto do visitante não casa com nenhum serviço pelo método determinístico.
- O limite é `max_provider_tokens_per_call = 600` tokens por chamada.
- Hoje, os tokens são estimados como `caracteres da mensagem ÷ 4 + 40`. Essa conta **subestima** o consumo real, porque a chamada também envia a lista de serviços e as instruções.
- **Antes de definir valores**, o provedor integrado deve devolver o uso real (tokens de entrada e saída da resposta), e esse número deve ser gravado no histórico.

**Conversão:** a configuração atual é de 1.000 tokens por crédito, com arredondamento para cima. Uma classificação (até 600 tokens) consome **1 crédito**.

**Estimativa de uso por empresa**, supondo que 30% das conversas precisam de classificação, com 1 chamada cada:

| Perfil | Conversas/mês | Classificações/mês | Créditos/mês |
|---|---:|---:|---:|
| Pequeno | 300 | 90 | ~90 |
| Médio | 1.000 | 300 | ~300 |
| Alto | 3.000 | 900 | ~900 |

## 3. Custo por crédito em faixas hipotéticas

Custo combinado (entrada e saída) por milhão de tokens, em reais. As faixas são **ilustrativas**, não preços de fornecedor:

| Faixa | R$ por 1M tokens | Custo por crédito (1.000 tokens) | Franquia de 500 créditos | % da mensalidade (R$ 139,90) |
|---|---:|---:|---:|---:|
| A — modelo econômico | 3,00 | R$ 0,003 | R$ 1,50 | 1,1% |
| B — modelo intermediário | 15,00 | R$ 0,015 | R$ 7,50 | 5,4% |
| C — modelo avançado | 60,00 | R$ 0,060 | R$ 30,00 | 21,4% |

Para a classificação (escolher um serviço numa lista curta), um modelo das faixas A ou B é suficiente. A faixa C compromete a margem do plano.

## 4. Proposta (sujeita à aprovação)

**Franquia inicial: 500 créditos por mês**, em todos os ciclos.
- Cobre com folga o perfil médio (~300) e mais de 5 vezes o pequeno.
- Na faixa B, custa no máximo R$ 7,50 por empresa por mês, se toda a franquia for usada.

**Pacotes adicionais** (preços calculados sobre a faixa B):

| Pacote | Créditos | Preço sugerido | Custo na faixa B | Margem bruta |
|---|---:|---:|---:|---:|
| Básico | 500 | R$ 14,90 | R$ 7,50 | ~50% |
| Intermediário | 2.000 | R$ 49,90 | R$ 30,00 | ~40% |
| Avançado | 5.000 | R$ 109,90 | R$ 75,00 | ~32% |

As margens não descontam tarifas da Efí, impostos e inadimplência. **Se o modelo contratado ficar na faixa C, os pacotes acima dão prejuízo** e precisam ser recalculados.

**Regras já implementadas que sustentam a proposta:**
- a franquia expira todo mês e os créditos comprados não expiram;
- sem saldo, só a chamada à IA para;
- o alerta de saldo baixo dispara a 20% da franquia (100 créditos).

## 5. Passos antes de aprovar

1. Escolher e contratar o provedor/modelo; integrar ao `AssistantProvider`, gravando os tokens reais da resposta.
2. Preencher `assistant.cost_per_million_tokens_cents` com o preço contratado, para o custo estimado aparecer no histórico.
3. Rodar 30 dias em homologação com empresas de teste e comparar o consumo real com a seção 2.
4. Recalcular a franquia, a taxa de tokens por crédito e os pacotes com os dados reais e o preço do modelo.
5. Aprovar os valores; configurar franquia e pacotes no RootAdmin; só então ligar a compra de créditos, que ainda depende da integração do pagamento com a Efí.
