# VETORSMART-BILLING-01.1 — Consolidação das assinaturas e homologação financeira

Data: 18/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem deploy.
- Nenhuma migration na VPS.
- Nenhuma cobrança ou chamada real à Efí.

## Parecer

**APROVADO COM RESTRIÇÕES** para continuar a homologação financeira. **Não liberado para cobrança real.**

O modelo comercial de um plano com três ciclos e os créditos de IA estão implementados e testados (621 de 621 testes).

Faltam:
- credenciais de homologação da Efí (os cenários reais na Efí não foram executados);
- aprovação definitiva dos valores pelos sócios;
- definição da franquia de IA e dos pacotes;
- homologação visual do RootAdmin (não há conta root local).

## 1. Diagnóstico inicial e consolidação da BILLING-01

- O Git tinha a BILLING-01 inteira sem commit. O `correio.md`, o `executed.md` e o `pnpm-lock.yaml` estavam alterados, e o lockfile não pertence à etapa.
- A revisão das 6 migrations, do diff e dos testes não encontrou nenhuma credencial no código.
- Testes antes do commit: Pest 587 de 587 (com `VETORSMART_CONCURRENCY=1`). PHPStan 0 erros, Pint, `tsc` e build ok.
- Commits:
  - `1abad90` `feat(billing): assinaturas, cobrança recorrente Efí e gestão financeira (BILLING-01)`;
  - `fb7ce62` `docs(release): registra BILLING-01`.
- **Sem push e sem deploy.** O `correio.md`, o `executed.md` e o `pnpm-lock.yaml` ficaram fora dos commits.

## 2. Modelo comercial: um plano, três ciclos

| Ciclo | Total | Equivalente mensal | Economia sobre o mensal |
|---|---:|---:|---:|
| Mensal | R$ 139,90 | R$ 139,90 | — |
| Semestral | R$ 719,40 | R$ 119,90 | 14% |
| Anual | R$ 1.318,80 | R$ 109,90 | 21% |

- **Valores provisórios.** A migration grava esses valores só nos campos vazios e deixa o plano como "não aprovado" (`prices_approved_at` vazio).
- **Enquanto não houver aprovação, nenhuma cobrança é emitida no ambiente de produção.** A cobrança fica "Em emissão", com o aviso. Isso é testado.
- Teste gratuito de 14 dias; três usuários incluídos em qualquer ciclo.
- **Usuário adicional:** R$ 19,90 por mês, configurável e ainda não aprovado. Cobra-se cada usuário ativo acima dos incluídos, multiplicado pelos meses do ciclo. Usuários inativos não contam.
- **Renovação:** a rotina diária abre a cobrança do próximo período conforme o ciclo contratado.
- **Cancelamento:** sem mudança; o acesso continua até o fim do período pago.
- **Preços sem edição de código:** o RootAdmin altera os preços em "Plano e Efí".
- **Condições preservadas:** na primeira cobrança, o contrato congela o preço do ciclo e o preço do usuário adicional. Uma promoção válida vira desconto do contrato, com a mesma data final. Aumentos de preço do plano não afetam contratos em andamento (testado).
- **Troca de ciclo (regras explícitas, sem cobrança nem crédito proporcional):**
  - sem nada pago e sem cobrança aberta (teste ou recontratação): a troca vale na hora;
  - com uma cobrança aberta: a troca é recusada, e a empresa paga ou espera o vencimento; assim, duas cobranças nunca se sobrepõem;
  - com período pago em curso: a troca é agendada e vale no próximo período, quando a cobrança dele é criada;
  - escolher de novo o ciclo atual desfaz a troca agendada.
- **Sem duplicidade:** continua valendo uma cobrança por período (índice único, lock da assinatura e devolução da cobrança aberta).
- Cada cobrança guarda o ciclo e a composição: base, desconto, usuários adicionais e valor deles.

## 3. Créditos de inteligência artificial

**Estrutura:**
- `ai_credit_accounts` guarda os saldos, alterados sempre sob lock de linha.
- `ai_credit_entries` é o histórico imutável, com uma referência única por operação.
- `ai_credit_packages` guarda os pacotes de créditos.
- `ai_credit_purchases` guarda as compras, separadas da assinatura.

**Franquia mensal:**
- Vem do plano (`ai_monthly_credits`) e é renovada no início de cada mês, no fuso da empresa, também nos ciclos semestral e anual.
- O que sobra do mês anterior expira, com lançamento próprio no histórico.
- **Nenhum limite foi inventado:** a franquia está vazia, e sem franquia definida os créditos não são aplicados (o consumo só é registrado).

**Créditos comprados:**
- Ficam num saldo separado e não expiram na renovação.
- O consumo usa primeiro a franquia e depois os créditos comprados.

**Conversão:** tokens viram créditos pela taxa configurável (padrão provisório de 1.000 tokens por crédito), arredondando para cima.

**Histórico:**
- Por empresa, com modelo, operação, tokens e custo estimado.
- O custo só é calculado quando houver preço do modelo em `assistant.cost_per_million_tokens_cents`; hoje está vazio.

**Débito duplicado:** a mesma operação registrada duas vezes debita uma vez. Testado inclusive com concorrência em MySQL real.

**Alertas:**
- saldo baixo (padrão: 20% da franquia), uma vez por mês;
- saldo esgotado, uma vez por mês;
- ambos vão aos proprietários pela central de notificações e por e-mail.

**Bloqueio:**
- Sem saldo, só a chamada paga ao provedor de IA deixa de acontecer (classificação do serviço).
- O fluxo convencional do assistente, o atendimento humano e o resto do painel continuam (testado).
- O consumo excedente da última operação fica registrado como "sem saldo", para conciliação.

**Compras:**
- São uma cobrança separada e auditada.
- **Não estão habilitadas:** dependem de uma chave do RootAdmin, desligada por padrão. A compra fica "Aguardando pagamento", sem cobrança na Efí, e o RootAdmin confirma o pagamento.
- Cada empresa tem no máximo uma compra aguardando pagamento.
- Confirmar duas vezes credita uma vez.

**Estorno:** só dos créditos ainda não usados, com valor proporcional. O dinheiro é devolvido fora da plataforma e a operação fica registrada. Créditos já consumidos não são estornáveis.

**Conciliação:**
- Os saldos são comparados com a soma do histórico.
- O RootAdmin vê "Consistente" ou "Divergente" e o total consumido sem saldo.

## 4. Minha Assinatura

A tela mostra:
- plano, ciclo e situação;
- próxima cobrança com a composição: base, desconto, usuários adicionais e total. Com cobrança em aberto, mostra a dela, com o valor já fixado;
- vencimento e dias de teste;
- usuários ativos e incluídos;
- escolha do ciclo em cartões (total, equivalente mensal e economia), forma de pagamento, "Gerar cobrança", "Só trocar o ciclo" e cancelar ou desfazer o cancelamento;
- "Condições do plano": serviços disponíveis, teste, usuários, descontos, preço garantido, regra de troca de ciclo, cancelamento e franquia de IA;
- "Créditos de IA": franquia, saldo, comprados, consumo do mês, pacotes com "Comprar", compras e histórico de consumo;
- histórico de cobranças, com ciclo e composição.

Só o proprietário age; o administrador vê, e o operador recebe 403.

## 5. RootAdmin

- **"Plano e Efí":**
  - preços mensal, semestral e anual, com prévia de equivalente mensal e economia;
  - dias de teste, usuários incluídos e preço do adicional;
  - franquia de IA;
  - promoção (percentual e data final);
  - selo "Valores provisórios" ou "Valores aprovados", com a caixa de aprovação. **Uma mudança de preço retira a aprovação.**
- **Pacotes de créditos:** criar, editar e desligar. Nenhum pacote é criado por padrão.
- **Regras:** tokens por crédito, limite do alerta de saldo baixo e chave de compra de créditos.
- **Auditoria:** plano, regras e pacotes registram **valor anterior e novo** de cada campo alterado. Credenciais aparecem só como "alterado".
- **Detalhe da assinatura:**
  - ciclo, troca agendada e preço contratado do adicional;
  - cobranças com ciclo e usuários adicionais;
  - créditos de IA com saldos, conciliação, compras (confirmar, cancelar ou estornar, com motivo auditado) e movimentos (tokens, modelo e custo).
- **Visão Geral:** a receita mensal recorrente passou a dividir pelo número de meses do ciclo de cada assinatura.
- **Expurgo de homologação:** inclui as tabelas de créditos.

## 6. Arquivos

**Novos:**
- `app/Enums/BillingInterval.php` e `app/Enums/AiCreditPurchaseStatus.php`;
- `app/Models/AiCreditAccount.php`, `AiCreditEntry.php`, `AiCreditPackage.php` e `AiCreditPurchase.php`;
- `app/Services/Billing/AiCreditService.php`, `AiCreditPurchaseService.php` e `PlanCatalog.php` (oferta pronta para a futura landing);
- `app/Http/Requests/RootAdmin/SaveAiCreditPackageRequest.php`;
- migrations `2026_11_18_182113_add_billing_cycles_to_plans_and_subscriptions` e `2026_11_18_182114_create_ai_credit_tables`;
- `tests/Feature/Billing/BillingIntervalTest.php` (15) e `AiCreditTest.php` (16).

**Alterados:**
- models `Plan`, `Subscription`, `BillingCharge` e `BillingSetting`;
- `SubscriptionService` (troca de ciclo e congelamento das condições);
- `ChargeService` (ciclo, composição e bloqueio em produção sem aprovação);
- `AssistantFlow` (créditos);
- controllers de "Minha Assinatura", "Plano e Efí", assinaturas e Visão Geral;
- form requests;
- `routes/tenant.php` e `routes/rootadmin.php`;
- `config/assistant.php`;
- `HomologPurgeService`;
- as três telas;
- testes `AssistantTest` (+2), `BillingConcurrencyTest` (+1) e `EfiBillingTest` (payloads novos).

**Estrutura removida do plano:** `interval_months`, `ai_messages_per_month` e `promotional_price_cents`, substituídos pelos preços por ciclo, pela franquia de créditos e pela promoção percentual. A migration tem `down` testado (rollback e reaplicação no banco local).

## 7. Testes e resultados

| Verificação | Resultado |
|---|---|
| Pest completo (MySQL, `VETORSMART_CONCURRENCY=1`) | **621 de 621**, 3772 asserções (eram 587) |
| Ciclos | Valores e economia; cobrança por ciclo (mensal, semestral, anual); usuários adicionais ativos × meses; preço congelado na renovação; promoção virando desconto; troca imediata, recusada com cobrança aberta e agendada com período pago; só o proprietário troca; sem emissão em produção sem aprovação; auditoria antes/depois com retirada da aprovação; validação da promoção; cobrança aberta exibida com o valor fixado |
| Créditos de IA | Franquia mensal com expiração do saldo anterior; comprados não expiram e são usados depois da franquia; conversão com arredondamento; débito único por operação; bloqueio sem saldo com excedente registrado; não aplicado sem franquia; alertas uma vez por mês; conciliação e detecção de divergência; compra fechada por padrão; compra única aguardando pagamento e auditada; confirmação dupla credita uma vez; estorno proporcional só do não usado; recusa do estorno sem saldo; isolamento entre empresas; 403 para usuários de empresa; pacote auditado |
| Assistente | A classificação pelo provedor debita créditos; sem saldo o provedor não é chamado e o fluxo convencional segue |
| Concorrência (MySQL real) | O débito de créditos espera o lock da conta; consumos concorrentes debitam uma vez cada; conciliação consistente |
| Regressão | Toda a suíte anterior (cadastro, agenda, assistente, sites, notificações, RootAdmin, cobrança Efí) |
| PHPStan, Pint, TypeScript e build | 0 erros, ok, ok, ok |

## 8. Evidências de homologação

- **Visual de "Minha Assinatura"** (Chromium e Firefox × desktop e celular × claro e escuro): **8 de 8** sem violações de axe (WCAG 2.1 AA), sem rolagem horizontal e sem erros de JavaScript.
  - Para exibir a seção de créditos, configurei temporariamente no banco local uma franquia de 100 créditos, um pacote "[HOMOLOG]" e a compra liberada. **Tudo foi desfeito ao final.**
- **Defeito encontrado e corrigido na homologação:** com uma cobrança aberta antiga (R$ 99,00, da BILLING-01), a tela mostrava a cotação nova (R$ 139,90) como próxima cobrança. Agora mostra a cobrança aberta, com o valor fixado. Teste de regressão adicionado.
- **Cenários de homologação financeira cobertos por testes, com a Efí simulada:**
  - criação da assinatura;
  - fim do teste;
  - emissão por ciclo;
  - pagamento confirmado;
  - webhook duplicado e fora de ordem;
  - renovação;
  - carência e inadimplência;
  - suspensão e reativação;
  - cancelamento;
  - alterações de usuários e créditos;
  - falha e recuperação da Efí.
- **Efí real (homologação): não executado.** O banco local não tem credenciais (Pix e Cobranças "incompletas"). Nenhuma credencial de produção foi usada e nenhuma transação real foi gerada.
- **RootAdmin visual: não executado.** Não há conta root local, e criar conta privilegiada ficou fora do escopo.
- **Banco local:** as duas migrations novas foram aplicadas (e testadas com rollback). O plano local está com os valores provisórios e "não aprovado".

## 9. Pendências e riscos

1. **Aprovação dos valores** pelos sócios (ciclos e usuário adicional) e marcação de "Valores aprovados" no RootAdmin.
2. **Franquia mensal de IA, pacotes e preços:** decisão comercial. Sem franquia, os créditos não são aplicados.
3. **Taxa de conversão tokens/crédito e custo por modelo:** valores provisórios ou vazios até haver provedor de IA contratado.
4. **Credenciais de homologação da Efí**, webhook cadastrado e roteiro no simulador, para executar os cenários reais.
5. **Pagamento das compras de créditos pela Efí:** ainda não integrado (hoje a confirmação é manual). A devolução do valor no estorno é feita fora da plataforma.
6. **Usuários adicionais:** são cobrados pela contagem na criação de cada cobrança. Quem entra no meio do período paga a partir do período seguinte, sem cobrança proporcional. Precisa de validação comercial.
7. **Créditos e contrato:** a franquia segue o plano e não é congelada no contrato. Uma redução vale para todos no mês seguinte.
8. Homologação visual do RootAdmin (conta root local).
9. Deploy: migrations das PANELS-02 e 04, BILLING-01 e BILLING-01.1, `APP_URL=https://vetorsmart.com.br`, SMTP, worker e scheduler.

## 10. Estado do Git

- `main` com os commits `1abad90` e `fb7ce62` (BILLING-01), **sem push**.
- **A BILLING-01.1 está sem commit**, aguardando autorização.
- Fora do escopo e não tocado: `pnpm-lock.yaml`. O `correio.md` e o `executed.md` estão alterados.

## Parecer de liberação

- **Liberado** para seguir a homologação financeira em ambiente isolado.
- **Não liberado** para cobrança real nem para venda de créditos: depende das pendências 1 a 5.
