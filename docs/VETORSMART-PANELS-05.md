# VETORSMART-PANELS-05 — Auditoria funcional e evolução dos painéis

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`. Base: HEAD `30d0906`.

- Sem deploy e sem migrations.
- Cobrança e bloqueios financeiros intactos.
- Nenhuma integração nova.

## Resumo

- Fiz o inventário funcional dos dois painéis a partir do código (rotas, controllers, páginas, permissões e menus).
- Implementei as três lacunas de maior impacto operacional no RootAdmin:
  - **ações de conta em Usuários e Acessos**;
  - **IA e Créditos**;
  - **Sites e Domínios**.
- Corrigi o único defeito de acessibilidade encontrado no painel do tenant.
- Nenhuma métrica é simulada: as telas novas usam apenas dados gravados.

## 1. Inventário funcional

Legenda:
- **F**: funcional e completa;
- **P**: parcialmente implementada;
- **V**: somente visual;
- **D**: com defeito;
- **N**: não implementada.

### RootAdmin

| Área | Antes | Depois | Observação |
|---|---|---|---|
| Visão Geral | F | F | Indicadores reais (empresas, usuários, sites, agendamentos, IA do mês, assinaturas, receita recorrente). Custo de IA e incidentes declarados como indisponíveis, sem estimativa |
| Empresas | F | F | Lista com filtros, detalhe operacional, suspensão administrativa e início de teste |
| Assinaturas e Financeiro | F | F | Lista, detalhe, condições, prorrogação, cancelamento, baixa manual, sincronização e créditos. Cobrança fora de vigor (RELEASE-11.2) |
| Plano e Efí | F | F | Ciclos, aprovação, pacotes, regras, Efí e situação da cobrança em vigor |
| **IA e Créditos** | **N** (menu desabilitado) | **F** | Nova tela somente leitura (seção 2.2) |
| **Sites e Domínios** | **N** (menu desabilitado) | **F** | Nova tela somente leitura (seção 2.3) |
| Segmentos | F | F | Criar e editar |
| **Usuários e Acessos** | **P** (somente leitura, sem a situação do e-mail) | **F** | Situação da confirmação, suspensão, reativação e reenvio do link (seção 2.1) |
| Segurança e Auditoria | F | F | Registro com filtros |
| Crescimento e SEO | N | N | Menu desabilitado. Depende de dados de acesso que não existem |
| Suporte | N | N | Menu desabilitado. Não há módulo de chamados |
| Configurações Globais | N | N | Menu desabilitado. As configurações vivem em "Plano e Efí" e no `.env` |

### Painel do tenant

| Área | Situação | Observação |
|---|---|---|
| Painel | F | Indicadores da empresa e roteiro de primeiros passos |
| Clientes | F | Cadastro, edição, busca e arquivamento |
| Solicitações | F | Fluxo completo: triagem, agendamento, execução, conclusão, não realizada e cancelamento |
| Atendimento IA | F | Configuração, conversas, assumir, devolver, encerrar, identificar e responder. Sem provedor de IA, só o fluxo determinístico |
| Notificações | F | Central e preferências |
| Agenda | F | Visões, remarcação, cancelamento e bloqueios |
| Serviços / Profissionais | F | Cadastro, vínculo e disponibilidade |
| Usuários e permissões | **D → F** | A tabela não recebia foco pelo teclado no celular (corrigido). Limite de vagas (BILLING-01.2) |
| Meu Site | P | Funcional (rascunho, publicação, domínios). O subdomínio fica "reservado" enquanto `PLATFORM_SUBDOMAIN_SITES` estiver desligada (dependência de infraestrutura, não de código) |
| Configurações | F | Dados da empresa |
| Minha Assinatura | F | Informativa enquanto a cobrança está fora de vigor |

**Navegação:** o tenant tem 12 itens num menu plano. Uma proposta de agrupamento (Operação, Atendimento e Empresa) ficou registrada como pendência; não foi aplicada, por ser uma mudança transversal.

## 2. Implementações

### 2.1 Usuários e Acessos: ações de conta

- **Coluna de e-mail** com a situação: "Confirmado", "Legado, sem confirmação" ou "Aguardando confirmação". Com a política da RELEASE-11.2, a equipe passa a acompanhar quem ainda precisa confirmar.
- **Suspender conta:**
  - exige motivo e vale para todas as empresas;
  - encerra as sessões do banco de dados;
  - é auditada (`platform.user_suspended`);
  - recusa a própria conta e a última conta RootAdmin ativa.
- **Reativar conta**, auditada.
- **Reenviar confirmação**, só para contas não confirmadas; limitada a 6 por minuto e auditada.
- Perfis e vínculos continuam com cada empresa. Nenhuma credencial é exibida.

### 2.2 IA e Créditos (somente leitura)

**Totais do mês, sem as empresas de homologação:**
- empresas com o atendimento ligado;
- mensagens, chamadas ao provedor, falhas e tokens (`assistant_usage`);
- créditos consumidos.

**Configuração mostrada:**
- provedor ("Nenhum" hoje);
- franquia do plano ("Não definida");
- custo ("Indisponível: sem preço do provedor").

**Demais conteúdos:**
- tabela por empresa, com o saldo de franquia e de créditos comprados e o consumo sem saldo;
- compras aguardando confirmação, com link para a assinatura.

### 2.3 Sites e Domínios (somente leitura)

- **Totais:** sites publicados, rascunhos, retirados, domínios próprios verificados e pendentes, e empresas sem site.
- **Lista por empresa:** situação do site e endereços (subdomínio e domínios, verificação e principal), com filtros por situação e nome.
- O token de verificação nunca é enviado à tela (testado).

### 2.4 Qualidade visual

**Varredura do painel do tenant:**
- escopo: 15 telas × desktop e celular × claro e escuro, com axe WCAG 2.1 AA (inclui contraste);
- **60 combinações e 1 problema:** a tabela de `/equipe` não recebia foco pelo teclado no celular. Corrigido e reverificado, com 0 problemas;
- **o contraste do modo escuro passou em todas as telas.**

As telas novas do RootAdmin seguem os mesmos componentes (`Card`, `Badge`, tabelas com região focável e tokens `vs-*`, com variantes `dark:`).

**A homologação visual do RootAdmin não foi executada:** não existe conta root local ativa, e as etapas anteriores proibiram criar contas administrativas.

## 3. Arquivos alterados

**Novos:**
- `app/Http/Controllers/RootAdmin/UserAccountController.php`;
- `app/Http/Controllers/RootAdmin/AiUsageController.php`;
- `app/Http/Controllers/RootAdmin/WebsiteController.php`;
- `resources/js/pages/rootadmin/ai/index.tsx`;
- `resources/js/pages/rootadmin/websites/index.tsx`;
- `tests/Feature/RootAdmin/PlatformPanelsTest.php`;
- este relatório.

**Alterados:**
- `app/Http/Controllers/RootAdmin/UserController.php` (situação do e-mail e da conta);
- `routes/rootadmin.php` (5 rotas novas);
- `resources/js/pages/rootadmin/users/index.tsx`;
- `resources/js/components/app-sidebar.tsx` (dois itens habilitados);
- `resources/js/pages/tenant/team/index.tsx` (região focável).

## 4. Testes direcionados

A política da seção 5 foi seguida: a suíte completa não foi repetida, só os grupos afetados.

| Teste | Resultado |
|---|---|
| `PlatformPanelsTest` (novo) | **12 de 12**: situação do e-mail; suspensão (motivo, efeito e auditoria); proteção da própria conta; reativação; reenvio só para não confirmadas; 403 para usuários de empresa nas 5 rotas novas; números reais do mês, sem homologação nos totais; sites e endereços sem o token |
| Grupos afetados (`RootAdmin`, `Management`, `Interface`, `Auth`, `Tenancy`) | **259 de 259** |
| PHPStan / Pint / TypeScript / build | 0 erros / ok / ok / ok |
| Varredura visual do tenant (axe) | 60 combinações; depois da correção, 0 problemas |

## 5. Pendências (por impacto)

1. **Homologação visual do RootAdmin** com uma conta root legítima: telas novas e alteradas.
2. **Agrupamento do menu do tenant** (12 itens): proposta de seções Operação (Painel, Solicitações, Agenda, Clientes), Atendimento (Atendimento IA, Notificações), Catálogo (Serviços, Profissionais) e Empresa (Meu Site, Usuários, Configurações, Assinatura).
3. **Configurações Globais:** reunir numa tela só de leitura os parâmetros vigentes (cobrança em vigor, prazo do acesso legado, subdomínios, provedor de IA), sem expor segredos.
4. **Suporte e Crescimento e SEO:** dependem de módulos novos (chamados, métricas de acesso). Continuam desabilitados, sem simulação.
5. A suspensão do último root ativo é bloqueada no código, mas não é alcançável pela tela (quem suspende já é um root ativo). Ficou como defesa adicional.
6. As pendências de implantação da RELEASE-11.2 (VPS, SMTP, backup, MySQL 8.4) continuam inalteradas.

## 6. Git

- **Sem commit** desta etapa, aguardando autorização.
- HEAD `30d0906`, sem push.
- Nenhuma migration nova.
