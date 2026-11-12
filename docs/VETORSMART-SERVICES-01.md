# VETORSMART-SERVICES-01 — Integração, correção e homologação do módulo de Serviços

Data: 12/11/2026. Diretório: `gateway/vetorsmart/`. Banco: MySQL.

## 1. Diagnóstico funcional

Classificação por funcionalidade, a partir do código, dos testes existentes e da inspeção no navegador:

| Funcionalidade | Classificação | Observação |
|---|---|---|
| Cadastro e edição de serviços | Funcional e homologada (desktop) | Nome, duração, tipo e valor de preço, categoria, descrição. Persistência confirmada após recarregar. |
| Associação ao tenant | Funcional | Isolamento verificado (empresa B não vê serviços da empresa A). |
| Profissionais e janela de disponibilidade | Funcional e homologada (desktop) | Janela semanal persistida. |
| Vínculo serviço → profissional | Funcional e homologada (desktop) | Persistência após recarregar. Lista vazia não remove vínculos em teste. |
| Solicitações pelo painel | Funcional e homologada (desktop) | Criação, triagem (serviço e prioridade). |
| Agendamento | Funcional e homologada (desktop) | Horário livre agendado; horário ocupado recusado pela regra de disponibilidade. |
| Conflito de agenda | Funcional (testes e desktop) | Recusa com mensagem "Este horário já está ocupado para o profissional." |
| Remarcação | Funcional (testes); navegador pendente | Corrigido: retorno de erro e resposta JSON. Recusa no navegador ainda pendente de confirmação (ver 6). |
| Cancelamento com motivo | Funcional e homologada (desktop) | Motivo registrado no histórico. |
| Início e conclusão | Funcional e homologada (desktop) | Conclusão não registra receita. |
| Histórico operacional | Funcional e homologada (desktop) | Transições de status visíveis. |
| Agenda | Corrigida | Antes listava só agendamentos ativos; agora mantém o histórico. |
| Indicadores do dashboard | Funcional e homologada (desktop) | Valores reais. |
| Comunicação backend × frontend | Corrigida | Erro de remarcação era silencioso e a resposta 405 falhava a interface. |
| Solicitação pelo canal público | Implementada, não integrada ao teste de ponta a ponta | Requer site publicado, domínio verificado e assistente ativado na empresa. |

## 2. Correções executadas

1. **Agenda**: consulta lista todos os status; apenas agendamentos ativos oferecem remarcar e cancelar (`can_change`). Arquivos: `app/Http/Controllers/Tenant/AgendaController.php`, `resources/js/pages/tenant/agenda/index.tsx`.
2. **Remarcação com erro silencioso**: o formulário envia JSON e mostra a mensagem do servidor junto ao campo; sucesso recarrega a agenda.
3. **Remarcação com HTTP 405**: resposta JSON 200 para chamadas JSON, pois o redirecionamento PATCH era seguido como PATCH para rota só-GET.
4. **Validação de e-mail em inglês**: mensagem em português em todos os campos de e-mail, via `resources/js/lib/email-validation.ts`.
5. **Teste de remarcação (causa real)**: a fixture gravava horários locais, e a consulta de conflito compara em UTC. Corrigida a fixture (UTC). A expectativa 422 foi mantida, e o teste agora verifica que o agendamento original permanece inalterado.

## 3. Arquivos modificados nesta etapa

- `app/Http/Controllers/Tenant/AgendaController.php`
- `resources/js/pages/tenant/agenda/index.tsx`
- `resources/js/lib/email-validation.ts` (novo)
- `resources/js/pages/auth/login.tsx`, `register.tsx`, `forgot-password.tsx`, `reset-password.tsx`, `settings/profile.tsx`, `tenant/team/index.tsx`
- `tests/Feature/Operations/AgendaHistoryTest.php` (novo)

Não há migrations nesta etapa. Não houve exclusão de dados.

## 4. Testes

- Suíte completa: 337 testes, 332 aprovados, 0 falhas, 5 pulados (os cinco de concorrência MySQL, pulados por padrão).
- Concorrência MySQL (`VETORSMART_CONCURRENCY=1`): 5 aprovados.
- Agenda (`AgendaTest` e `AgendaHistoryTest`): 17 aprovados.
- Pint, `tsc --noEmit` e `npm run build`: aprovados.

## 5. Evidências de homologação

- **Validado por testes automatizados**: regras de agenda, conflito, remarcação com recusa, histórico, isolamento e concorrência MySQL.
- **Homologado anteriormente no navegador (desktop, 1440 px)**: fluxo operacional completo descrito na seção 1, com dados reais de homologação.
- **Pendente de homologação remota via VPN**: ver `docs/VETORSMART-SERVICES-01.2.md`.

## 6. Pendências e riscos

1. Confirmação no navegador da recusa de remarcação para horário ocupado (o servidor recusa, verificado no banco e por teste).
2. Fluxo completo em 390 px (mobile).
3. Solicitação pública: site publicado, domínio verificado e assistente ativado, e então solicitação até a conclusão.
4. Verificação da configuração real do ambiente de produção (ver SERVICES-01.2).
5. Contas de homologação de prestador (`srv.owner.a@example.test`, `srv.owner.b@example.test`) continuam ativas; dados `[HOMOLOG-SRV]` permanecem. Remoção depende de autorização.
6. Validação de concorrência em MySQL 8 pendente; a suíte roda em MariaDB/MySQL local.
