# VETORSMART-OPERATIONS-01 — Central de trabalho e produtividade do prestador

Data: 20/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

**Restrições cumpridas:**
- Sem push, deploy ou VPS.
- **Sem migrations.**
- Sem dependências novas.
- Sem alterações em Efí, cobrança, autenticação, RootAdmin, Portal Regional ou sites públicos.

**Consolidação da JOURNEY-01:** commit `9c061a8`. Ficaram fora do commit `correio.md` e `executed.md`; nenhuma senha aparece no diff.

## Parecer final

**Concluída para a central de trabalho; a agenda ficou sem alterações, a seu pedido.**

O painel do assinante virou uma central operacional:
- **Pendências:** o que exige ação, das mais urgentes para as menos.
- **Ações rápidas:** usam as rotas e regras existentes.
- **Agenda:** visitas de hoje e dos próximos dias.
- **Resumo:** números do período.

O celular é tratado como interface principal.

**Agenda (seção 5 do correio):** as melhorias da tela Agenda foram iniciadas e recusadas por você. As alterações incompletas foram desfeitas, e a tela está exatamente como no commit `9c061a8`. O que a agenda já oferecia continua igual: visões por dia, semana e mês, profissional, situação, bloqueios, remarcar e cancelar.

## 1. Funcionalidades existentes reaproveitadas

- **Ações e validações:** todas as ações já existiam, com seus serviços e validações de estado: triagem, agendamento, início, conclusão com valor, registro do valor, cancelamento com motivo, remarcação e "assumir" atendimento da IA. Também as regras de transição de `RequestStatus`.
- **Permissões:** `ServiceRequestPolicy` (`manage`, `recordAmount`) e `TenantPolicy::handleAssistant`.
- **Solicitações paradas:** o escopo `ServiceRequest::stalled` (GROWTH-01) define quais solicitações estão sem movimentação.
- **Retorno ao painel:** os endpoints das ações devolvem o usuário à página de onde ele veio, então as ações rápidas voltam ao painel.

Nenhuma regra de negócio foi copiada para os componentes.

## 2. Funcionalidades implementadas

**`App\Services\Operations\WorkCenter`:** filas de trabalho da empresa. Cada fila faz uma contagem e uma lista curta (5 itens), com relações carregadas antecipadamente.

**Ordem das pendências, da mais urgente para a menos:**
1. Novas aguardando triagem.
2. Atendimento IA aguardando a equipe.
3. Visitas atrasadas: o horário passou e o atendimento não foi iniciado nem encerrado.
4. Triadas aguardando agendamento.
5. Em andamento.
6. Sem movimentação recente.
7. Concluídos sem valor registrado.

Só aparecem as filas que têm algo a fazer.

**Agenda no painel:**
- **Agenda de hoje:** no fuso horário da empresa, até 20 visitas.
- **Próximos agendamentos:** de amanhã até 7 dias.

**Resumo:** solicitações em aberto, visitas de hoje, concluídos hoje e concluídos nos últimos 30 dias. Os cadastros ativos e os cancelamentos aparecem em uma linha.

**Ações rápidas por item**, exibidas só quando a situação permite e o usuário tem permissão. O servidor valida a transição novamente a cada ação.

| Situação | Ações |
|---|---|
| Solicitada | Fazer triagem (vai direto ao formulário), Abrir, Cancelar com motivo |
| Triada | Agendar (vai direto ao formulário de agendamento), Abrir, Cancelar |
| Agendada, visita hoje ou atrasada | Iniciar execução, Remarcar, Abrir, Cancelar |
| Agendada, visita futura | Remarcar, Abrir, Cancelar (iniciar fica para o dia da visita) |
| Em andamento | Concluir, com valor opcional; Abrir |
| Concluída sem valor | Registrar valor |
| Conversa da IA aguardando | Assumir atendimento |

**Proteções das ações:**
- **Cancelar:** é oferecido só quando a transição é permitida. Uma solicitação em andamento não pode ser cancelada; ela é concluída ou registrada como não realizada. O teste encontrou esse caso, e ele foi corrigido.
- **Clique duplo ou tela desatualizada:** o botão fica desabilitado durante o envio. Uma repetição é recusada pelo servidor, e a mensagem aparece ao lado da ação (testado).
- **Motivo obrigatório:** o cancelamento só é enviado com um motivo escrito, e o servidor também recusa motivo vazio.

**Celular:**
- botões de ação com 40 px de altura;
- links "Ver todas" e "Abrir lista" com área de toque de 40 a 44 px;
- sem rolagem horizontal;
- a agenda de hoje sobe para o topo quando há visitas;
- estado vazio claro;
- tema claro e escuro.

**Detalhe da solicitação:** os cartões de triagem e de agendamento ganharam âncoras (`#triagem`, `#agendamento`), usadas pelos atalhos do painel.

## 3. Arquivos

**Novos:**
- `app/Services/Operations/WorkCenter.php`
- `tests/Feature/Operations/WorkCenterTest.php`
- `docs/VETORSMART-OPERATIONS-01.md`

**Alterados:**
- `app/Http/Controllers/DashboardController.php`: usa o `WorkCenter`; o cálculo antigo de indicadores foi substituído pelas filas e pelo resumo.
- `resources/js/pages/dashboard.tsx`
- `resources/js/pages/tenant/requests/show.tsx`: âncoras.
- `tests/Feature/Operations/LocalTimeDisplayTest.php`: a verificação de "visitas de hoje no fuso da empresa" passou para o novo formato.

**Desfeitos:** `resources/js/pages/tenant/agenda/index.tsx` e `app/Http/Controllers/Tenant/AgendaController.php` voltaram ao estado do commit `9c061a8`.

**Migrations:** nenhuma.

## 4. Testes e verificações

| Verificação | Resultado |
|---|---|
| `WorkCenterTest` (novo): ordem e conteúdo das filas, ação certa para cada situação, agenda de hoje e próximos no fuso da empresa, isolamento entre empresas, permissões do Operador, ações rápidas pelas rotas existentes, recusa de repetição, cancelamento só com motivo, estado vazio | **6 de 6** |
| Testes afetados (Operations, Assistant, Tenancy, Dashboard, login e situação da conta) | **401 testes: 392 aprovados, 9 ignorados (já eram antes), 0 falhas** |
| PHPStan, Pint, TypeScript e build | ok |
| Navegador headless, nas 3 empresas de demonstração (2 Owners e 1 Operador), em celular com tema escuro e desktop com tema claro | **6 de 6**: sem rolagem horizontal, tema correto, sem erros, filas e ações coerentes com a base, diálogo de cancelamento bloqueado sem motivo |

Nenhuma ação foi enviada na verificação visual, para não alterar a base de demonstração. As ações estão cobertas pelos testes.

## 5. Pendências

1. **Agenda:** as melhorias da seção 5 não foram feitas, a seu pedido: diferenciação visual por situação, filtro por profissional, dias vazios compactos e atalho para iniciar.
2. **Títulos dos itens:** os títulos dos itens das filas são links de texto com menos de 32 px de altura. O botão "Abrir", de 40 px, leva ao mesmo lugar.
3. **"Agendar" no painel:** abre o formulário de agendamento da solicitação, e não a agenda com a solicitação já selecionada (essa opção dependia da agenda).

## 6. Git

- `9c061a8`: JOURNEY-01.
- **OPERATIONS-01 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**

Nenhuma outra etapa foi iniciada.
