# VETORSMART-ROOTADMIN-01 — Consolidação administrativa, UX e governança

Data: 11/11/2026. Local: `gateway/vetorsmart/`.

## 1. Inventário inicial

### Existente e aproveitado

| Área | Situação | Onde |
|---|---|---|
| Autenticação e gate de acesso | `platform-admin` separado de papéis de tenant | `routes/rootadmin.php`, `User::is_root_admin` |
| Visão geral | Totais reais, distribuição por segmento, cadastros recentes | `RootAdmin/DashboardController` |
| Empresas | Lista com filtros, detalhe, suspensão/ativação com confirmação e auditoria | `RootAdmin/TenantController`, `TenantStatusService` |
| Segmentos | Criação, edição e desativação (nunca exclusão) | `RootAdmin/SegmentController`, `SegmentService` |
| Usuários e acessos | Leitura de vínculos, sem senhas e sem alteração de permissão | `RootAdmin/UserController` |
| Auditoria | Trilha paginada e filtrável, metadados sanitizados | `RootAdmin/AuditLogController`, `AuditLogger` |
| Consumo de IA | Tabela diária por tenant (mensagens, chamadas, falhas, tokens) | `assistant_usage` |
| Sites e domínios | Tabelas `tenant_websites`, `tenant_domains` (sem tela RootAdmin) | migrations de 10/11 |

### Incompleto

- **Sites e domínios**: a plataforma tem os dados, mas o RootAdmin não possui tela própria. Apenas o detalhe da empresa exibe o domínio principal e o estado do site.
- **Usuários e acessos**: somente leitura. Não há gestão de permissões globais nem convites.
- **Dashboard**: não possuía indicadores de site, agendamento nem de IA.

### Ausente

- **Assinaturas, planos e cobrança**: não existe modelo de plano, ciclo ou assinatura. Nenhuma cobrança é feita.
- **Créditos de IA**: não há saldo, compra, franquia nem conversão de tokens.
- **Situação de teste** para empresas: `TenantStatus` tem apenas `active`, `suspended`, `cancelled`.
- **Incidentes operacionais**: não há registro.
- **MFA para administradores globais**: Fortify está sem `twoFactorAuthentication` habilitado. Ver §6.
- **Crescimento e SEO, Suporte, Configurações Globais**: sem telas no RootAdmin.

## 2. Implementações realizadas

### Navegação do RootAdmin
- Menu reorganizado em 11 áreas, na ordem do brief.
- Áreas sem tela funcional aparecem como **"Em breve"**, desabilitadas e sem link. Nenhuma aparenta estar operacional.
- Arquivos: `resources/js/components/app-sidebar.tsx`, `resources/js/components/nav-main.tsx`, `resources/js/types/navigation.ts`.

### Visão geral com indicadores reais
- Empresas cadastradas, ativas, suspensas; usuários; **sites publicados** (site publicado com versão ativa); **agendamentos futuros**.
- **Atendimento IA do mês corrente**: mensagens, chamadas ao provedor, falhas e tokens, a partir de `assistant_usage`.
- Bloco **"Indisponíveis no momento"** com a lista explícita de indicadores que dependem de módulos ainda não existentes: receita recorrente, pagamentos pendentes, empresas em teste, custo estimado de IA e incidentes. Nenhum número é estimado.
- Arquivos: `app/Http/Controllers/RootAdmin/DashboardController.php`, `resources/js/pages/rootadmin/dashboard.tsx`.

### Detalhe da empresa
- Novo bloco **Operação** (somente leitura): usuários ativos; limite contratado marcado como não definido; domínio principal e estado; site e estado de publicação; atendimento IA ativado ou não.
- Novo bloco **Histórico administrativo**: últimas 20 ações sobre a empresa (ação, responsável, data). Não inclui conteúdo de atendimento nem metadados.
- Arquivos: `app/Http/Controllers/RootAdmin/TenantController.php`, `resources/js/pages/rootadmin/tenants/show.tsx`.

### Planos (somente configuração)
- `config/platform.php` ganhou `plans` (Profissional: 1 usuário, R$ 89,90; Premium: 5 usuários, R$ 189,90), `additional_user_monthly_price_cents` (R$ 19,90) e `billing_cycles` (mensal, semestral, anual).
- Valores vêm de variáveis de ambiente em centavos, para mudar sem deploy.
- Descontos por ciclo não foram definidos no brief: padrão `0`, configurável por ambiente.
- **Nenhuma cobrança é ativada.** Nenhuma tela consome essa configuração ainda.

## 3. Testes executados

| Comando | Resultado |
|---|---|
| `php artisan test --compact tests/Feature/RootAdmin tests/Feature/Tenancy` | 73 aprovados, 0 falhas |
| `php artisan test --compact` (suíte completa) | 313 testes: 303 aprovados, 2 falhas, 8 pulados |
| `vendor/bin/pint --dirty` | aprovado |
| `npx tsc --noEmit` | 0 erros |
| `npm run build` | aprovado |

As 2 falhas são de `PlatformStartTest` (landing: textos "Ir para o painel" e "Em desenvolvimento"). Já existiam antes desta etapa, vindas das alterações não commitadas da landing. Esta etapa não alterou a landing.

Testes novos em `tests/Feature/RootAdmin/ConsoleTest.php`:
- indicadores de IA do mês corrente, sem somar meses anteriores;
- lista de indicadores indisponíveis presente;
- detalhe da empresa com estado operacional e histórico administrativo após suspensão.

## 4. Evidências visuais

**Não realizadas.** A homologação no navegador em 1440 px e 390 px não foi feita nesta execução. O build e os testes não substituem essa verificação.

## 5. Problemas de UX identificados

- Visão geral misturava números sem origem clara. Agora os indicadores são separados em "Empresas e uso", "Atendimento IA" e "Indisponíveis no momento".
- Menu tinha itens com aparência de funcionais sem tela. Agora são marcados como "Em breve".
- Detalhe da empresa não mostrava o que a empresa tinha configurado de operação nem o histórico de ações administrativas.
- Não validado: contraste, navegação por teclado e layout de 390 px dos novos blocos.

## 6. Pendências técnicas

1. **MFA obrigatório para administradores globais: não implementado.** Requisito do brief e falha de segurança para aceite. Exige habilitar `twoFactorAuthentication` no Fortify, fluxo de ativação e tratamento de recuperação. Não foi feito nesta etapa por exigir decisão de fluxo.
2. **Modelo de assinaturas e cobrança**: sem tabelas. Precisa de especificação e homologação financeira antes de implementar.
3. **Créditos de IA**: sem saldo, franquia ou conversão de tokens. A conversão depende de medir o custo real.
4. **Limite de usuários por plano**: exibido como "não definido" até existir assinatura.
5. **Custo estimado de IA**: exige tabela de preços do provedor. Não definida.
6. **Tela de sites e domínios no RootAdmin**: não criada.
7. **Homologação no navegador** (1440 px e 390 px): pendente.
8. **Testes de permissão para administradores globais sem MFA**: não aplicável até o item 1.
9. Pendências anteriores mantidas: `homolog:purge` não alterado; horários históricos não alterados; licenciamento das imagens; MySQL 8; e as 2 falhas da landing.

## 7. Riscos comerciais e operacionais

- **Sem MFA, a conta administrativa global é um ponto único de falha.** Esse é o risco mais alto. Não se deve declarar o RootAdmin pronto para uso comercial antes do item 1.
- **Preços em `config/platform.php` ainda não têm contrato.** Se a cobrança for ligada sem validação, há risco de cobrança incorreta. Por isso nenhuma cobrança foi ativada.
- **Indicador de IA sem custo** pode ser lido como gasto zero. A tela informa que o custo está indisponível, mas a comunicação comercial deve manter essa ressalva.
- **Limite de usuários "não definido"** significa que o plano Premium ainda não é aplicado.

## 8. Recomendações para a próxima etapa

1. Implementar MFA para administradores globais no Fortify, com teste de acesso bloqueado sem segundo fator.
2. Especificar assinaturas (planos, ciclos, renovação, cancelamento, histórico de preço) e só depois criar as tabelas.
3. Medir o custo real por modelo e provedor antes de definir a conversão de tokens em créditos.
4. Criar a tela de Sites e Domínios no RootAdmin, reaproveitando `tenant_websites` e `tenant_domains`.
5. Homologar no navegador as telas de Visão Geral e Empresas em 1440 px e 390 px.
6. Resolver as 2 falhas da landing antes de qualquer entrega.

## 9. Escopo não realizado (por restrição do brief)

- Nenhum commit, push, merge ou deploy.
- Nenhuma cobrança real, nem integração de pagamento.
- Nenhuma chamada paga de IA.
- `homolog:purge`, horários históricos e dados de produção não foram alterados.
- Nenhum módulo de SEO, sites, widget ou aplicativo foi implementado.
