# VETORSMART-BACKEND-05.2 — Homologação e consolidação do atendimento

**Projeto:** VetorSmart  
**Diretório oficial:** `gateway/vetorsmart/`  
**Tecnologia:** Laravel + MySQL  
**Data:** 13/11/2026

## Objetivo

Consolidar a integração entre atendimento público, triagem, agenda e painel operacional, corrigindo pendências da BACKEND-05.1 sem reconstruir funcionalidades existentes.

Priorizar simplicidade, confiabilidade, segurança e usabilidade mobile.

## 1. Atendimento humano sem identificação

Atualmente, visitantes que solicitam um atendente antes de informar nome e telefone não geram solicitação.

Implementar:

- Fila de conversas aguardando atendimento humano, mesmo sem cliente cadastrado.
- Identificação do visitante como "Não identificado".
- Possibilidade de o operador assumir a conversa e solicitar os dados necessários.
- Conversão posterior da conversa em solicitação de serviço.
- Preservação integral do histórico.
- Proibição de criar clientes fictícios.

A fila de conversas não deve depender da criação prévia de uma solicitação formal.

## 2. Horários indisponíveis

Alterar o comportamento atual.

Quando o horário desejado não estiver disponível:

1. Informar claramente que o horário não foi reservado.
2. Consultar alternativas reais usando o AppointmentScheduler.
3. Apresentar até três horários disponíveis próximos da preferência.
4. Permitir escolher outra data.
5. Oferecer encaminhamento humano.

Se a indisponibilidade ocorrer durante a confirmação, não confirmar nem substituir automaticamente o horário escolhido. Oferecer alternativas somente após nova consulta e exigir nova confirmação do visitante.

Preservar idempotência, transações e isolamento por tenant.

## 3. Atendimento e experiência mobile

Revisar o widget `/atendimento`:

- Interface responsiva.
- Campos e botões legíveis.
- Mensagens objetivas.
- Indicador de processamento.
- Tratamento de falhas de conexão.
- Prevenção de envios duplicados.
- Continuidade da conversa após atualização da página, conforme a política de sessão.
- Navegação acessível por teclado.
- Estados de carregamento e erro compreensíveis.

Não transformar o atendimento em formulário longo.

## 4. Privacidade e segurança

- Exibir aviso de privacidade antes da coleta de dados pessoais.
- Informar finalidade do uso de nome, telefone e informações do serviço.
- Disponibilizar link para a política de privacidade da empresa.
- Registrar apenas dados necessários.
- Verificar autenticação, autorização e segregação entre tenants.
- Aplicar limites contra abuso.
- Preservar proteção contra instruções maliciosas fornecidas pelo visitante.
- Não expor detalhes internos da aplicação nas mensagens públicas.

## 5. Integração com o painel

Garantir que o operador consiga:

- Visualizar atendimentos aguardando interação humana.
- Identificar origem "Site / IA".
- Consultar resumo e histórico.
- Assumir e devolver atendimento.
- Identificar o visitante.
- Converter atendimento em solicitação quando aplicável.
- Consultar agendamento associado.
- Iniciar, acompanhar e concluir o serviço.

Evitar duplicação de registros e telas administrativas desnecessárias.

## 6. Concorrência e testes

Executar testes automatizados e homologação com MySQL.

Validar:

- Dois visitantes disputando o mesmo horário.
- Confirmações simultâneas.
- Reenvio de mensagens.
- Falhas durante persistência.
- Repetição de requisições após timeout.
- Conversas retomadas.
- Acesso entre tenants.
- Encaminhamento humano sem identificação.
- Conversão posterior em solicitação.

Um mesmo profissional não pode receber dois agendamentos conflitantes.

Nenhuma confirmação pode ser exibida antes da persistência bem-sucedida.

## 7. Homologação em navegador

Executar testes reais em desktop e mobile, utilizando ambiente disponível e seguro.

Cobrir:

- Site público → atendimento.
- Atendimento → agendamento.
- Atendimento → encaminhamento humano.
- Painel → triagem.
- Triagem → execução.
- Execução → conclusão.

Registrar evidências de cada cenário.

Se o navegador ou ambiente necessário não estiver disponível, registrar a impossibilidade. Não substituir a homologação por testes simulados e não declarar aprovação sem evidências.

## 8. Documentação

Atualizar:

`docs/VETORSMART-BACKEND-05-ATENDIMENTO-IA.md`

Documentar o comportamento implementado, fluxo de atendimento humano, agendamento, regras de confirmação, limitações e cenários de testes.

## 9. Restrições

- Não integrar provedor pago de IA nesta execução.
- Preservar fluxo determinístico funcional.
- Não alterar infraestrutura Docker compartilhada.
- Não implantar na VPS sem autorização.
- Não alterar regras comerciais.
- Não executar alterações destrutivas no banco.
- Não fazer commit sem autorização.
- Não modificar módulos não relacionados.

## 10. Relatório final obrigatório

Informar:

1. Arquivos modificados.
2. Funcionalidades implementadas.
3. Testes executados, aprovados, falhados e pulados.
4. Evidências de navegador.
5. Resultados dos testes de concorrência.
6. Pendências identificadas.
7. Riscos para produção.
8. Necessidade de migrações.
9. Situação de commit e deploy.

**Critério de aceite:** atendimento público e painel operando de ponta a ponta, com agendamentos seguros, encaminhamento humano funcional e experiência adequada no celular.

Não considerar concluída a homologação sem testes reais de navegador e concorrência.
