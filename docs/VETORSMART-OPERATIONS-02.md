# VETORSMART-OPERATIONS-02 — Gestão de Clientes, Histórico e Relacionamento

Data: 20/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

**Restrições cumpridas:**
- Sem push, deploy, VPS ou DNS.
- **Sem migrations e sem alterações estruturais.**
- Sem dependências novas.
- Não foram alterados: agenda, Efí, cobrança, autenticação, RootAdmin, Portal Regional, sites públicos, jornada pública e recepcionista IA.

**Consolidação da OPERATIONS-01:** commit `69f7cfd`. O diff foi revisado e nenhuma senha aparece nele; `correio.md` e `executed.md` ficaram fora do commit.

## Parecer final

**Concluída.** O painel do assinante ganhou:
- a **carteira de clientes**, com indicadores e filtros de relacionamento;
- a **ficha completa do cliente**, com histórico real, resumo comercial e contato rápido;
- a ligação entre as telas de solicitações e a ficha.

Todos os números são calculados a partir das solicitações e dos agendamentos gravados. Nenhum valor é armazenado à parte, e nenhum dado é demonstrativo.

| # | Critério de aceite | Situação |
|---|---|---|
| 1 | Carteira funcional | Atendido: busca, situação, período, segmentos, paginação |
| 2 | Ficha com histórico real | Atendido |
| 3 | Solicitações relacionadas aos clientes | Atendido: vínculo existente `customer_id`, com links nos dois sentidos |
| 4 | Indicadores a partir de dados persistidos | Atendido |
| 5 | Filtros de relacionamento | Atendido: 30, 60 e 90 dias |
| 6 | Sem vazamento entre empresas | Atendido (testado) |
| 7 | Desktop e celular | Atendido (12 de 12 telas) |
| 8 | Regras anteriores preservadas | Atendido: 394 testes afetados aprovados |

## 1. Recursos existentes reaproveitados

- **Modelo `Customer`:**
  - busca por nome, e-mail e dígitos do telefone (`scopeSearch`);
  - telefones guardados só com dígitos, o que já permite comparar sem destruir o dado;
  - situação ativo ou arquivado.
- **`CustomerService`:** cadastro sem duplicar telefone ou e-mail na mesma empresa, edição, arquivamento e auditoria.
- **Associação automática:** `AssistantOperations::findOrCreateCustomer` liga as solicitações do site, do portal e da IA ao cliente já existente.
- **Histórico:** o paginador de solicitações da tela de edição (`CustomerController::history`) foi reaproveitado na ficha.
- **Permissões:**
  - `CustomerPolicy` para ver e editar;
  - `TenantPolicy::viewGrowth` para os valores financeiros, a mesma regra do Meu Crescimento.
- **Dinheiro:** `App\Support\Money`, com valores em centavos inteiros.

## 2. Funcionalidades implementadas

**`App\Services\Customers\CustomerPortfolio`:** números por cliente calculados por subconsultas na própria consulta da lista, sem N+1. Todas as subconsultas exigem cliente e empresa iguais.

| Definição | Regra |
|---|---|
| Serviços concluídos | Solicitações do cliente com situação "concluída". Canceladas e não realizadas nunca contam. |
| Valor registrado | Soma, em centavos, dos valores informados nos serviços concluídos. Serviços sem valor ficam fora da soma e são contados à parte. É valor declarado, não pagamento recebido. |
| Recorrente | 2 ou mais **serviços concluídos**. Solicitações criadas não bastam. |
| Novo no período | Primeiro contato (o cadastro do cliente) nos últimos 30, 60 ou 90 dias. |
| Sem atendimento recente | Já teve serviço concluído, mas nenhum no período. Vale só para o período analisado e não indica abandono. |
| Com solicitação em aberto | Solicitada, triada, agendada ou em andamento. |
| Com agendamento futuro | Agendamento ativo a partir de agora. |
| Frequência | Média de dias entre o primeiro e o último serviço concluído, calculada a partir do 2º serviço. |

**Carteira (`/clientes`):**
- **5 indicadores**, que também servem de filtro: novos, recorrentes, sem atendimento recente, em aberto e com agendamento futuro.
- **Período:** 30, 60 ou 90 dias.
- **Busca** por nome, e-mail ou telefone, e filtro de situação.
- **Por cliente:** selos de novo, recorrente e arquivado; serviços concluídos; data do último atendimento; próximo agendamento ou solicitações em aberto; valor registrado (só para Owner e Admin).
- **Ações:** "Abrir ficha", Editar e Arquivar.
- **Formato:** os clientes aparecem em cartões, e não em tabela, então não há rolagem horizontal no celular.
- **Estados vazios:** explicam como os clientes chegam à carteira.

**Ficha (`/clientes/{id}`):**
- **Identificação:** nome, contato, endereço, observações e "cliente desde".
- **Contato rápido:** botões Ligar (`tel:`), WhatsApp (link `wa.me`, sem API) e E-mail (`mailto:`), montados com os dados já cadastrados. Sem disparos automáticos nem provedores novos.
- **Resumo:** serviços concluídos, solicitações, cancelados ou não realizados, frequência e último serviço.
- **Valor registrado:** só para Owner e Admin, com o aviso de que é declarado e de que cancelados não entram.
- **Próximos agendamentos** e **histórico** cronológico (paginado), com link para cada solicitação.

**Integração com os atendimentos:**
- Na lista e no detalhe de solicitações, o nome do cliente leva à ficha.
- Da ficha, cada solicitação e cada agendamento levam ao detalhe.
- A origem comercial e as regras de associação e de não duplicação foram mantidas sem alteração.

## 3. Segurança e integridade

- **Isolamento:**
  - todas as consultas exigem a empresa ativa;
  - um cliente de outra empresa responde 404 (testado);
  - registros de outra empresa nunca entram nos números (testado).
- **Permissões:**
  - qualquer membro vê a carteira e a ficha;
  - os valores aparecem só para Owner e Admin (testado com Operador);
  - editar e arquivar seguem a `CustomerPolicy` existente.
- **Validação:** período e segmento só aceitam valores conhecidos; um valor inválido cai no padrão.
- **Dados:**
  - nenhum valor financeiro é alterado na edição cadastral, porque o cadastro não toca nas solicitações;
  - não há fusão automática de cadastros;
  - o histórico existente foi preservado.

## 4. Arquivos

**Novos:**
- `app/Services/Customers/CustomerPortfolio.php`
- `resources/js/pages/tenant/customers/show.tsx`
- `tests/Feature/Operations/CustomerPortfolioTest.php`
- `docs/VETORSMART-OPERATIONS-02.md`

**Alterados:**
- `app/Http/Controllers/Tenant/CustomerController.php`: números e filtros na carteira, ficha e links de contato.
- `routes/tenant.php`: rota `tenant.customers.show`.
- `resources/js/pages/tenant/customers/index.tsx`: carteira em cartões com indicadores.
- `app/Http/Controllers/Tenant/ServiceRequestController.php`: `customer_id` nas linhas.
- `resources/js/pages/tenant/requests/index.tsx` e `show.tsx`: link para a ficha do cliente.

**Migrations:** nenhuma. Os índices existentes da chave `(tenant_id, customer_id)` atendem às consultas.

## 5. Validações

| Verificação | Resultado |
|---|---|
| `CustomerPortfolioTest` (novo): números por cliente, cancelados fora da receita, valores ocultos para o Operador, segmentos e período, recorrência por serviços concluídos, busca, ficha (resumo, frequência, contato, histórico), isolamento, navegação entre solicitação e ficha | **7 de 7** |
| Testes de clientes existentes (`CustomerTest`, `CustomerHistoryTest`) | aprovados (20 de 20 junto com o novo arquivo) |
| Testes afetados: Operations, API de clientes, telas de gestão, isolamento web, painel e outros que usam clientes | **403 testes: 394 aprovados, 9 ignorados (já eram antes), 0 falhas** |
| PHPStan, Pint, TypeScript e build | ok |
| Navegador headless (Owner da Silva Elétrica e Operador da ClimaSul; celular com tema escuro e desktop com tema claro): carteira, filtro "Sem atendimento recente" e ficha | **12 de 12 telas**: sem rolagem horizontal, tema correto, sem erros. O Owner vê os valores; o Operador não vê nenhum. |

**Ajuste feito durante a verificação:** os clientes da base de demonstração têm só WhatsApp e e-mail, e por isso o botão "Ligar" não aparecia. Agora o número do WhatsApp também é usado para ligar quando não há outro telefone cadastrado.

## 6. Limitações e pendências

1. **"Novo no período"** usa a data do cadastro do cliente como primeiro contato. Clientes cadastrados depois do primeiro pedido, por importação ou cadastro manual, ficam com essa data.
2. **Possíveis duplicidades:** a identificação depende da comparação por telefone ou e-mail já existente. Cadastros com dados diferentes para a mesma pessoa não são unidos; a fusão continua manual, por decisão do correio.
3. **"Serviços"** no correio foi interpretado como os atendimentos executados (solicitações). O catálogo de serviços não ganhou link para clientes.
4. **Meu Crescimento:** os indicadores de clientes não foram levados para lá nesta etapa. O serviço `CustomerPortfolio` está pronto para essa evolução.

## 7. Git

- `69f7cfd`: OPERATIONS-01.
- **OPERATIONS-02 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**

Nenhuma outra etapa foi iniciada.
