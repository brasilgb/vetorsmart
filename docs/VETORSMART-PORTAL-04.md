# VETORSMART-PORTAL-04 — Conversão, origem e rastreabilidade dos atendimentos

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem push, deploy, VPS ou DNS.
- **Sem migrations nem alteração de esquema.**
- Sem Efí ou regras financeiras.
- Sem MFA.
- Sem dependências.
- Sem redesign.

**Consolidação da PORTAL-03:** commit `8a670bb`.

## Parecer técnico final

**Concluída, com uma dependência documentada (receita atribuível).**

O atendimento iniciado no portal chega ao painel da empresa certa, com o serviço validado, a origem "Portal Regional" gravada na criação e o status operacional normal.
- Reenvios acidentais não duplicam o pedido.
- Uma falha no registro nunca confirma.
- O prestador identifica e filtra esses pedidos no painel, e "Meu Crescimento" mostra recebidos, agendados e concluídos por origem, com dados reais.
- A **receita atribuível não foi implementada**: o VetorSmart não registra o valor cobrado em cada atendimento, e isso exige mudança de esquema (seção 5).

| # | Critério | Situação |
|---|---|---|
| 1 | PORTAL-03 consolidada | `8a670bb` |
| 2 | Fluxo existente documentado | Seção 1 |
| 3 | Consumidor solicita a partir do portal | Atendido (teste da jornada completa) |
| 4 | Empresa de destino determinada com segurança | Atendido: só pelo endereço; `tenant_id` no formulário é ignorado (testado) |
| 5 | Serviço validado | Atendido: só serviço publicado e ativo da própria empresa (testado) |
| 6 | Origem registrada de forma confiável | Atendido: gravada na criação e imutável depois (testado). Observação na seção 6 |
| 7 | Prestador identifica a solicitação no painel | Atendido: selo, coluna, filtro e detalhe |
| 8 | Indicadores com dados reais | Atendido para recebidas, agendadas e concluídas. **Receita: não atendido** (depende de esquema) |
| 9 | Isolamento preservado | Atendido (testado) |
| 10 | Sem alteração financeira, migration, push ou deploy | Atendido |

## 1. Inventário do fluxo existente (auditoria)

| Etapa | Como funciona (antes desta etapa) | Onde |
|---|---|---|
| Portal → site | Os cartões levam ao endereço padrão do site com `?origem=portal` e, se o serviço buscado é publicado pela empresa, `&servico={id}` | `portal/partials/cards.blade.php`, `PortalController::siteBase` |
| Serviço preservado | O site pré-seleciona `servico` no botão principal só se o serviço for publicado por aquela empresa; o formulário o pré-seleciona só entre os publicados | `SiteController::render`, `SiteRequestController::show` |
| Origem transmitida | `origem=portal` segue nos links internos do site e vira um campo oculto do formulário | `SiteController`, `website/solicitar.blade.php` |
| Empresa | Vem **só do endereço** (slugs ou host), nunca do formulário ou da sessão | `PublicSiteContext::forRequest` |
| Persistência | `ServiceRequestService::create`, em transação: `service_requests` (tenant, cliente, descrição, `origin`, status `requested`, `created_at`), evento `created` no histórico, auditoria (com a origem) e aviso à equipe (`requestReceived`). Com serviço válido, a triagem é imediata | `SiteRequestController::store` |
| Origem | Enum `RequestOrigin` (`portal` = "Portal Regional", `public_site`, `panel`, `ai`, `api`) na coluna `origin` existente. **Só é gravada na criação.** O painel cria sempre com `panel`, nenhuma rota a altera, e o agendamento copia a origem do pedido | `ServiceRequestService`, `AppointmentScheduler` |
| Painel | A lista tinha filtro só por situação, **sem a origem**. O detalhe exibia a origem em texto | `ServiceRequestController` |
| Indicadores | "Meu Crescimento": recebidas, agendamentos realizados, concluídas e conversão do mês, **sem a origem** | `GrowthMetrics`, `GrowthController` |
| Proteção contra abuso | Limite `site-request` (3 por minuto, 10 por hora), campo-armadilha, CSRF e validação do telefone. **Não havia proteção contra reenvio do mesmo formulário** | WEBSITES-10 |
| Atribuição de cliques | Não existe (e não foi criada): o portal não registra cliques nem visitas | — |

## 2. Implementações realizadas

### 2.1 Reenvio acidental e falhas de registro (formulário do site)

- **Token por formulário:** cada formulário exibido recebe um token aleatório de uso único (`envio`, 40 caracteres).
- **Envio repetido:** duplo clique, recarregar ou rede lenta com o mesmo token **não criam um segundo pedido**. O visitante recebe a mesma confirmação (testado).
- **Mecanismo:** trava do cache do framework (`Cache::lock`), sem migration. O token só é marcado como usado **depois** do registro salvo. Se o registro falha, **não há confirmação**, e o mesmo formulário pode ser reenviado e registrado uma única vez (testado com falha simulada).
- **Dados inválidos:** não confirmam. O formulário volta com os erros, o mesmo token e a origem preservada (testado).
- **Navegador:** o botão fica desabilitado ("Enviando…") enquanto o envio acontece.
- **Formulários antigos sem token:** continuam funcionando, sem a proteção de reenvio.
- **Produção:** usa `CACHE_STORE=database`, que suporta travas atômicas.

### 2.2 Painel do assinante

- **Lista de Solicitações:**
  - nova coluna **Origem**, com selo destacado para **"Portal Regional"**;
  - novo filtro **Origem**, combinável com a situação e preservado na paginação;
  - um valor desconhecido é ignorado.
- **Detalhe:** a origem aparece com o mesmo selo.
- **Sem painel paralelo:** a triagem, a agenda, a execução e a conclusão continuam iguais, e os outros canais seguem aparecendo normalmente.

### 2.3 Meu Crescimento

Nova seção **"Origem das solicitações em {mês}"**:

- **Cartões do Portal Regional:** recebidas, que chegaram a ser agendadas, e com serviço concluído, cada um com o valor do mês anterior.
- **Tabela por origem:** recebidas, agendadas e concluídas.
- **Definição por coorte:** são as solicitações **recebidas no mês**. "Agendadas" significa que teve ao menos um agendamento; "concluídas" significa status concluído. **Um agendamento nunca conta como serviço concluído.** Cliques e visitas não são contados.
- **Receita:** a tela informa que a receita por origem não é exibida porque o valor cobrado não é registrado.
- **Permissões:** valem as existentes (`viewGrowth`).
- **Consulta:** uma por mês, agrupada por origem, só da empresa logada.

### 2.4 Neutralidade

O portal não lê nenhuma métrica de crescimento. Um teste mostra que 15 atendimentos concluídos de uma empresa **não mudam** a ordem do portal.

## 3. Arquivos modificados

- `app/Http/Controllers/Site/SiteRequestController.php`: token de envio, trava e confirmação só após o registro.
- `app/Http/Requests/Site/StoreSiteServiceRequest.php`: validação de `envio`.
- `resources/views/website/solicitar.blade.php`: campo `envio`, mensagem de envio em andamento e bloqueio do botão.
- `app/Http/Controllers/Tenant/ServiceRequestController.php`: filtro e dados de origem.
- `resources/js/pages/tenant/requests/index.tsx` e `show.tsx`: coluna, filtro e selo `OriginLabel`.
- `app/Services/Growth/GrowthMetrics.php`: `byOrigin()`.
- `app/Http/Controllers/Tenant/GrowthController.php`: `origins` e `previousOrigins`.
- `resources/js/pages/tenant/growth/index.tsx`: seção de origem.
- `tests/Feature/Websites/RegionalPortalTest.php`: 8 testes novos.

## 4. Resultados das validações

| Verificação | Resultado |
|---|---|
| `RegionalPortalTest` | **35 de 35** (27 anteriores e 8 novos, detalhados abaixo) |
| Testes existentes das telas alteradas (`CrossTenantWebTest`, `GrowthAndSupportTest`, `ServiceRequestWorkflowTest`, `PublicSiteEvolutionTest`) | **51 de 51** |
| PHPStan (arquivos alterados) / Pint / TypeScript / build | 0 erros / ok / ok / ok |
| Visual das telas alteradas do painel (Solicitações, filtro Portal e Meu Crescimento; celular e computador, claro e escuro) | **12 de 12**, após tornar acessível por teclado a nova tabela rolável |

Os 8 testes novos cobrem:
- jornada completa portal → painel da empresa certa, com serviço, origem, data e status, e invisível para outra empresa;
- empresa só pelo endereço e serviço de outra empresa ignorado;
- reenvio do mesmo formulário (1 pedido) e novo formulário (2 pedidos);
- falha de registro sem confirmação e com reenvio;
- dados inválidos sem confirmação;
- origem imutável na triagem;
- indicadores por origem reais e isolados;
- ordem do portal independente do volume de atendimentos.

A suíte completa e os testes visuais do portal não foram repetidos (sem mudança nessas telas).

## 5. Indicadores disponíveis e indisponíveis

| Indicador | Situação |
|---|---|
| Solicitações recebidas pelo portal | **Disponível** (`origin = portal`, por mês) |
| Solicitações do portal convertidas em agendamento | **Disponível** (com ao menos um agendamento) |
| Serviços concluídos originados pelo portal | **Disponível** (status concluído; não confunde com agendamento) |
| Comparação com o mês anterior | **Disponível** |
| Mesmos números para as outras origens | **Disponível** |
| **Receita atribuível** | **Indisponível.** `service_requests` e `appointments` não têm valor cobrado, e o preço do catálogo não é valor real. Depende de um campo de valor efetivo, ou de um registro de cobrança vinculado ao atendimento (**migration**) |
| Cliques ou visitas vindos do portal | **Não medidos**, por decisão: não confundir com solicitações |

## 6. Pendências para evolução futura

1. **Receita atribuível:** campo de valor cobrado na conclusão do atendimento (migration).
2. **Origem declarativa:** a origem "Portal Regional" vem do link do portal (`?origem=portal`); quem copia o link também a envia. Ela **nunca muda a empresa nem o serviço** e não é alterável depois da criação. Uma atribuição verificável (token assinado emitido pelo portal) fica para uma etapa própria.
3. **Protocolo para o consumidor:** avaliar um protocolo não sequencial, para não expor o volume da empresa.
4. **Painel inicial:** um contador do portal no dashboard, se os assinantes pedirem.
5. **RootAdmin:** indicadores agregados do portal, sem uso no ranqueamento.
6. **Implantação:** DNS e certificado do portal e as 19 migrations pendentes.

## 7. Git

- `8a670bb`: PORTAL-03.
- **PORTAL-04 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**

A PORTAL-05 não foi iniciada.
