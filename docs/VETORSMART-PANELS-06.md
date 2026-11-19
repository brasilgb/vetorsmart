# VETORSMART-PANELS-06 — Evolução funcional dos painéis, administração global e crescimento

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem deploy e sem migrations em produção.
- Bloqueios financeiros inalterados.
- Sem MFA.
- Sem dependências novas.

## Parecer técnico

**Concluída.** Todos os critérios da seção 9 do correio foram atendidos:

| Critério | Situação |
|---|---|
| PANELS-05 consolidada em Git | `b8c43a6` |
| Menu do assinante organizado | 4 seções, permissões preservadas, página ativa anunciada a leitores de tela |
| Configurações Globais funcional e protegida | Somente leitura, sem segredos, restrita ao RootAdmin (testado) |
| Crescimento e SEO com indicadores verdadeiros | Consultas reais, filtro por mês, paginação, sem afirmar indexação (testado) |
| Suporte operacional utilizável | Persistência (migration nova), autorização, fluxo de abertura, acompanhamento e auditoria (testado) |
| Permissões e isolamento preservados | 403 para usuários de empresa nas 6 rotas novas; nada muda quando recusado |
| Testes direcionados aprovados | 12 novos e 88 dos grupos afetados |

## 1. Funcionalidades implementadas

### 1.1 Menu do assinante

| Seção | Itens |
|---|---|
| Visão Geral | Painel, Notificações |
| Atendimento e Operação | Solicitações, Agenda, Atendimento IA, Clientes, Serviços, Profissionais |
| Presença Digital | Meu Site |
| Administração | Usuários e permissões\*, Minha Assinatura\*, Configurações |

\* Só para proprietário e administrador.

- O filtro agora usa a propriedade `managersOnly`, em vez de comparar títulos de texto. O servidor continua autorizando cada página.
- Nenhuma página vazia foi criada. "Meu Crescimento" não entrou, porque não existe no painel do tenant.
- **Acessibilidade e responsividade:**
  - cada grupo é uma lista rotulada (`aria-label`);
  - o item ativo tem `aria-current="page"` e destaque visual;
  - os mesmos ícones lucide foram mantidos;
  - no modo recolhido, aparecem só os ícones com dica;
  - no celular, o menu continua lateral e fecha ao navegar.
- **Verificação:** axe em `/dashboard`, desktop e celular, claro e escuro: 0 problemas. A captura no escuro confirma o contraste dos rótulos e do item ativo.
- O RootAdmin ficou num grupo único ("Plataforma"), com **todos os itens habilitados**: não resta nenhum "Em breve".

### 1.2 Configurações Globais (`/rootadmin/configuracoes`)

Somente leitura, em cinco categorias. Cada item tem um estado explícito: **Configurado**, **Desabilitado** ou **Indisponível**.

| Categoria | Itens |
|---|---|
| Plataforma | Ambiente, nome, URL pública, URL da aplicação, modo de depuração, estado operacional (manutenção) |
| Assinaturas | Plano vigente (valores aprovados ou provisórios), ciclos com valor, teste, usuários incluídos, franquia de IA, cobrança em vigor |
| Integrações | Efí (ambiente, Pix e Cobranças **por presença de credencial**, último teste de conexão), e-mail (mailer, host e porta; "entrega não verificada por esta tela"), fila (conexão, pendentes e com falha), provedor de IA, compra de créditos |
| Sites e Domínios | Domínio principal, base dos subdomínios, sites por subdomínio, limites de domínios e trocas |
| Segurança | Confirmação de e-mail, contas legadas sem confirmação e prazo, sessão (driver, duração, cookie seguro, SameSite), limites de tentativa, auditoria dos últimos 30 dias |

**Garantias:**
- Senhas, tokens, chaves de API e client secrets nunca são enviados à tela. O teste injeta segredos e confirma que nenhum aparece na resposta.
- Nenhum valor do `.env` é alterado pela interface.
- Nenhum estado é inventado: o que esta tela não consegue verificar (a entrega real de e-mail, a saúde do worker) está dito na nota.

### 1.3 Crescimento e SEO (`/rootadmin/crescimento`)

**Por empresa, no mês escolhido (últimos 12):**
- site (publicado, rascunho, retirado ou sem site);
- serviços ativos e serviços exibidos no site publicado;
- solicitações recebidas, agendamentos gerados e solicitações concluídas;
- **conversão** (concluídas ÷ recebidas), **só com 5 ou mais solicitações**; abaixo disso, aparece "Dados insuficientes".

**SEO técnico:**
- título e descrição cadastrados;
- URL canônica do site publicado;
- **indexável e no sitemap**, conforme a configuração local: site no ar, chave de SEO ligada, empresa ativa e fora de homologação. É a mesma regra do `SiteController`.
- **Nunca afirma que a página está indexada em buscadores.** Não há integração com o Search Console.

**Gerais** (sem homologação):
- empresas com e sem site publicado;
- serviços exibidos publicamente;
- empresas com atividade no período;
- **evolução mensal real de 6 meses**: empresas novas, sites publicados, solicitações, agendamentos e concluídas.

**Eficiência:**
- paginação de 20;
- métricas da página lidas com consultas agrupadas por empresa (sem N+1);
- filtros por nome, situação do site e mês;
- **nenhuma tabela nova.**

### 1.4 Suporte operacional (`/rootadmin/suporte`)

- **Verificação prévia:** não havia tabela nem modelo de chamados, incidentes ou suporte. Foi criada uma migration.
- **Chamados:**
  - empresa relacionada (opcional, para questões da plataforma);
  - assunto e descrição;
  - situação: Aberto, Em andamento, Aguardando a empresa, Resolvido ou Encerrado;
  - prioridade: Baixa, Normal, Alta ou Urgente;
  - quem abriu, abertura, última atualização e finalização.
- **Histórico imutável** (`support_ticket_events`): abertura, acompanhamento, mudança de situação e mudança de prioridade, com autor e data.
- **Fluxo:**
  - abrir (com validação em português);
  - registrar acompanhamento: nota, situação e/ou prioridade, com pelo menos um deles obrigatório;
  - ao resolver ou encerrar, grava a data de finalização;
  - lock na atualização;
  - auditoria (`support.ticket_opened` e `support.ticket_updated`, com valor anterior e novo).
- **Lista:** só os chamados em aberto por padrão, ordenados por prioridade e atividade, com filtros e paginação.
- **Sinais operacionais** (dados reais, sem homologação), com atalho "Abrir chamado" que preenche a empresa:
  - empresas suspensas ou canceladas;
  - domínios com verificação falha;
  - cobranças sem emissão;
  - falhas do provedor de IA no mês.
- **Fora do escopo,** como pede o correio: chat, WhatsApp, IA ou integrações externas. O módulo não substitui a futura Central ABrasil Suporte.

## 2. Arquivos

**Criados:**
- `app/Http/Controllers/RootAdmin/GlobalSettingsController.php`;
- `app/Http/Controllers/RootAdmin/GrowthController.php`;
- `app/Http/Controllers/RootAdmin/SupportController.php`;
- `app/Http/Requests/RootAdmin/StoreSupportTicketRequest.php` e `UpdateSupportTicketRequest.php`;
- `app/Models/SupportTicket.php` e `SupportTicketEvent.php`;
- `app/Enums/SupportTicketStatus.php` e `SupportTicketPriority.php`;
- `database/migrations/2026_11_19_124224_create_support_tickets_tables.php`;
- `resources/js/pages/rootadmin/settings/index.tsx`;
- `resources/js/pages/rootadmin/growth/index.tsx`;
- `resources/js/pages/rootadmin/support/index.tsx` e `show.tsx`;
- `tests/Feature/RootAdmin/PlatformAdministrationTest.php`;
- este relatório.

**Alterados:**
- `routes/rootadmin.php` (6 rotas);
- `resources/js/components/app-sidebar.tsx` (grupos e itens habilitados);
- `resources/js/components/nav-main.tsx` (rótulo do grupo e `aria-current`);
- `resources/js/types/navigation.ts` (`NavGroup` e `managersOnly`).

## 3. Rotas e páginas

Todas sob `auth`, `verified.strict` e `can:platform-admin`:

| Método | Rota | Página |
|---|---|---|
| GET | `/rootadmin/configuracoes` | Configurações Globais |
| GET | `/rootadmin/crescimento` | Crescimento e SEO (`?mes=AAAA-MM&q=&site=publicado\|sem-site`) |
| GET | `/rootadmin/suporte` | Suporte: sinais e chamados (`?status=&priority=&q=&tenant_id=`) |
| POST | `/rootadmin/suporte` | Abrir chamado (limite de 30 por minuto) |
| GET | `/rootadmin/suporte/{ticket}` | Detalhe do chamado |
| POST | `/rootadmin/suporte/{ticket}/acompanhamentos` | Registrar acompanhamento (limite de 60 por minuto) |

## 4. Testes direcionados

A suíte completa e a matriz visual extensa não foram repetidas, conforme o correio.

| Verificação | Resultado |
|---|---|
| `PlatformAdministrationTest` (novo) | **12 de 12**: Configurações Globais sem nenhum segredo, com estados corretos; Crescimento com números reais do mês, conversão só com 5 ou mais solicitações, indexável falso sem site; homologação fora dos totais; abertura de chamado com validação, histórico e auditoria; acompanhamento com comentário, situação, prioridade e data de finalização; acompanhamento vazio recusado; lista em aberto por padrão; sinal de empresa suspensa; 403 para usuários de empresa nas 6 rotas, sem efeito colateral |
| Grupos afetados (`RootAdmin`, `Interface`) | **88 de 88** |
| PHPStan | 0 erros (7 apontamentos de tipagem corrigidos na causa) |
| Pint | ok |
| TypeScript / build | ok / ok |
| Menu (axe em `/dashboard`, 4 combinações, mais uma captura no escuro) | 0 problemas |

## 5. Funcionalidades ainda incompletas

1. **Homologação visual das telas novas do RootAdmin:** não há conta root local ativa, e nenhuma conta foi criada.
2. **Crescimento e SEO:** não há dados de acesso (visitas e origem), nem integração com o Search Console (fora do escopo). Por isso, não existe métrica de tráfego.
3. **Suporte:** não há atribuição de responsável nem prazos (SLA), e a empresa não abre chamados pelo painel. É uma base interna da equipe.
4. **Configurações Globais:** é somente leitura por decisão do correio. Alterações continuam no `.env` e em "Plano e Efí".

## 6. Migrations

`2026_11_19_124224_create_support_tickets_tables` cria `support_tickets` e `support_ticket_events`. Ela é reversível e foi **executada só no banco local**, nunca na VPS.

Total pendente para a próxima implantação: **17** (as 16 da RELEASE-11.2 mais esta).

## 7. Git

| Commit | Descrição |
|---|---|
| `b8c43a6` | `feat(rootadmin)`: ações de conta, IA e Créditos e Sites e Domínios (PANELS-05) |

- **PANELS-06 sem commit**, aguardando autorização.
- Base `b8c43a6`, com 17 commits locais sobre a `origin/main`.
- **Sem push e sem deploy.**

## 8. Recomendações para a PANELS-07

1. **Suporte:** atribuir o responsável (um RootAdmin), definir prazo por prioridade e, se aprovado, permitir que o proprietário abra chamados pelo painel da empresa, com isolamento por tenant.
2. **Meu Crescimento no painel do tenant:** os mesmos indicadores da empresa (solicitações, agendamentos, concluídas, conversão e SEO técnico do próprio site), reaproveitando as consultas do `GrowthController`.
3. **Homologação visual do RootAdmin** (as telas das PANELS-05 e 06), com uma conta root legítima.
4. **Exportação CSV** das listas de Crescimento e Suporte, com o mesmo filtro e limite de linhas.
5. **Teste de componente do menu:** que o operador não veja os itens de administração. Hoje isso é garantido pela autorização no servidor e conferido manualmente.
