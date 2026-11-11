# VETORSMART-BACKEND-05 — Atendimento Inteligente, API Operacional e Recepcionista IA

**Projeto:** VetorSmart  
**Empresa:** ABrasilSistemas  
**Data:** 11/11/2026  
**Diretório oficial:** `gateway/vetorsmart/`  
**Prioridade:** Alta

## 1. Instrução

Ler `correio.md`, `executed.md` e os relatórios BACKEND-01 até BACKEND-04.3 disponíveis no projeto.

Inspecionar a arquitetura existente antes de implementar.

O objetivo é desenvolver a primeira versão funcional do atendimento inteligente, integrada ao catálogo de serviços, profissionais, clientes, solicitações e agenda.

Não duplicar a lógica operacional existente.

Preservar:

- Laravel, React e Inertia.
- Banco de dados MySQL/MariaDB conforme configuração existente.
- Isolamento multi-tenant.
- Autenticação e permissões.
- Interface, login e paleta visual aprovados.
- Timezone por tenant.
- Regras transacionais de agendamento.

Não executar commit, push, merge ou deploy.

## 2. API operacional

Criar uma camada de serviços internos reutilizáveis pela recepcionista IA, evitando que o modelo acesse diretamente o banco de dados.

Disponibilizar operações para:

1. Listar serviços ativos do tenant.
2. Consultar descrições, duração e preços.
3. Consultar profissionais habilitados.
4. Consultar horários disponíveis.
5. Criar ou localizar cliente com regras de identificação e prevenção de duplicatas.
6. Criar solicitação.
7. Encaminhar solicitação para triagem.
8. Solicitar agendamento.
9. Consultar status do atendimento.
10. Encaminhar para atendimento humano.

Reutilizar os services, policies e validadores existentes.

Não permitir que endpoints públicos recebam livremente um `tenant_id` e acessem outro tenant.

Separar contratos internos da IA e rotas públicas necessárias ao atendimento.

Validar todos os parâmetros no backend.

## 3. Recepcionista IA por segmento

Criar uma estrutura de atendimento especializada por segmento de negócio.

Exemplos de segmentos suportados pela arquitetura:

- Ar-condicionado e climatização.
- Eletricistas.
- Encanadores.
- Manutenção residencial.
- Assistência técnica.
- Limpeza e conservação.
- Serviços profissionais em geral.

Não criar uma implementação independente para cada segmento.

Utilizar uma base comum e configurações especializadas.

Cada tenant deverá poder configurar:

- Nome comercial.
- Saudação inicial.
- Apresentação da empresa.
- Serviços atendidos.
- Região de atendimento.
- Horário comercial.
- Informações comerciais adicionais.
- Orientações específicas.
- Condições para encaminhamento humano.
- Ativação ou desativação da IA.

Essas configurações devem permanecer isoladas por tenant.

O modelo nunca deve receber dados internos de outros tenants.

## 4. Conversas e identificação

Implementar persistência de conversas contendo:

- Tenant.
- Canal de origem.
- Identificador seguro da sessão.
- Cliente identificado, quando existir.
- Mensagens.
- Datas e horários.
- Status da conversa.
- Serviço de interesse.
- Solicitação vinculada.
- Agendamento vinculado.
- Indicador de intervenção humana.

Adotar controle de retenção configurável e medidas de proteção de dados compatíveis com a LGPD.

Permitir atendimento inicial sem exigir cadastro completo.

Coletar somente os dados necessários para avançar.

Evitar perguntas repetidas quando a informação já estiver disponível na conversa.

## 5. Fluxo de atendimento

Implementar o fluxo:

**Saudação → Identificação da necessidade → Qualificação → Serviço → Disponibilidade → Solicitação/Agendamento → Confirmação.**

A recepcionista deverá:

1. Entender o que o cliente deseja.
2. Identificar se o serviço existe no catálogo.
3. Consultar informações reais.
4. Solicitar dados faltantes.
5. Consultar disponibilidade.
6. Apresentar opções reais de horário.
7. Solicitar confirmação do cliente.
8. Executar o agendamento por operação transacional autorizada.
9. Informar o resultado confirmado pelo backend.
10. Registrar o atendimento e seus eventos.

Quando não houver serviço ou horário disponível, oferecer encaminhamento humano.

A IA não pode inventar valores, disponibilidade, diagnóstico ou prazo.

Não deve informar que um agendamento foi confirmado antes do sucesso da transação.

## 6. Ferramentas controladas da IA

Criar ferramentas internas com contratos tipados e validação explícita.

Exemplos de operações:

- `list_services`
- `get_service_details`
- `list_available_slots`
- `create_service_request`
- `request_appointment`
- `get_request_status`
- `handoff_to_human`

Esses nomes são sugestões; adaptar à arquitetura existente.

A IA poderá solicitar uma operação, mas a aplicação deverá validar autorização, regras comerciais, tenant, parâmetros e integridade antes de executá-la.

Limitar operações permitidas por estado da conversa.

Aplicar idempotência nas operações que criam solicitações e agendamentos.

Não permitir que instruções enviadas por clientes substituam políticas de segurança ou regras do sistema.

## 7. Integração com agenda

A IA deve utilizar exclusivamente o mecanismo de disponibilidade existente.

Regras obrigatórias:

- Respeitar profissionais habilitados.
- Respeitar serviços ativos.
- Respeitar duração.
- Respeitar horário comercial.
- Respeitar bloqueios.
- Respeitar timezone do tenant.
- Impedir conflitos e reservas duplicadas.
- Confirmar disponibilidade novamente no momento da reserva.
- Registrar corretamente a origem do agendamento.

Não criar uma segunda agenda nem reservar horários apenas pela resposta textual da IA.

## 8. Atendimento humano

Permitir transferência da conversa para um operador autorizado.

Implementar:

- Estado aguardando atendimento.
- Identificação de conversa assumida.
- Histórico visível ao operador.
- Resumo contextual para continuidade.
- Bloqueio de respostas automáticas enquanto o humano conduz o atendimento.
- Encerramento ou devolução à IA por ação autorizada.

Preservar o contexto para que o cliente não precise repetir todas as informações.

## 9. Interface de atendimento

Criar uma primeira interface funcional para atendimento no site público do tenant, utilizando a identidade visual existente.

O chat deverá ser responsivo e acessível.

Preservar o padrão do VetorSmart:

- Desktop 1440 px.
- Mobile 390 px.
- Sem overflow.
- Mensagens claras.
- Indicador de atendimento.
- Estados de carregamento e erro.
- Identificação visual da empresa.

Não adicionar chatbot indiscriminadamente às áreas administrativas.

A interface de atendimento ao visitante e a caixa de entrada operacional dos atendentes devem ter responsabilidades distintas.

## 10. IA de baixo custo

O VetorSmart deverá suportar provedores de IA por configuração, com a implementação inicial escolhida conforme os recursos realmente existentes no projeto.

Não presumir credenciais ou provedores já configurados.

Implementar:

- Interface de provedor substituível.
- Configuração segura de chave.
- Limite de tokens.
- Limite de mensagens e requisições.
- Histórico contextual compacto.
- Controle de custo por tenant.
- Tratamento de indisponibilidade.
- Possibilidade de desativar a IA.
- Fluxo básico de atendimento sem IA quando o provedor estiver indisponível.

Nunca enviar senhas, tokens, dados de outros tenants ou informações desnecessárias ao provedor.

Não criar consumo pago obrigatório para executar os testes locais.

## 11. Indicadores iniciais

Registrar eventos que permitam medir futuramente:

- Conversas iniciadas.
- Conversas qualificadas.
- Solicitações criadas.
- Agendamentos realizados.
- Encaminhamentos humanos.
- Falhas de atendimento.
- Consumo de IA.
- Origem do contato.

Não desenvolver o dashboard comercial completo nesta etapa.

Preparar os dados para o BACKEND-06.

## 12. Correções operacionais pontuais

Aproveitar esta etapa para resolver somente estes ajustes, sem ampliar indevidamente o escopo:

- Mensagens de validação de e-mail em português.
- Configurações do operador estritamente em modo somente leitura, sem ações administrativas.
- Testes de regressão para garantir que falhas de formulário não removam acidentalmente profissionais associados a um serviço.

Não modificar `homolog:purge` nem excluir registros de homologação sem autorização.

Não alterar horários históricos sem confirmação.

Manter as pendências de MySQL 8 e licenciamento das imagens explicitamente registradas.

## 13. Segurança

Verificar:

- Isolamento entre tenants.
- Autorização nas operações internas.
- Rate limiting nas rotas públicas.
- Proteção contra abuso e automação.
- Validação de entradas.
- Prevenção contra prompt injection.
- Proteção contra vazamento de dados.
- Idempotência das operações.
- Logs sem informações sensíveis.
- Retenção de dados e acesso autorizado às conversas.

A IA não deve poder executar ações administrativas.

## 14. Testes obrigatórios

Criar testes para:

1. Conversa de atendimento completa.
2. Serviço existente.
3. Serviço inexistente.
4. Serviço inativo.
5. Consulta de disponibilidade.
6. Horário indisponível.
7. Agendamento com sucesso.
8. Conflito de agendamento.
9. Encaminhamento humano.
10. Tenant incorreto.
11. Resposta inválida do modelo.
12. Falha do provedor de IA.
13. Limite de consumo excedido.
14. Repetição de requisição de agendamento.
15. Tentativa de manipular instruções do assistente.

Usar provedor simulado para testes automatizados determinísticos.

Executar:

```bash
php artisan test --compact
vendor/bin/pint --dirty
npx tsc --noEmit
npm run build
```

Homologar no navegador quando houver acesso autorizado.

## 15. Entrega

Criar:

`docs/VETORSMART-BACKEND-05-ATENDIMENTO-IA.md`

Incluir:

- Arquitetura implementada.
- Migrations.
- APIs e contratos.
- Ferramentas disponíveis.
- Configurações de IA.
- Fluxos de atendimento.
- Integração com agenda.
- Segurança.
- Testes executados.
- Evidências do navegador.
- Consumo e limites.
- Pendências e próximos passos.

Atualizar `executed.md` com o resultado fiel da execução.

Não declarar integração real com o provedor de IA se apenas mocks tiverem sido utilizados.

**Não executar commit, push, merge ou deploy.**

## 16. Critério principal de aceite

Um visitante deve conseguir abrir o atendimento do tenant, informar sua necessidade, receber informações de serviços existentes, consultar horários disponíveis e, após confirmação explícita, criar um agendamento real e persistido.

Quando a IA não puder resolver, o atendimento deverá ser transferido para um humano sem perda do contexto.

Todo o fluxo deve utilizar os recursos operacionais existentes do VetorSmart, sem duplicação de regras.