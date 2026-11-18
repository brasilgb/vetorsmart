# VETORSMART-BILLING-01 — Assinaturas e cobrança recorrente

Data: 18/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`. Sem deploy, sem migration na VPS e sem nenhuma chamada real à Efí: todas as integrações foram testadas com respostas simuladas e endereços de homologação.

## Parecer

**APROVADO COM RESTRIÇÕES** para a homologação financeira.

A estrutura comercial está implementada e testada (587 de 587):

- plano configurável;
- assinatura com teste de 14 dias, carência, suspensão financeira, reativação e cancelamento, com histórico;
- integração Efí (Pix e boleto com Pix) com webhooks autenticados e idempotentes;
- conciliação diária;
- "Minha Assinatura";
- gestão no RootAdmin.

**Não está pronto para cobrança real:**

- faltam credenciais de homologação da Efí, o webhook cadastrado e o SMTP de produção;
- os valores comerciais não foram aprovados (o plano está sem preço, e por isso a contratação fica fechada);
- a homologação visual do RootAdmin continua pendente (conta local).

## Etapa 1 — Consolidação da PANELS-04

- Revisão do diff e das 3 migrations. Testes: 552 de 552. PHPStan: 0 erros. Pint: ok. Nenhuma credencial no diff.
- Commits:
  - `ef89f9a` `feat(notificacoes)`;
  - `6468d1c` `docs(release)`.
- Push: `58da0e0..6468d1c` na `origin/main`. **Sem deploy.** O `correio.md` e o `pnpm-lock.yaml` ficaram fora.

## Etapa 2 — Auditoria da Efí

### VetorOS e VetorSales (somente leitura; nada alterado)

- Os dois usam **clientes HTTP próprios**, sem SDK:
  - `EfiPixClient`: OAuth2 com mTLS, certificado PEM gravado em arquivo temporário 0600 só durante a chamada;
  - `EfiChargesClient`: `/v1/authorize`, `/v1/charge/one-step`, `/v1/notification/:token`.
- As credenciais ficam numa tabela de configurações com cast `encrypted`.
- O webhook Pix é autenticado por **segredo na URL**, e cada aviso é **confirmado consultando a cobrança na API**.
- O pagamento é quitado de forma idempotente, conferindo o valor recebido.
- Uma cobrança por competência, sem recorrência nativa da Efí.

**Reaproveitamento:** o desenho foi reescrito no VetorSmart (`app/Services/Billing/Efi/`), com tabelas próprias. **Nenhuma dependência entre bancos e nenhuma credencial compartilhada.**

### Documentação atual da Efí (dev.efipay.com.br, consultada em 18/11/2026)

| Modalidade | Recorrência automática | Como funciona |
|---|---|---|
| **Boleto (Bolix)** | Sim, pela API Cobranças | `POST /v1/plan` (interval em meses, repeats) e `POST /v1/plan/:id/subscription/one-step`. A Efí envia cada boleto por e-mail 10 dias antes do vencimento. |
| **Cartão** | Sim, pela API Cobranças | Mesmos endpoints. O cartão é informado só no primeiro pagamento, por **tokenização no navegador** (script da Efí); as cobranças seguintes são automáticas. Recobrança com `POST /v1/charge/:id/retry`. |
| **Pix imediato (cob)** | **Não** | Uma cobrança por vez (`PUT /v2/cob/:txid`). |
| **Pix Automático** | Sim, mas com jornada própria | Endpoints `/v2/rec`, `/v2/solicrec`, `/v2/cobr` e `/v2/locrec`. O pagador autoriza a recorrência no app do banco (QR Code ou notificação). Exige **Conta Digital Efí Empresas** e os escopos `rec.*`, `solicrec.*` e `cobr.*`. Tem webhooks próprios (`/v2/webhookrec` e `/v2/webhookcobr`) e política de retentativa (`PERMITE_3R_7D`). |

Avisos (API Cobranças):
- o callback traz **só um token**, sem assinatura;
- os detalhes vêm de `GET /v1/notification/:token`;
- a Efí reenvia até que a consulta seja feita;
- a ordem dos eventos não é garantida (é preciso usar o `id` do evento).

Webhook Pix:
- exige mTLS no servidor, ou uma alternativa recomendada pela própria Efí: **segredo (HMAC) na URL** mais validação do IP de origem;
- a Efí acrescenta `/pix` ao endereço;
- até 10 tentativas.

### Decisão para esta etapa

**Uma cobrança por período, emitida pelo VetorSmart**, em Pix imediato ou boleto com Pix. A renovação é gerada pela rotina diária. Assim:

- a regra é a mesma para todas as formas de pagamento;
- não se presume que Pix, boleto e cartão tenham a mesma recorrência;
- dá para migrar depois para a recorrência nativa de cada modalidade (Pix Automático, assinatura de boleto ou de cartão), sem trocar o modelo de dados.

## Etapa 3 — Modelo comercial

- **Tabela `plans`** com o plano principal (`principal`, "VetorSmart"). Parâmetros:
  - mensalidade;
  - período de cobrança (1, 3, 6 ou 12 meses);
  - **dias de teste (14)**;
  - usuários incluídos;
  - valor do usuário adicional;
  - limite de mensagens de IA por mês;
  - valor promocional com data final.
- **Sem valores fixados:** a migration cria o plano **sem preço**. Com isso, o teste funciona e a contratação fica fechada, com a explicação "valor sendo definido". O RootAdmin define os valores na tela "Plano e Efí".
- Valores sempre em **centavos inteiros**.
- O preço é congelado no contrato quando negociado (`subscriptions.price_cents`), e o desconto tem percentual e data final.
- O limite de IA e o de usuários estão **preparados** (configuráveis e exibidos), mas **não são aplicados** ainda.

## Etapa 4 — Assinaturas

`SubscriptionService`: cada mudança passa por lock de linha, transição validada, histórico (`subscription_events`, com origem: empresa, administração, Efí ou rotina diária) e auditoria.

| Situação | Acesso ao painel | Sai para |
|---|---|---|
| Em teste (`trialing`) | Sim | Ativa (pagamento), Pagamento pendente (fim do teste), Cancelada |
| Ativa | Sim | Pagamento pendente (fim do período), Cancelada |
| Pagamento pendente (`past_due`, carência) | Sim, com aviso | Ativa, Suspensa (fim da carência), Cancelada |
| Suspensa por falta de pagamento | **Não**, exceto "Minha Assinatura" | Ativa (regularização), Cancelada |
| Cancelada | **Não**, exceto "Minha Assinatura" | Em teste, Ativa (nova contratação) |

**Funcionamento:**

- **Criação:** o cadastro público inicia o teste de 14 dias.
- **Empresas antigas:** as que existiam antes (sem assinatura) mantêm o acesso livre até o RootAdmin iniciar o teste. Nenhum relógio foi ligado sem decisão.
- **Contratação:** o proprietário escolhe Pix ou boleto e gera a cobrança do período seguinte ao teste (pode pagar antecipado).
- **Renovação:** a rotina diária abre a cobrança do próximo período alguns dias antes do vencimento (padrão 7, configurável), se houver forma de pagamento escolhida.
- **Carência:** padrão de 5 dias após o fim do período, configurável.
- **Suspensão financeira:** ao fim da carência.
- **Reativação:** o pagamento da cobrança em aberto reativa. Se o período cobrado já terminou, o pagamento compra um período inteiro a partir da data do pagamento.
- **Cancelamento:**
  - pela empresa, ao fim do período (pode ser desfeito);
  - pelo RootAdmin, imediato e com motivo.
- **Separação das suspensões:** a suspensão financeira está em `subscriptions.status`, e a administrativa continua em `tenants.status`. Nenhuma altera a outra (testado), e a console mostra as duas separadamente.
- **Avisos aos proprietários** (sempre por e-mail, pela central da PANELS-04): teste terminando em 3 dias, pagamento pendente, suspensão, regularização e cancelamento.

## Etapa 5 — Integração Efí (homologação)

- **Credenciais:** tabela `billing_settings`. Client ID e secret (Pix e Cobranças), certificado PEM e segredo do webhook ficam **criptografados** (cast `encrypted`, conferido no banco) e ocultos na serialização. São **somente escrita** na tela (campo vazio mantém o valor). A auditoria registra só os nomes dos campos.
- **Ambiente:** começa em **homologação** (`pix-h` e `cobrancas-h`). Mudar para produção exige uma confirmação explícita.
- **Criação de cobranças:**
  - Pix: `PUT /v2/cob/{txid}` com `txid` derivado do id local. Repetir a emissão não cria outra cobrança, e uma emissão interrompida é recuperada consultando o próprio `txid`.
  - Boleto com Pix: `POST /v1/charge/one-step`, com CPF ou CNPJ (pedido na tela quando falta), `custom_id` e `notification_url` com segredo.
- **Uma cobrança por período:** índice único (assinatura, início do período). Pedir de novo devolve a mesma cobrança.
- **Identificação dos pagamentos:**
  - webhook Pix (`/api/webhooks/efi/{segredo}/pix`);
  - webhook de Cobranças (`/api/webhooks/efi/{segredo}/cobrancas`, que lê os eventos pela API).
- **Autenticação:** segredo comparado em tempo constante. Segredo inválido → 401, sem ler nada.
- **Idempotência:** `billing_webhook_events` com chave única (`endToEndId` do Pix ou token mais id do evento). Um aviso repetido é registrado e ignorado.
- **Conciliação:** o estado é **sempre lido na Efí** (`GET /v2/cob/{txid}` ou `GET /v1/charge/{id}`). Um aviso forjado ou prematuro não quita nada. Paga é estado final: um aviso fora de ordem nunca reverte. Valor menor que o cobrado não quita e fica para conciliação manual.
- **Falhas:**
  - a emissão falha → a cobrança fica "Em emissão", com o erro (sem credenciais no texto);
  - a rotina diária tenta de novo (até 10 vezes);
  - aviso de Cobranças ilegível → 503, para a Efí reenviar;
  - falhas aparecem no RootAdmin.
- **Recuperação após indisponibilidade:** `billing:run` (diário, 05:30, `withoutOverlapping`) emite as pendentes e concilia as abertas.
- **Nenhuma cobrança real:** os testes usam `Http::fake` e verificam que nenhuma chamada vai para os endereços de produção.

## Etapa 6 — Painel do assinante: "Minha Assinatura"

- Plano, valor (ou "A definir"), desconto e próximo vencimento (fim do teste, vencimento ou prazo da carência).
- Dias restantes do teste, situação financeira e usuários incluídos.
- Avisos de cancelamento agendado e de suspensão.
- Histórico de cobranças (paginado), com Pix copia e cola (botão copiar), link e PDF do boleto (só endereços https) e o estado da emissão.
- Ações (só o proprietário): gerar a cobrança (Pix ou boleto), agendar o cancelamento e desfazê-lo.
- O administrador vê, mas não age. O operador não acessa (403) e, com a assinatura bloqueada, vê uma tela explicativa (402).
- No menu, "Minha Assinatura" aparece para proprietário e administrador.

## Etapa 7 — RootAdmin

- O menu **"Assinaturas e Financeiro" foi ativado**.
- **Lista:**
  - contagem por situação;
  - filtros por situação e empresa;
  - selos de homologação e de suspensão administrativa;
  - cobranças com falha de emissão;
  - avisos da Efí com falha.
- **Detalhe:**
  - situação e períodos;
  - condições (valor negociado e desconto);
  - prorrogar o teste;
  - cancelar com motivo;
  - cobranças com sincronização com a Efí e baixa manual com motivo (auditada);
  - histórico completo.
- **Detalhe da empresa:** cartão "Assinatura", com "Iniciar período de teste" para empresas antigas.
- **"Plano e Efí":**
  - parâmetros do plano;
  - ambiente, chave Pix e credenciais somente escrita;
  - carência, antecedência da renovação e vencimento do boleto;
  - "Testar conexão" (só autentica, não cria cobrança);
  - endereço do webhook Pix para cadastrar na Efí.
- **Visão Geral:** os indicadores antes "pendentes" agora são reais, sem as empresas de homologação: receita mensal recorrente, ativas, em teste, em carência, suspensas e cobranças em aberto.
- **Expurgo de homologação:** passou a incluir as tabelas de cobrança.

## Etapa 8 — Qualidade e segurança

| Verificação | Resultado |
|---|---|
| Pest completo (MySQL, `VETORSMART_CONCURRENCY=1`) | **587 de 587**, 3597 asserções (eram 552) |
| Novos: `Billing/SubscriptionLifecycleTest` (10), `EfiBillingTest` (22), `BillingConcurrencyTest` (3, MySQL real) | ok |
| Isolamento | A empresa vê só as próprias cobranças; usuários de empresa recebem 403 nas rotas do RootAdmin |
| Concorrência e idempotência | Abrir cobrança espera o lock da assinatura (MySQL real); o banco recusa a segunda cobrança do mesmo período; duas confirmações simultâneas quitam uma vez |
| Transições | Transição não permitida é recusada sem alterar nada; teste → carência → suspensão; cancelamento ao fim do período |
| Webhooks inválidos | Segredo errado → 401 sem ler nada; aviso não confirmado pela Efí não quita; aviso duplicado é processado uma vez; valor menor não quita; aviso tardio não reverte |
| Falha e recuperação da Efí | 503 na emissão → cobrança pendente com erro sem credenciais → a rotina diária emite depois; Cobranças ilegível → 503 para reenvio |
| Permissões | Operador 403; administrador só vê; proprietário age; troca para produção exige confirmação; tela de configuração não devolve nenhum segredo; credenciais criptografadas no banco |
| PHPStan | 0 erros |
| Pint, TypeScript e build | ok |
| **Navegador: funcional (Chromium)** | **9 de 9**:<br>• cadastro inicia o teste;<br>• confirmação entregue **pela fila com o worker local**;<br>• "Em teste" com 14 dias restantes;<br>• sem preço, contratação fechada com explicação;<br>• menu;<br>• com um preço de teste local, o formulário aparece e, **sem credenciais da Efí, a cobrança fica "Em emissão"** com o erro "credenciais não configuradas";<br>• sem erros de JavaScript. |
| **Navegador: visual de "Minha Assinatura"** | **8 de 8**: Chromium e Firefox × desktop e celular × claro e escuro, 0 violações de axe, sem rolagem horizontal. Antes da correção do selo "Em teste" no escuro, eram 6 de 8. |

**Defeitos encontrados e corrigidos durante a etapa:**
1. Contraste do selo "Em teste" no tema escuro.
2. Criação da configuração de cobrança por atribuição em massa.
3. Tipos de data imutáveis na rotina.
4. Expurgo de homologação sem as tabelas novas.

**Achado de ambiente (confirma uma pendência de produção):** com a fila, os links de e-mail são gerados no worker a partir do `APP_URL`, e a assinatura do link inclui o host. No ambiente local, `APP_URL=http://localhost:8000`, e um servidor em outra porta recusaria o link. **Em produção, `APP_URL` precisa ser `https://vetorsmart.com.br`.**

**Dados locais criados:**
- empresa "Homologacao Billing 01" (marcada como homologação), com assinatura em teste e uma cobrança pendente;
- usuário `homolog.billing01.owner@example.test`.

O preço de teste do plano foi **removido ao final** (o plano voltou a ficar sem preço). As migrations foram aplicadas **só no banco local**.

## Limitações da API Efí (para o desenho)

1. O Pix imediato não recorre. A recorrência no Pix é o **Pix Automático**, que exige Conta Digital Efí Empresas, autorização do pagador no banco e webhooks e escopos próprios.
2. O cartão exige tokenização no navegador (script da Efí) e a "API de Emissão" habilitada. **Não foi oferecido nesta etapa.**
3. O callback de Cobranças não é assinado. A autenticação depende do segredo na URL e da consulta à API.
4. O webhook Pix exige mTLS no servidor, ou a alternativa de segredo na URL mais validação de IP. Esta implementação usa o segredo. **A validação do IP (34.193.116.226) fica para o Nginx.**
5. A notificação de Cobranças cobre 6 meses de eventos, e as tentativas da Efí são finitas (até 10 no Pix e até 3 dias em Cobranças). Por isso existe a conciliação diária.

## Requisitos para a homologação financeira

1. Conta Efí com **aplicação de homologação** (API Pix e API Cobranças), certificado `.p12` convertido em PEM e chave Pix de homologação.
2. Cadastrar as credenciais em RootAdmin → "Plano e Efí" e usar **"Testar conexão"**.
3. Cadastrar o webhook Pix na Efí com o endereço exibido na tela. Opcionalmente, restringir no Nginx o caminho `/api/webhooks/efi/` ao IP da Efí.
4. Definir um **valor de teste** no plano (no ambiente de homologação) e executar o roteiro:
   - gerar Pix e boleto;
   - pagar no simulador de homologação;
   - confirmar a quitação pelo webhook e pela rotina;
   - testar o cancelamento e a suspensão.
5. **Aprovar os valores comerciais** (mensalidade, usuários incluídos e adicionais, limite de IA, promoção).
6. Pré-requisitos já pendentes:
   - SMTP de produção, SPF, DKIM, DMARC e `APP_URL`;
   - worker supervisionado;
   - agendamento do `schedule:work` ativo (o `billing:run` roda às 05:30).
7. Deploy das PANELS-02, 03 e 04 e da BILLING-01, com as migrations:
   - 2 da PANELS-02;
   - 3 da PANELS-04;
   - 6 da BILLING-01.
8. Só então mudar para produção (com a confirmação explícita da tela).

## Pendências

- Homologação visual do RootAdmin (sem conta local de root).
- Cartão, Pix Automático e assinatura nativa de boleto: próximas etapas, se aprovadas.
- Aplicação dos limites de usuários e de IA do plano.
- Política para o site público e o atendimento de empresas com assinatura suspensa (hoje continuam no ar).
- Notas fiscais: fora do escopo.

## Arquivos (BILLING-01, sem commit)

**Aplicação:**
- Enums: `SubscriptionStatus`, `ChargeStatus`, `PaymentMethod` e `BillingSource`
- Models: `Plan`, `BillingSetting`, `Subscription`, `SubscriptionEvent`, `BillingCharge` e `BillingWebhookEvent`
- Serviços em `app/Services/Billing/`: `SubscriptionService`, `ChargeService`, `BillingWebhookService`, `BillingCycle` e `Efi/*`
- Comando `billing:run`
- Middleware `EnsureSubscriptionAllowsAccess`
- Controllers: `Tenant/SubscriptionController`, `RootAdmin/SubscriptionController`, `RootAdmin/BillingSettingsController` e `Billing/EfiWebhookController`
- 3 form requests
- Alterados: `TenantPolicy`, `OperationalNotifier`, `TenantRegistrationService`, `HomologPurgeService`, `RootAdmin/DashboardController`, `RootAdmin/TenantController` e `bootstrap/app.php`

**Rotas:**
- `web`, `tenant`, `rootadmin`, `api` e `console`

**Banco:**
- 6 migrations

**Interface:**
- `tenant/subscription/index` e `blocked`
- `rootadmin/subscriptions/index` e `show`
- `rootadmin/billing/settings`
- Ajustes no menu, no painel e no detalhe da empresa

**Testes:**
- `tests/Feature/Billing/` (3 arquivos)
- Ajuste em `RootAdmin/ConsoleTest`, porque os indicadores pendentes viraram reais
