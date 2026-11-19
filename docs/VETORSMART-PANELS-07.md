# VETORSMART-PANELS-07 — Meu Crescimento, Central de Suporte e Administração Operacional

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem deploy, push ou migrations na VPS.
- Efí, assinaturas, pagamentos e bloqueios inalterados.
- Sem MFA.
- Sem dependências novas.

## Parecer

**Concluída.** Os 9 critérios de aceite da seção 10 foram atendidos:

| # | Critério | Situação |
|---|---|---|
| 1 | PANELS-06 consolidada | `02448cd` |
| 2 | Assinante vê indicadores reais | Meu Crescimento (seção 1) |
| 3 | Assinante abre e acompanha chamados | Central de Suporte do painel (seção 2) |
| 4 | RootAdmin administra chamados | Responsável, prazo, resposta, nota interna, situação controlada, encerramento, reabertura e indicadores (seção 3) |
| 5 | Exportações CSV | Empresas, chamados e crescimento (seção 4) |
| 6 | Isolamento multi-tenant | Chamado de outra empresa responde 404; consultas sempre pela empresa do contexto (testado) |
| 7 | Desktop e celular | Varredura nas telas novas do assinante: 12 combinações, 0 problemas |
| 8 | Testes direcionados | 26 novos ou afetados e 224 dos grupos impactados |
| 9 | Regras financeiras intactas | Nenhum arquivo de cobrança, Efí ou bloqueio alterado |

## 1. Meu Crescimento (assinante)

**Rota:** `/meu-crescimento`, para proprietário e administrador (`TenantPolicy::viewGrowth`). Fica na seção "Visão Geral" do menu.

**Indicadores do mês corrente**, no fuso da empresa:
- solicitações recebidas;
- **agendamentos realizados** (status concluído, pela data do atendimento);
- serviços concluídos;
- **taxa de conversão**, só com 5 ou mais solicitações; abaixo disso, aparece "Dados insuficientes".

**Comparativo com o mês anterior**, só quando o mês anterior tem dados. Sem base, a tela mostra "sem base de comparação"; para a conversão, a diferença aparece em pontos percentuais.

**Também exibe:**
- serviços ativos e serviços exibidos no site publicado;
- SEO técnico do próprio site (título, descrição, liberado ou não para indexação, endereço principal), com o aviso explícito de que a VetorSmart não mede a indexação no Google;
- evolução dos últimos 6 meses (solicitações, agendamentos realizados e concluídos), com estado vazio quando não há dados.

**Sem visitas, tráfego, faturamento ou posição em buscadores.**

**Reaproveitamento:** as agregações da PANELS-06 foram extraídas para `App\Services\Growth\GrowthMetrics` e são usadas pelo RootAdmin e pelo painel, sem duplicação. O tenant vem sempre do `TenantContext`.

## 2. Central de Suporte (assinante)

**Rotas:** `/suporte`, `/suporte/{id}` e `POST /suporte/{id}/respostas`, para proprietário e administrador (`TenantPolicy::useSupport`). Ficam fora do bloqueio de assinatura, para que uma empresa bloqueada ainda possa pedir ajuda.

**Funcionalidades:**
- abrir chamado com assunto, **categoria** (Problema técnico, Acesso e usuários, Site e domínio, Assinatura, Dúvida de uso, Outro) e descrição;
- consultar os próprios chamados, com filtro Todos, Em andamento ou Finalizados;
- acompanhar a situação;
- **responder e complementar**;
- histórico cronológico, com as respostas da equipe destacadas como "Equipe VetorSmart";
- chamados encerrados identificados, com a orientação de abrir um novo.

**Regras:**
- **Isolamento:**
  - o chamado é buscado sempre dentro da empresa da sessão; o de outra empresa responde **404**, tanto para ver quanto para responder;
  - a prioridade é definida pela equipe;
  - a empresa nunca vê **notas internas** nem as mudanças de prioridade, responsável ou prazo.
- **Reabertura pela resposta:** se a empresa responde um chamado "Aguardando a empresa" ou "Resolvido", ele volta a "Aberto". Um chamado "Encerrado" não aceita resposta.

## 3. Central de Suporte (RootAdmin)

**Funcionalidades:**
- **lista de todas as empresas e da plataforma**, com filtros por empresa, situação (inclui "Atrasados" e "Todas"), prioridade, categoria, responsável ("Sem responsável") e assunto, com paginação;
- **indicadores clicáveis:** em aberto, aguardando a empresa, atrasados e sem responsável;
- **atribuição de responsável** (só administradores da plataforma ativos; outro usuário é recusado);
- **prazo de atendimento** definido no chamado, em horário de Brasília. "Atrasado" significa prazo vencido sem resolução. Não há SLA contratual nem automação.
- **resposta** (visível para a empresa) ou **nota interna** (marcada como "Interno");
- **situação controlada:**
  - Aberto, Em andamento e Aguardando circulam entre si e podem ir para Resolvido ou Encerrado;
  - Resolvido só vai para Encerrado;
  - um chamado finalizado volta apenas por **Reabrir**, com motivo;
- **histórico** com usuário e data, inclusive das notas internas e das mudanças de responsável e prazo.

**Implementação:** `App\Services\Support\SupportTicketService` concentra todas as mudanças, com transação, lock de linha, histórico imutável (`support_ticket_events`) e auditoria (`support.ticket_opened`, `support.ticket_replied`, `support.ticket_updated`, `support.ticket_reopened`).

## 4. Administração Global e CSV

| Exportação | Rota | Conteúdo |
|---|---|---|
| Empresas | `/rootadmin/tenants/exportar` | Número, nome, slug, segmento, situação, homologação, assinatura, usuários ativos, cadastro, com os mesmos filtros da tela. **Sem e-mail, documento ou credencial** |
| Chamados | `/rootadmin/suporte/exportar` | Número, empresa, assunto, categoria, situação, prioridade, origem, responsável, prazo, atrasado e datas, com os filtros da tela |
| Indicadores | `/rootadmin/crescimento/exportar` | Por empresa, no mês: site, serviços, solicitações, agendamentos, concluídos, conversão e SEO técnico |

**Formato e segurança:**
- `App\Support\CsvExport`: envio em fluxo e em blocos de 200 linhas, UTF-8 com BOM e separador `;` (Excel em português);
- **proteção contra injeção de fórmula:** células começando com `=`, `+`, `-`, `@`, tabulação ou retorno recebem um apóstrofo (testado);
- limite de 10 exportações por minuto;
- acesso só para o RootAdmin (403 para usuários de empresa, testado).

**Outros ajustes:**
- **Filtros novos:** empresa, categoria e responsável no Suporte.
- **Configurações Globais** continuam somente leitura.
- Nenhuma área administrativa nova além do que o correio pediu.

## 5. Menu do assinante

- **Visão Geral:** Painel, **Meu Crescimento**\*, Notificações.
- **Administração:** acrescenta **Suporte**\*.

\* Só para proprietário e administrador. O servidor também autoriza cada página.

## 6. Alterações de banco

| Migration | Conteúdo |
|---|---|
| `2026_11_19_130828_add_tenant_support_fields_to_support_tickets` | `support_tickets`: `category`, `opened_via` (empresa ou plataforma), `assigned_to` e `due_at`, com índice. `support_ticket_events`: `is_internal`. Reversível |

- Executada **somente no banco local**.
- Pendentes para a próxima implantação: **18**.

## 7. Arquivos

**Criados:**
- `app/Services/Growth/GrowthMetrics.php`;
- `app/Services/Support/SupportTicketService.php`;
- `app/Support/CsvExport.php`;
- `app/Enums/SupportTicketCategory.php`;
- `app/Http/Controllers/Tenant/GrowthController.php` e `SupportController.php`;
- `app/Http/Requests/Tenant/StoreSupportRequest.php`;
- a migration acima;
- `resources/js/pages/tenant/growth/index.tsx`;
- `resources/js/pages/tenant/support/index.tsx` e `show.tsx`;
- `tests/Feature/Tenancy/GrowthAndSupportTest.php`;
- este relatório.

**Alterados:**
- `app/Http/Controllers/RootAdmin/GrowthController.php` (usa o `GrowthMetrics`, mais a exportação);
- `app/Http/Controllers/RootAdmin/SupportController.php` (reescrito sobre o serviço);
- `app/Http/Controllers/RootAdmin/TenantController.php` (consulta filtrada reaproveitada, mais a exportação);
- `app/Http/Requests/RootAdmin/StoreSupportTicketRequest.php` e `UpdateSupportTicketRequest.php`;
- `app/Models/SupportTicket.php` e `SupportTicketEvent.php`;
- `app/Policies/TenantPolicy.php` (`viewGrowth` e `useSupport`);
- `app/Support/PlatformTime.php` (`fromLocal`);
- `routes/tenant.php` e `routes/rootadmin.php`;
- `resources/js/components/app-sidebar.tsx`;
- `resources/js/pages/rootadmin/support/index.tsx` e `show.tsx`;
- `resources/js/pages/rootadmin/tenants/index.tsx` e `growth/index.tsx` (botão "Exportar CSV");
- `tests/Feature/RootAdmin/PlatformAdministrationTest.php` (texto de validação atualizado).

## 8. Testes

| Verificação | Resultado |
|---|---|
| `GrowthAndSupportTest` (novo, 14) e `PlatformAdministrationTest` (12) | **26 de 26** |
| Coberto | Números só da própria empresa; conversão e comparativo; "dados insuficientes"; operador recebe 403 nas duas telas; abertura com validação, categoria, origem e auditoria; **chamado de outra empresa 404** (ver e responder, sem efeito); **nota interna invisível** para a empresa; resposta reabre chamado aguardando ou resolvido; encerrado recusa resposta; responsável só root; prazo, contagem e filtro de atrasados; situação controlada e reabertura com motivo e auditoria; CSV com filtros, sem e-mail e com fórmula neutralizada; 403 nas exportações |
| Grupos afetados (`RootAdmin`, `Interface`, `Tenancy`, `Management`) | **224 de 224** |
| PHPStan | 0 erros (8 apontamentos de tipagem corrigidos na causa) |
| Pint | ok (ajustou só imports) |
| TypeScript / build | ok / ok |
| Visual (Meu Crescimento, Suporte, Painel; desktop e celular, claro e escuro) | **12 de 12**, 0 problemas de axe ou de layout; a captura no celular escuro confere os estados sem dados |

A suíte completa e a homologação geral não foram executadas, conforme o correio.

## 9. Limitações

1. **RootAdmin sem verificação em navegador:** não há conta root local ativa, e nenhuma conta administrativa foi criada. As telas de Suporte, o CSV e os filtros foram validados por testes.
2. **Suporte sem avisos:** não há notificação por e-mail nem na central de avisos quando a equipe responde. Notificações externas ficaram fora do escopo, e a empresa acompanha pela página.
3. **Operadores:** não abrem chamados nem veem Meu Crescimento (decisão de permissão: só proprietário e administrador).
4. **Prazos:** são manuais, por chamado; não há SLA por prioridade nem escalonamento automático.
5. **Meu Crescimento sem tráfego:** não mede visitas nem origem.

## 10. Próximos passos sugeridos

1. Aviso na central de notificações da empresa quando a equipe responder um chamado (reaproveitando o `OperationalNotifier`).
2. Prazo sugerido por prioridade, editável, sem SLA contratual.
3. Homologação visual do RootAdmin (PANELS-05 a 07) com uma conta root legítima.
4. Avaliar se operadores devem abrir chamados de problemas técnicos.
5. Consolidar a PANELS-07 em commit e, depois, preparar a etapa de integração e implantação das 18 migrations.

## 11. Git

- `02448cd`: PANELS-06.
- **PANELS-07 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**
