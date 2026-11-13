<<<<<<< HEAD
# VETORSMART-BACKEND-05.2 — Homologação e consolidação do atendimento
=======
# VETORSMART-SERVICES-01.4 — Isolamento de testes e homologação na VPS

**Projeto:** VetorSmart  
**Diretório:** `gateway/vetorsmart/`  
**Servidor:** VPS de produção  
**Banco:** MySQL 8.4  
**Prioridade:** Crítica

---

# VETORSMART-AUTH-UX-01 — Padronização da autenticação
>>>>>>> 576c9219af61b9d41bc77c75352a80b5ade2f900

**Projeto:** VetorSmart  
**Diretório oficial:** `gateway/vetorsmart/`  
**Tecnologia:** Laravel + MySQL  
**Data:** 13/11/2026

## Objetivo

<<<<<<< HEAD
Consolidar a integração entre atendimento público, triagem, agenda e painel operacional, corrigindo pendências da BACKEND-05.1 sem reconstruir funcionalidades existentes.
=======
Eliminar os bloqueios encontrados no SERVICES-01.3 e realizar a homologação operacional do módulo Serviços.

Não criar novos módulos nem alterar funcionalidades comerciais.

## 1. Preparação

Ler os relatórios anteriores e verificar o estado real do repositório, das dependências e dos containers.

A aplicação de produção está funcionando. Preservar essa condição.

Confirmar o commit implantado `5ead1ca`.

## 2. Ambiente isolado de testes

Criar um ambiente de execução de testes separado dos containers operacionais.

Requisitos:

- Utilizar as versões de PHP e dependências compatíveis com a aplicação.
- Utilizar banco de testes dedicado no MySQL 8.4.
- Não utilizar o banco `vetorsmart` nos testes.
- Não publicar portas externas desnecessárias.
- Não alterar o `.env` de produção.
- Não utilizar credenciais administrativas da produção na suíte.
- Manter os arquivos de build isolados do volume público.
- Não modificar os containers `vetorsmart`, `worker` e `scheduler`.

O banco `vetorsmart_test` já existe. Verificar se está vazio antes de utilizá-lo.

Criar usuário de testes com permissões limitadas exclusivamente ao banco de testes, caso necessário.

Não reutilizar o usuário `root`.

Não armazenar senhas nos relatórios ou no repositório.

## 3. Correções técnicas

### Pint

Identificar os sete arquivos reprovados e aplicar exclusivamente correções de formatação.

Não alterar regras de negócio.

### Wayfinder e TypeScript

Gerar os arquivos de rotas no ambiente isolado.

---

Corrigir o contraste e a legibilidade das telas de autenticação, padronizar a imagem de fundo, simplificar os requisitos de senha e corrigir o favicon do VetorSmart.
>>>>>>> 576c9219af61b9d41bc77c75352a80b5ade2f900

Priorizar simplicidade, confiabilidade, segurança e usabilidade mobile.

## 1. Atendimento humano sem identificação

Atualmente, visitantes que solicitam um atendente antes de informar nome e telefone não geram solicitação.

<<<<<<< HEAD
Implementar:
=======
`npx tsc --noEmit`

Corrigir somente problemas reais de tipagem ou geração.

### Build

Executar a compilação exclusivamente no ambiente isolado.

Não gravar em `public/build` compartilhado com a aplicação em execução.

## 4. Testes automatizados

Executar:

`php artisan test --compact`

`VETORSMART_CONCURRENCY=1 php artisan test --compact tests/Feature/Operations/MysqlConcurrencyTest.php`

`vendor/bin/pint --test`

`npx tsc --noEmit`

`npm run build`

Registrar resultados completos e diferenciar falhas de aplicação, infraestrutura e configuração.

Não utilizar banco de produção para testes automatizados.

## 5. Homologação no navegador

Acessar a aplicação real na VPS utilizando navegador.

Somente utilizar tenants de homologação.

### Desktop

Testar cadastro de serviços, profissionais, clientes, solicitações, triagem, agenda, remarcação, conflitos, execução, cancelamento, conclusão e histórico.

### Mobile

Executar o mesmo fluxo em viewport de 390 px.

### Multi-tenant

Comprovar isolamento das empresas A e B.

### Atendimento público

Configurar e validar o site público do tenant de homologação e o assistente.

Comprovar o fluxo:

Visitante → Atendimento → Solicitação → Triagem → Agendamento → Execução → Conclusão.

Não declarar aprovado caso algum estágio não seja executado.

Usar exclusivamente dados fictícios identificados como homologação, evitando qualquer comunicação externa involuntária.

## 6. Segurança operacional

Não executar operações destrutivas.

Não modificar cobrança, assinaturas, MFA, credenciais existentes ou dados de clientes.

Não realizar migrations no banco operacional.

Não reiniciar containers de produção sem necessidade e autorização.

Não apagar contas de homologação.

Se qualquer ação puder interromper a aplicação, registrar o risco e solicitar autorização específica antes de executá-la.

## 7. Entrega

Criar:

`docs/VETORSMART-SERVICES-01.4.md`

Atualizar `executed.md` com:

- Estado do ambiente de testes.
- Configurações realizadas, sem segredos.
- Correções aplicadas.
- Resultado dos testes.
- Resultado da homologação desktop.
- Resultado da homologação mobile.
- Resultado do atendimento público.
- Resultado do isolamento multi-tenant.
- Pendências e riscos.
- Estado final da produção.

Não declarar sucesso sem evidências reais.

**Critério final:** o módulo Serviços deve estar tecnicamente validado e operacionalmente homologado no ambiente real antes de receber autorização para uso comercial.

---

- Login
- Cadastro
- Recuperação de senha
- Redefinição de senha
- Confirmação de senha
- Autenticação em dois fatores
>>>>>>> 576c9219af61b9d41bc77c75352a80b5ade2f900

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
