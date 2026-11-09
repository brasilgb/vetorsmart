# VETORSMART-BACKEND-02 — Homologação da Fundação e Núcleo Operacional

**Status: CONCLUÍDO COM RESSALVAS.** Código implementado e validado por testes automatizados, Pint, PHPStan, TypeScript e build. Homologação HTTP real executada. Homologação no navegador e do RootAdmin **não** foram possíveis nesta máquina (ver seção 2).

Data: 09/11/2026. Diretório: `gateway/vetor-smart/`. Banco: MySQL-compatível local (ver seção 11).

---

## 1. Estado inicial

- Backend 01 implementado, com relatório em `docs/VETORSMART-BACKEND-01.md`.
- `correio.md` mudou de VETORSMART-BACKEND-01 para 02. Esta etapa executa o 02.
- Baseline antes desta etapa: 80 testes (77 aprovados, 3 pulados de 2FA).
- O diretório não é rastreado pelo repositório pai (`??`). HEAD do repositório pai está destacado. Não houve commit ainda.

## 2. Homologação do Backend 01

### 2.1 Navegador
Chrome não instalado nesta máquina (nem em `/opt/google/chrome` nem no caminho do Windows). A homologação foi feita por **HTTP real** contra `php artisan serve` (`127.0.0.1:8000`), com cookies de sessão, token CSRF e login pelo formulário. Os registros de teste têm prefixo `[HOMOLOG]`.

| Item | Resultado (HTTP real) |
|---|---|
| Cadastro público com segmento | 302 → `/dashboard` (empresa "[HOMOLOG] Empresa Alfa" e "[HOMOLOG] Empresa Beta") |
| Criação transacional do tenant | Empresa, vínculo owner e configurações criados pelo mesmo fluxo (verificado no banco) |
| Login do proprietário | 302 → `/dashboard` |
| Logout | 204; depois, API retorna 401 |
| Usuário suspenso (sessão existente) | 401 |
| Usuário suspenso (novo login) | 302 para `/login`, sem sessão; API retorna 401 |
| Tenant suspenso | 403 nas rotas do tenant; lista de empresas vazia |
| Ausência de dependência de e-mail | Cadastro e login concluídos sem envio de e-mail (`MAIL_MAILER=log`) |
| Isolamento entre empresas | Alfa lê cliente da Beta → 404; lista da Alfa não contém dados da Beta; troca para empresa alheia → 403; `tenant_id` no payload ignorado |

**Limitação:** suspensão de tenant foi simulada por `UPDATE tenants SET status` no banco de homologação e restaurada logo depois. A suspensão pela API de RootAdmin não foi executada por HTTP (ver 2.2).

### 2.2 RootAdmin (`php artisan platform:root-admin`)
- O comando exige terminal interativo para a senha oculta. Não foi executado em nenhum ambiente, e **nenhuma credencial foi inventada ou registrada**.
- Permissões globais, listagem, ativação/suspensão e segmentos foram validados por testes automatizados (`PlatformAdminTest`, `CreateRootAdminCommandTest`), incluindo bloqueio de usuário comum (403) e de visitante (401).
- Pendência: provisionar o primeiro RootAdmin manualmente, com terminal interativo.

## 3. Pendências anteriores (Backend 01)

| Item | Ação | Resultado |
|---|---|---|
| 12 erros TypeScript | `global.d.ts` não importava `@inertiajs/core`, então a augmentation de `InertiaConfig` não era aplicada | Import adicionado. `tsc --noEmit`: **0 erros** |
| Usuário legado `test@example.com` | Removido do `DatabaseSeeder` (senha conhecida). Registro existente no banco de desenvolvimento **não apagado**: foi **suspenso** (`status = suspended`), sem vínculo com empresa | Login bloqueado |
| Status `cancelled` sem fluxo | Não há operação que o aplique. Tenant cancelado já não concede acesso (resolver exige `active`) | Teste `AccountStatusTest` cobre o comportamento |
| Auditoria de operações administrativas | Mutações de tenant, usuários, configurações, segmentos, categorias, serviços, profissionais e clientes são auditadas. **Leituras do RootAdmin não são auditadas** (decisão mantida) | Testes `AuditLogTest`, `OperationalAuditTest` |
| Autenticação por sessão nas APIs | Mantida. Sanctum **não** foi adicionado (sem necessidade comprovada) | Documentado |
| Jobs tenant-scoped | `TenantScopedJob` revalida tenant e membership antes de processar | `TenantScopedJobTest` (5 testes) |

## 4. Clientes (`Customer`)

- **Tabela `customers`**: `tenant_id` (FK), `name`, `person_type` (`individual`/`company`), `document` (dígitos), `email` (minúsculo), `phone` e `whatsapp` (dígitos), endereço, `notes`, `status` (`active`/`inactive`), timestamps.
- **Índices**: `(tenant_id, id)` (alvo de FKs compostas), `(tenant_id, document)` **único**, `(tenant_id, status)`, `(tenant_id, email)`, `(tenant_id, phone)`, `(tenant_id, name)`.
- **Regras**: só o nome é obrigatório. Duplicidade por documento, e-mail ou telefone/WhatsApp é recusada **dentro do mesmo tenant** (422, chave `duplicate`, com o ID existente). Clientes de outras empresas podem repetir os dados.
- **Busca**: `q` por nome, e-mail ou dígitos de telefone/WhatsApp.
- **Desativação**: `DELETE` marca `inactive`; o registro é mantido.

## 5. Categorias e serviços

**`service_categories`**: `tenant_id`, `name` (único por tenant), `description`, `sort_order`, `active`. Não há categorias fixadas no código.

**`services`**: `tenant_id`, `service_category_id` (opcional), `name`, `description`, `duration_minutes` (opcional, 1–1440), `price` **DECIMAL(10,2)** (opcional, sem FLOAT), `price_type` (`fixed`/`starting_at`/`on_request`), `requires_evaluation`, `active`, `sort_order`, timestamps.

Regras de preço (decisão desta etapa):
- `fixed` e `starting_at` **exigem** valor.
- `on_request` **não aceita** valor. Ao trocar para `on_request` sem informar valor, o valor anterior é limpo.
- Valor negativo é recusado pela validação e por `CHECK` no banco.

Regras de integridade:
- Categoria precisa pertencer ao mesmo tenant **e** estar ativa (validação), e a FK composta `(tenant_id, service_category_id)` impede no banco qualquer referência cruzada.
- Serviço inativo não recebe novos agendamentos (`isAvailableForScheduling()`). Histórico preservado (desativação, sem exclusão).

API: CRUD, paginação, filtros `category_id` e `active`, ordenação por `sort`/`direction` com lista branca (`name`, `sort_order`, `price`, `created_at`).

## 6. Profissionais

- **`professionals`**: `tenant_id`, `name`, `email`, `phone` (dígitos), `active`, `notes`, timestamps. Não exige login.
- **`professional_services`**: `tenant_id`, `professional_id`, `service_id`, único por par. FKs compostas garantem que os dois lados pertencem ao mesmo tenant.
- Vínculo substituído pelo conjunto enviado (`PUT`). Serviço de outra empresa → 422. Serviço inativo **não pode ser vinculado como novo**, mas vínculos já existentes são mantidos.
- Ativação/desativação (`DELETE` desativa, `POST .../activate` reativa). Profissional inativo não recebe agendamentos.
- Auditoria registra os IDs adicionados e removidos no vínculo.

## 7. Configurações de atendimento

`tenant_settings` ampliada com `description`, `service_modes` (JSON: `in_person`, `remote`, `customer_location`), `welcome_message`, `confirmation_policy` (`none`/`manual`/`customer_confirmation`, padrão `manual`).

Não duplicados: **telefone e fuso** continuam em `tenants`. A API os apresenta e grava junto das configurações (`phone`, `timezone`).

Nome público = `business_name` (já existia). Configurações futuras (IA, instruções do segmento, base de conhecimento, site público, horários) ficam reservadas na coluna JSON `settings`, **sem implementação**.

## 8. Endpoints (sob `/api/v1/tenant`, com `tenant.resolve`)

| Recurso | Método e rota | Observação |
|---|---|---|
| Configurações | `GET/PATCH settings` | PATCH aceita campos novos |
| Clientes | `GET/POST customers`, `GET/PATCH/DELETE customers/{id}` | `DELETE` = desativar |
| Categorias | `GET/POST service-categories`, `GET/PATCH/DELETE service-categories/{id}` | `DELETE` = desativar |
| Serviços | `GET/POST services`, `GET/PATCH/DELETE services/{id}` | filtros e ordenação |
| Profissionais | `GET/POST professionals`, `GET/PATCH/DELETE professionals/{id}` | `DELETE` = desativar |
| Ativar profissional | `POST professionals/{id}/activate` | |
| Serviços do profissional | `GET professionals/{id}/services` | |
| Vincular serviços | `PUT professionals/{id}/services` | substitui o conjunto |

Usuários, auth e administração global seguem como no BACKEND-01.

## 9. Matriz de permissões

| Ação | Owner | Admin | Operator |
|---|:-:|:-:|:-:|
| Listar/ver clientes, categorias, serviços, profissionais | ✔ | ✔ | ✔ |
| Criar/editar clientes | ✔ | ✔ | ✔ |
| Desativar clientes | ✔ | ✔ | ✘ |
| Criar/editar/desativar categorias, serviços, profissionais | ✔ | ✔ | ✘ |
| Vincular serviços a profissional | ✔ | ✔ | ✘ |
| Editar configurações | ✔ | ✔ | ✘ |
| Gerenciar usuários | ✔ | apenas operator | ✘ |
| RootAdmin em dados do tenant | ✘ | ✘ | ✘ |

Verificado por teste (`OperationalPermissionsTest`) e por HTTP (operador: 200 em listagem, 403 em criação de serviço).

## 10. Isolamento

- `tenant_id` nunca vem do cliente: ignorado em todos os `POST`.
- Toda busca passa por `forTenant()`. Registro de outra empresa → **404**.
- **Constraints no banco** (InnoDB): FKs compostas `(tenant_id, …)` em `services` e `professional_services`; `UNIQUE (tenant_id, document)`; `UNIQUE (tenant_id, name)` em categorias; `CHECK` de preço e duração.
- Testes de integridade inserem diretamente no banco referências cruzadas e confirmam a recusa (`DatabaseIntegrityTest`).
- Contexto do tenant é restaurado ao fim de cada requisição (`TenantContextLeak`-style test em `OperationalIsolationTest`).
- RootAdmin não acessa dados operacionais de tenant (403).

## 11. Banco de dados

- Configuração: `.env` com `DB_CONNECTION=mysql`.
- **O servidor local é MariaDB 11.8.6**, não MySQL 8. Os testes e as migrations rodaram nele.
- Compatibilidade com MySQL 8 **não verificada**. Os recursos usados (InnoDB, FKs compostas, `CHECK`, `JSON`) são suportados em MySQL 8.0.16+, mas não foram testados nele.
- Teste `the operational tables use InnoDB...` verifica o motor e as FKs no servidor atual.

## 12. Testes e validação

| Verificação | Resultado |
|---|---|
| `php artisan test` (suíte completa) | **141 testes: 138 aprovados, 3 pulados** (2FA desativado) |
| Testes do BACKEND-01 preservados | Sim (80 testes originais continuam passando) |
| Testes novos do BACKEND-02 | 61 (CRUD, validação, preço, isolamento, permissões, auditoria, configurações, integridade, MariaDB/InnoDB) |
| Pint (`--dirty`) | aprovado |
| PHPStan | **0 erros** |
| TypeScript (`tsc --noEmit`) | **0 erros** (eram 12) |
| Build do frontend (`npm run build`) | aprovado |
| Homologação HTTP real | ver seção 2.1 |

Os 3 pulados são testes de 2FA (`Settings/SecurityTest`), que dependem de recurso desativado na configuração.

## 13. Problemas encontrados e corrigidos

1. **500 ao criar cliente**: o model não recarregava o default de `person_type` após `create`. Corrigido com `refresh()`.
2. **Serviço trocado para `on_request` retornava 422** quando o valor antigo permanecia. Corrigido: sem valor informado, o valor é limpo.
3. **Telefone de profissional não era normalizado**, quebrando a busca por dígitos. Corrigido.
4. **PHPStan**: lista de IDs sem tipo `list`, e `?->` desnecessário. Corrigidos na origem, sem supressão.
5. **Frontend**: falta de `import '@inertiajs/core'` (12 erros TS).
6. **Driver da homologação HTTP**: cookie `XSRF-TOKEN` precisava ser decodificado (`%3D` → `=`) antes do header.
7. **Porta 8000 já ocupada** por outro `artisan serve` do mesmo projeto, iniciado antes desta sessão. Homologação usou esse servidor.
8. Dois erros de roteiro de teste (cookie de sessão trocado, data de telefone ambígua) — corrigidos no roteiro, sem mudança de código.

## 14. Pendências

1. **Chrome não instalado**: homologação no navegador não executada.
2. **RootAdmin**: primeiro provisionamento pendente, com terminal interativo.
3. **MySQL 8 não testado** (servidor local é MariaDB 11.8).
4. Suspensão de tenant por RootAdmin **não** exercitada por HTTP (apenas automatizada e por simulação no banco).
5. Suspensão de **usuário** não tem endpoint de administração (só por banco ou comando futuro).
6. Clientes ainda não têm vínculo com solicitações, conversas ou agendamentos (preparado, não implementado).
7. Categorias não são seedadas (por decisão: cada empresa cria as próprias).
8. Dados de homologação criados no banco `vetorsmart` com prefixo `[HOMOLOG]`, para remoção posterior.
9. Commit e push ainda não realizados (ver seção 15).

## 15. Recomendações para o Backend 03

- Antes da agenda, validar MySQL 8 e instalar um navegador para homologar o frontend.
- Reutilizar `isAvailableForScheduling()` de serviços e profissionais como filtro obrigatório.
- Implementar a FK composta de agendamento `(tenant_id, customer_id)`, `(tenant_id, professional_id)`, `(tenant_id, service_id)`, seguindo o padrão já usado.
- Definir endpoint de suspensão de usuário pela administração do tenant.
