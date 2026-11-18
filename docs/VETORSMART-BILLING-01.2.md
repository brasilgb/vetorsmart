# VETORSMART-BILLING-01.2 — Consolidação financeira, contratos e homologação do RootAdmin

Data: 18/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem push.
- Sem deploy.
- Nenhuma migration na VPS.
- Nenhuma chamada real à Efí.

**Observação sobre o correio:** o `correio.md` recebido termina na seção 5 ("Franquia e regras de consumo"), sem as seções seguintes. Executei integralmente as seções 1 a 5. Para a parte cortada, segui o objetivo da seção 1 ("preparar a integração Efí para uma futura homologação real") e o padrão de entrega das etapas anteriores.

## Parecer

**APROVADO COM RESTRIÇÕES.**

- A BILLING-01.1 está consolidada.
- As vagas de usuários agora são contratadas à parte do cadastro.
- O RootAdmin financeiro foi homologado.

**Continua não liberado para cobrança real**, por falta de:
- aprovação dos valores;
- credenciais de homologação da Efí;
- decisão sobre franquia e pacotes de IA.

## 1. Consolidação Git (BILLING-01.1)

- **Validação:** 621 de 621 testes (MySQL, concorrência ligada). PHPStan 0 erros, Pint, `tsc` e build ok. Nenhuma credencial ou dado sensível no diff.
- **Commits locais:**
  - `dc0341f` `feat(billing): ciclos mensal, semestral e anual e créditos de IA (BILLING-01.1)`;
  - `a06ed7d` `docs(release): registra BILLING-01.1`.
- **Sem push e sem deploy.** O `correio.md` e o `executed.md` ficaram fora. O `pnpm-lock.yaml` já estava alterado antes, não pertence à etapa e foi preservado sem descarte.

## 2. Contratos e usuários adicionais

Os valores provisórios foram mantidos: mensal R$ 139,90, semestral R$ 719,40, anual R$ 1.318,80, 3 usuários incluídos, adicional de R$ 19,90 por mês e 14 dias de teste.

**Mudança de regra:** antes, a cobrança contava os usuários ativos acima dos incluídos. **Agora, cadastrar usuários e contratar vagas são ações separadas.**

- A assinatura tem `extra_seats` (vagas adicionais contratadas) e `scheduled_extra_seats` (redução agendada).
- O limite de usuários ativos é `incluídos + adicionais contratadas`. Uma empresa sem assinatura (antiga) ou um plano sem usuários incluídos não tem limite.
- O limite é aplicado no `TenantUserService`, que atende o painel e a API, ao **cadastrar** e ao **reativar** usuários. Com todas as vagas ocupadas, a ação é recusada com a explicação. O lock da empresa serializa ativações simultâneas.
- A cobrança usa **as vagas contratadas**, não os usuários cadastrados.

**Cenários documentados:**

| Cenário | Comportamento |
|---|---|
| Trocar um usuário por outro (desativar e cadastrar ou reativar) dentro das vagas | Permitido, **sem cobrança** |
| Cadastrar ou reativar acima das vagas | **Recusado**: "Todas as N vagas de usuário estão em uso…" |
| Contratar mais vagas (em teste ou com período pago) | Vale **na hora**. Entra nas **próximas cobranças geradas**. **Cobranças abertas não mudam.** **Sem valor proporcional** (regra comercial não aprovada) |
| Reduzir vagas sem período pago e sem cobrança aberta | Vale na hora |
| Reduzir vagas com período pago em curso ou cobrança aberta | **Agendado** para o próximo período (as vagas do período atual já foram pagas). Enquanto espera, as ativações respeitam o limite reduzido |
| Reduzir abaixo dos usuários ativos | **Recusado** até desativar usuários |
| Contratos existentes | Preço do adicional congelado (BILLING-01.1). A migration inicia `extra_seats` com o excedente que cada assinatura já usava, sem bloquear ninguém e sem mudar valores |

- Toda alteração gera evento no histórico da assinatura e registro na auditoria.
- A cobrança dos adicionais continua sujeita à aprovação comercial: sem "Valores aprovados", nada é emitido em produção.

**Telas:**
- "Minha Assinatura" ganhou o cartão "Vagas de usuários" (incluídas, adicionais, em uso, redução agendada e alteração pelo proprietário).
- "Usuários e permissões" mostra o motivo da recusa por falta de vaga.
- O detalhe da assinatura no RootAdmin mostra as vagas.

## 3. Créditos de IA

A arquitetura da BILLING-01.1 foi mantida. Itens verificados, todos cobertos por testes automatizados:

- franquia renovada mensalmente, também nos ciclos semestral e anual;
- expiração só da franquia não usada;
- créditos comprados separados e sem expiração;
- débito único por operação, inclusive com concorrência em MySQL real;
- isolamento entre empresas;
- histórico com modelo, operação, tokens e custo;
- bloqueio só da chamada paga de IA, com o atendimento convencional continuando;
- estornos sem duplicidade e conciliação.

**Relatório técnico:** `docs/VETORSMART-BILLING-01.2-CREDITOS-IA.md`.
- Nenhum provedor de IA está integrado hoje, então os custos são faixas hipotéticas.
- Proposta: franquia de 500 créditos por mês e pacotes de 500, 2.000 e 5.000 créditos (R$ 14,90, R$ 49,90 e R$ 109,90).
- Esses pacotes só são viáveis com modelo das faixas econômica ou intermediária.
- **Nada foi ativado:** franquia vazia, nenhum pacote e compra desligada.

## 4. Homologação do RootAdmin

Conta root **local** criada com o comando do projeto (`platform:root-admin`), com senha aleatória guardada só no scratchpad da sessão. Ela não existe fora do banco local.

**Visual** (Chromium e Firefox × desktop e celular × claro e escuro). Telas: "Plano e Efí", lista de assinaturas, detalhe da assinatura e Visão Geral.
- Resultado final: **32 de 32**, sem violações de axe (WCAG 2.1 AA), sem rolagem horizontal e sem erros de JavaScript.
- Defeitos encontrados e corrigidos:
  1. Link "dados da empresa" sem sublinhado no meio do texto (`link-in-text-block`).
  2. Tabelas com rolagem horizontal sem foco por teclado no celular (`scrollable-region-focusable`). Agora são regiões nomeadas e focáveis.

**Funcional** (Chromium):

| Fluxo | Resultado |
|---|---|
| Configuração de ciclos e preços | Prévia de equivalente mensal e economia correta |
| Aprovação comercial | Selo muda para "Valores aprovados em …" |
| Revogação da aprovação ao mudar um preço | Selo volta para "Valores provisórios". **Defeito corrigido:** a caixa "Valores aprovados" continuava marcada depois da revogação; agora reflete o que o servidor gravou |
| Usuários incluídos e preço do adicional | Salvos e auditados |
| Franquia e regras de consumo | Franquia, tokens por crédito e alerta de saldo baixo salvos |
| Pacote de créditos | Criado e listado |
| Promoção acima de 90% | Recusada com mensagem |
| Detalhe da assinatura | Vagas e créditos exibidos |

**Banco local restaurado ao final:** plano com os valores provisórios, "não aprovado" e sem franquia. Pacotes "[HOMOLOG]" e lançamentos de créditos criados pela visita foram removidos. Os registros de auditoria das ações ficaram, por ser histórico imutável.

## 5. Preparação da Efí para homologação real

Nenhuma credencial existe localmente (API Pix e API Cobranças "incompletas"). Roteiro para quando houver credenciais **de homologação**:

1. Em "Plano e Efí", manter "Homologação" e informar Client ID e Secret das duas APIs, o certificado PEM (convertido do `.p12` de homologação) e a chave Pix.
2. Clicar em "Testar conexão": só autentica, sem criar cobrança.
3. Cadastrar na Efí o webhook Pix exibido na tela (a Efí acrescenta `/pix`). Exige um domínio público com HTTPS, então não roda em `localhost`.
4. Executar no simulador: cobrança Pix paga, boleto pago, webhook repetido, pagamento a menor e expiração. Conferir a conciliação diária (`billing:run`) e o histórico.
5. Só depois disso, e com os valores aprovados, considerar produção.

## 6. Arquivos

**Novos:**
- `app/Services/Billing/SeatService.php`;
- migration `2026_11_18_194419_add_extra_seats_to_subscriptions_table`;
- `tests/Feature/Billing/SeatTest.php` (9 testes);
- `docs/VETORSMART-BILLING-01.2-CREDITOS-IA.md`.

**Alterados:**
- `app/Models/Subscription.php`;
- `app/Services/Billing/SubscriptionService.php`;
- `app/Services/TenantUserService.php`;
- `app/Http/Controllers/Tenant/SubscriptionController.php`;
- `app/Http/Controllers/RootAdmin/SubscriptionController.php`;
- `routes/tenant.php`;
- `resources/js/pages/tenant/subscription/index.tsx`;
- `resources/js/pages/tenant/team/index.tsx`;
- `resources/js/pages/rootadmin/subscriptions/show.tsx`;
- `resources/js/pages/rootadmin/billing/settings.tsx`;
- `tests/Feature/Billing/BillingIntervalTest.php` (dois testes adaptados à regra de vagas).

## 7. Testes

| Verificação | Resultado |
|---|---|
| Pest completo (MySQL, `VETORSMART_CONCURRENCY=1`) | **630 de 630**, 3806 asserções |
| Vagas | Recusa acima do limite (com a mensagem); troca sem cobrança; reativação recusada; vaga extra vale na hora sem mudar a cobrança aberta; redução agendada e aplicada na renovação; ativações respeitam a redução agendada; redução abaixo dos ativos recusada (com a mensagem); só o proprietário contrata; empresa sem assinatura sem limite |
| PHPStan, Pint, TypeScript e build | 0 erros, ok, ok, ok |
| Visual do RootAdmin | 32 de 32 |
| Funcional do RootAdmin | 7 de 7 fluxos |

## 8. Pendências e riscos

1. Aprovação dos valores e da regra de vagas adicionais (sem proporcional no meio do período).
2. Escolha do provedor/modelo de IA, tokens reais e preço; depois, franquia, pacotes e liberação da compra.
3. Credenciais de homologação da Efí e domínio público para o webhook.
4. Integração do pagamento de créditos com a Efí (hoje a confirmação é manual).
5. Deploy: migrations das PANELS-02 e 04, BILLING-01, 01.1 e 01.2, `APP_URL`, SMTP, worker e scheduler.
6. Conta root local de homologação: remover quando não for mais necessária.

## 9. Estado do Git

- `main` com `dc0341f` e `a06ed7d` (BILLING-01.1) sobre a BILLING-01. **Sem push.**
- **A BILLING-01.2 está sem commit**, aguardando autorização.
- A migration nova foi aplicada só no banco local.
- `pnpm-lock.yaml` preservado, fora do escopo.
