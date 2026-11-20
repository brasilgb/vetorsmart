# VETORSMART-GROWTH-03 — Rastreabilidade de Oportunidades e Atribuição Comercial

Data: 20/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

**Restrições cumpridas:**
- Sem push, deploy ou VPS.
- Sem dependências novas.
- **1 migration local**, justificada na seção 3.
- Não foram alterados: Efí, cobrança, assinaturas, planos, autenticação, RootAdmin, agenda e infraestrutura.
- Fluxos públicos: só a captura e a preservação da origem; as interfaces públicas não mudaram.

**Consolidação da GROWTH-02:** commit `3a176da`. O diff foi revisado e não contém senhas; `correio.md` e `executed.md` ficaram fora do commit.

## Parecer final

**Concluída no modelo de atribuição, na captura e no Meu Crescimento.** O item 7 do correio (exibir a origem no detalhe da solicitação e na ficha do cliente) não foi feito, por decisão sua durante a execução, e fica pendente.

As três dimensões pedidas estão separadas:

| Dimensão | De onde vem | Valores |
|---|---|---|
| **Origem comercial**: como o cliente encontrou a empresa | Primeiro registro confiável, gravado na criação | Portal Regional (link assinado verificado), Campanha (UTM do link) ou Não identificada |
| **Canal de entrada**: por onde a solicitação chegou | `RequestOrigin::channel()`, derivado do valor gravado, sem reescrever dados | Site — formulário, Site — atendimento virtual, Painel (registro manual), API |
| **Atendimento**: quem conduziu | A conversa da IA ligada à solicitação (`handoff_reason`, `handled_by_user_id`) e o responsável (INTEGRATION-02) | Já estava gravado; nenhum cálculo novo foi preciso |

**Os exemplos do correio:**
- Cliente que chega pelo portal e usa o formulário do site: origem Portal Regional, canal Site — formulário.
- Acesso direto ao site: canal site, origem Não identificada. Nenhuma "origem direta" é declarada sem evidência.
- Registro pelo painel: canal Painel, origem Não identificada. O painel recusa campanha, mesmo que alguém a envie.
- IA atendendo um cliente vindo do portal: origem Portal Regional, canal atendimento virtual.

## 1. Campos e mecanismos reaproveitados

- **`service_requests.origin`:** canal gravado. Mantido como está; o canal é derivado na leitura.
- **`discovery` e `PortalReferral` (JOURNEY-01):** link do portal assinado, chegada registrada na sessão do servidor e transporte pela conversa da IA até a solicitação, inclusive na passagem para a equipe.
- **Conversa da IA e responsável pelo atendimento:** dados que já indicavam quem conduziu a solicitação.
- **`GrowthMetrics`:** coortes, conversão e `Money`.

## 2. Funcionalidades implementadas

**Captura de campanha (`App\Services\Websites\CampaignTags`):**
- **O que lê:** `utm_source`, `utm_medium` e `utm_campaign` das páginas públicas da empresa (site, serviços, formulário e atendimento virtual), nos mesmos pontos em que a chegada do portal já era registrada.
- **Primeira marcação da visita:** a primeira página marcada é guardada na sessão do servidor por 24 horas, e páginas marcadas depois não a substituem.
- **Higienização:** tudo em minúsculas; só letras, números, ponto, hífen e sublinhado; até 100 caracteres. O resto é descartado. Nenhum dado pessoal é lido ou guardado.
- **Gravação:** só na criação da solicitação (formulário) ou da conversa (IA, que repassa à solicitação), e nunca sobrescrita depois.
- **Limites:** campanha é informação declarada pelo link. **Nunca comprova o Portal Regional**: `utm_source=portal` ou `origem=portal` não concedem essa origem (testado). Também nunca carrega empresa, situação ou permissão.

**Origem comercial (`ServiceRequest::commercialOrigin()`):** a prioridade segue o primeiro registro confiável: portal verificado, depois campanha, depois não identificada. Ela não é deduzida do canal nem de quem atendeu.

**Canal (`RequestOrigin::channel()`):** o valor gravado `portal` (formulário aberto a partir do portal) entra no canal "Site — formulário", e a origem dele é o portal. Os dados históricos não foram reescritos.

**Meu Crescimento** (`GrowthMetrics::byCommercialOrigin` e `byChannel`):
- **Duas visões:** "Origem comercial" e "Canal de entrada", lado a lado. Cada oportunidade aparece uma vez em cada visão, e as duas somam o mesmo total (testado).
- **Por linha:** recebidas, concluídas, conversão por coorte (só a partir de 5 oportunidades) e receita registrada.
- **Campanhas por fonte:** até 5 fontes.
- **Não identificada:** aparece à parte.
- **Filtro "Canal":** rótulos pelo canal; o valor do portal aparece como "Site — formulário (vindo do portal)".
- **Receita por origem e por canal:** com o aviso de que é valor registrado pela empresa, e não receita gerada pela VetorSmart nem pagamento.

## 3. Migration

`2026_11_20_142301_add_campaign_tags_to_service_requests_and_assistant_conversations`:
- `utm_source`, `utm_medium` e `utm_campaign`, todos `string(100)` e nullable, em `service_requests` e em `assistant_conversations`;
- índice `(tenant_id, utm_source, created_at)` em `service_requests`.

**Por que é necessária:** nenhum campo existente comporta a campanha. `origin` é o canal, e `discovery` guarda só o portal verificado. A conversa precisa carregar as marcações até a solicitação ser registrada, como já faz com o portal.

**Compatibilidade:** os registros antigos ficam `NULL` (sem campanha) e nada é presumido para o passado. Migration aplicada somente no MySQL local.

## 4. Integridade e segurança

- **Origem validada no servidor:** o portal vem só do link assinado; a campanha é higienizada.
- **Origem preservada:** a origem é gravada na criação e nunca muda com a situação da solicitação (testado: triagem e cancelamento). Não há caminho que substitua uma origem conhecida por desconhecida.
- **Isolamento:** as marcações e a chegada ficam na sessão por empresa. Todas as consultas do painel exigem a empresa.
- **Valores financeiros:** não foram alterados.
- **Fluxos públicos e de atendimento:** preservados. As suítes de Websites e Assistant foram aprovadas.

## 5. Arquivos

**Novos:**
- `app/Services/Websites/CampaignTags.php`
- a migration da seção 3
- `docs/VETORSMART-GROWTH-03.md`

**Alterados:**
- `app/Enums/RequestOrigin.php`: `channel()`.
- `app/Models/ServiceRequest.php` e `app/Models/AssistantConversation.php`: campos UTM e `commercialOrigin()`.
- `app/Services/ServiceRequestService.php`: grava a campanha só nos canais públicos.
- `app/Services/Assistant/AssistantOperations.php`: a conversa repassa a campanha à solicitação.
- `app/Http/Controllers/Site/SiteController.php`, `SiteRequestController.php` e `AssistantController.php`: captura e repasse.
- `app/Services/Growth/GrowthMetrics.php`: `byCommercialOrigin` e `byChannel`, no lugar de `byDiscovery`.
- `app/Http/Controllers/Tenant/GrowthController.php`
- `resources/js/pages/tenant/growth/index.tsx`
- `tests/Feature/Tenancy/GrowthCommercialTest.php`: o teste de origem da GROWTH-02 foi reescrito para as duas visões.
- `tests/Feature/Websites/PortalAttributionTest.php`: 5 testes novos.

## 6. Testes direcionados

| Verificação | Resultado |
|---|---|
| `PortalAttributionTest`, com 5 testes novos: primeira marcação preservada; UTM nunca comprova o portal; marcações malformadas descartadas; portal verificado prevalece sobre a campanha e as duas são guardadas; campanha passa da IA para a solicitação, também após a passagem para a equipe; visita sem marcação e painel ficam não identificados; painel recusa campanha; origem inalterada em triagem e cancelamento | **14 de 14** |
| `GrowthCommercialTest`: origem e canal como visões separadas do mesmo grupo de oportunidades, conversão e receita por origem, campanhas por fonte, IA sem atribuição ao portal sem evidência, não identificada à parte | **12 de 12** |
| Suítes afetadas: Websites (jornada pública e portal), Assistant, Operations, Tenancy e demonstração | **519 testes: 510 aprovados, 9 ignorados (já eram antes), 0 falhas** |
| PHPStan, Pint, TypeScript e build | ok |
| Navegador headless: Meu Crescimento dos 3 Owners de demonstração (30 e 90 dias, celular com tema escuro e desktop com tema claro) | **12 de 12** telas sem rolagem horizontal e sem erros; Operador continua com **403** |

**Conferência com a base de demonstração** (ClimaSul, 90 dias):
- Origem comercial: Portal Regional 6 e Não identificada 9.
- Canal: Site — formulário 11, atendimento virtual 2 e Painel 2.
- As duas visões somam as mesmas 15 oportunidades.

## 7. Limitações e pendências

1. **Item 7 do correio:** a origem comercial, o canal e o atendimento no detalhe da solicitação, e o resumo dos canais na ficha do cliente, ficaram para depois, por sua decisão durante a execução. Os dados e os métodos já estão prontos (`commercialOrigin()` e `channel()`).
2. **Origem "direta":** não é declarada, porque não existe evidência confiável que distinga visita direta de visita sem referência. Esses casos ficam como "Não identificada".
3. **Validade da campanha:** limitada a 24 horas e à sessão do navegador do visitante. A campanha não é recuperada em outra visita nem em outro aparelho.
4. **Registros antigos:** solicitações anteriores não têm campanha. Só as que vieram pelo portal têm origem identificada, preenchida na JOURNEY-01.
5. **Painel:** não pergunta como o cliente conheceu a empresa. Uma origem declarada pela equipe exigiria um campo e uma interface novos.

## 8. Git

- `3a176da`: GROWTH-02.
- **GROWTH-03 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**

Nenhuma outra etapa foi iniciada.
