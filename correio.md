# CLAUDE CODE — VETORSMART-BACKEND-02
## Homologação da Fundação e Implementação do Núcleo Operacional

**Projeto:** VetorSmart  
**Empresa:** ABrasilSistemas  
**Diretório:** `gateway/vetor-smart/`  
**Stack:** Laravel + MySQL  
**Data:** 09/11/2026

**Objetivo:** Homologar os recursos essenciais do Backend 01 e implementar o núcleo operacional de clientes, serviços, profissionais e configurações de atendimento.

---

## 1. Instruções de execução

O Backend 01 já foi implementado e possui relatório em:

`docs/VETORSMART-BACKEND-01.md`

Antes de iniciar:

1. Ler o relatório anterior.
2. Inspecionar o código existente.
3. Verificar `git status` e a branch atual.
4. Preservar migrations, dados, autenticação e multi-tenancy existentes.
5. Reutilizar `TenantContext`, `TenantResolver`, `TenantPolicy` e os serviços já implementados.
6. Não instalar pacotes desnecessários.
7. Não recriar estruturas que já existam.
8. Não realizar alterações destrutivas.

**Importante:** MySQL é o banco definitivo. Não configurar nem testar PostgreSQL.

Implementar efetivamente as funcionalidades. Não entregar apenas planejamento ou documentação.

---

# PARTE A — HOMOLOGAÇÃO DO BACKEND 01

## 2. Validar autenticação e cadastro

Utilizar os recursos disponíveis no ambiente para executar testes reais no navegador.

Validar:

- Abertura do cadastro público.
- Cadastro de empresa com segmento.
- Criação transacional do tenant.
- Login do proprietário.
- Redirecionamento para área autenticada.
- Logout.
- Bloqueio de usuário suspenso.
- Bloqueio de tenant suspenso.
- Ausência de dependência de e-mail.

Criar duas empresas de teste isoladas e verificar que seus usuários não conseguem acessar recursos da outra empresa.

Usar o banco de testes ou registros identificados como testes. Não apagar indiscriminadamente os dados existentes.

Caso o navegador não esteja disponível, informar a limitação e executar testes HTTP automatizados equivalentes.

## 3. RootAdmin

Verificar o comando:

`php artisan platform:root-admin`

Homologar o provisionamento do RootAdmin em ambiente seguro, sem criar credenciais fixas ou expor senhas.

Validar:

- Autenticação.
- Permissões globais.
- Listagem de tenants.
- Ativação e suspensão de empresas.
- Gerenciamento de segmentos.
- Bloqueio do acesso administrativo por usuários comuns.

Não provisionar RootAdmin em produção.

Se forem necessárias credenciais interativas, não as inventar e não registrá-las no relatório.

## 4. Pendências técnicas anteriores

Verificar os 12 erros TypeScript informados no Backend 01.

Corrigir os problemas quando houver solução segura e compatível com o código existente.

Verificar também:

- Usuário legado `test@example.com`.
- Status `cancelled` sem fluxo operacional.
- Auditoria de operações administrativas.
- Autenticação por sessão nas APIs.
- Comportamento dos jobs tenant-scoped.

Não adicionar Sanctum sem necessidade arquitetural comprovada.

Não implementar funcionalidades novas apenas para eliminar pendências que não afetam esta etapa.

---

# PARTE B — CLIENTES

## 5. Cadastro de clientes

Implementar a entidade `Customer`.

Campos sugeridos:

- id
- tenant_id
- name
- person_type (individual/company)
- document (nullable)
- email (nullable)
- phone (nullable)
- whatsapp (nullable)
- address (nullable)
- city (nullable)
- state (nullable)
- notes (nullable)
- status
- timestamps

Reavaliar a estrutura conforme o padrão existente.

### Regras

- Cliente sempre pertence a um tenant.
- Nome obrigatório.
- Telefone ou e-mail podem ser informados, conforme o caso.
- CPF/CNPJ opcionais.
- Não exigir cadastro completo para iniciar um atendimento.
- Permitir cadastro rápido.
- Evitar duplicação acidental de clientes.
- Nunca misturar clientes de empresas diferentes.

Preparar o modelo para vinculação futura com solicitações, conversas e agendamentos.

### API

Criar:

- Listar clientes com paginação.
- Consultar cliente.
- Cadastrar cliente.
- Atualizar cliente.
- Desativar cliente.
- Buscar por nome, telefone ou e-mail.

Não implementar CRM complexo.

---

# PARTE C — CATÁLOGO DE SERVIÇOS

## 6. Categorias de serviços

Criar `ServiceCategory`:

- id
- tenant_id
- name
- description (nullable)
- sort_order
- active
- timestamps

Cada empresa terá suas próprias categorias.

Exemplos:

Climatização:
- Instalação
- Manutenção
- Higienização

Eletricistas:
- Instalações
- Reparos
- Inspeções

Não fixar categorias específicas de um segmento diretamente no código.

## 7. Serviços

Criar `Service`:

- id
- tenant_id
- service_category_id (nullable)
- name
- description (nullable)
- duration_minutes (nullable)
- price (nullable)
- price_type
- requires_evaluation
- active
- sort_order
- timestamps

Tipos de preço:

- fixed
- starting_at
- on_request

### Regras essenciais

1. Serviço pertence ao tenant.
2. Categoria deve pertencer ao mesmo tenant.
3. Valor é opcional.
4. Duração é opcional.
5. Serviços podem exigir avaliação antes de informar preço ou agendar.
6. A IA futura só poderá informar preços configurados e autorizados.
7. Serviços inativos não poderão receber novos agendamentos.
8. Preservar histórico de serviços já utilizados.

Utilizar `DECIMAL` para valores monetários, sem `FLOAT`.

### API

Implementar CRUD, paginação, filtros por categoria e status e ordenação simples.

---

# PARTE D — PROFISSIONAIS

## 8. Cadastro de profissionais

Criar `Professional`:

- id
- tenant_id
- name
- email (nullable)
- phone (nullable)
- active
- notes (nullable)
- timestamps

O profissional não precisa obrigatoriamente possuir login.

Um tenant poderá cadastrar pessoas que apenas executam serviços, sem acesso ao sistema.

### Relacionamento com serviços

Criar relacionamento muitos-para-muitos:

`professional_services`

O relacionamento deverá permitir definir quais serviços cada profissional pode executar.

### Regras

- Profissionais sempre pertencem ao tenant.
- Serviços vinculados devem pertencer ao mesmo tenant.
- Profissionais inativos não poderão receber novos agendamentos.
- Permitir profissional sem vínculo com usuário autenticável.
- Não implementar comissões, folha de pagamento ou controle financeiro.

### API

Implementar:

- Listagem.
- Cadastro.
- Edição.
- Ativação/desativação.
- Vinculação de serviços.
- Consulta dos serviços executados pelo profissional.

---

# PARTE E — CONFIGURAÇÕES DE ATENDIMENTO

## 9. Preferências operacionais

Ampliar `tenant_settings` sem duplicar tabelas ou campos desnecessariamente.

Configurações iniciais:

- Nome público da empresa.
- Descrição da atividade.
- Telefone de atendimento.
- WhatsApp.
- Endereço.
- Cidade e estado.
- Fuso horário.
- Atendimento presencial, remoto ou no local do cliente.
- Mensagem de boas-vindas.
- Política de confirmação de agendamento.

Preparar configuração futura para:

- Recepcionista com IA.
- Instruções específicas do segmento.
- Base de conhecimento.
- Site público.
- Horários de atendimento.

Não implementar a IA nesta etapa.

---

# PARTE F — PADRONIZAÇÃO E SEGURANÇA

## 10. Isolamento multi-tenant

Aplicar obrigatoriamente:

- `tenant_id` nas entidades operacionais.
- `TenantContext` para resolução do tenant.
- Escopo `forTenant()` nas consultas.
- Policies por recurso.
- Validação de relacionamentos cruzados.
- Verificação de tenant em toda API.
- Auditoria de alterações relevantes.

Como o projeto usa escopo explícito em vez de Global Scope, revisar cuidadosamente todos os caminhos de consulta.

Não permitir que o frontend escolha livremente o `tenant_id`.

### Integridade do banco

Criar índices, constraints e chaves estrangeiras adequadas ao MySQL/InnoDB.

Sempre que necessário, utilizar índices compostos e validações adicionais para impedir associação entre registros de tenants diferentes.

## 11. Autorização

Respeitar os perfis existentes:

**Owner:** administração completa do tenant.

**Admin:** gestão operacional de clientes, serviços e profissionais.

**Operator:** consultas e operações permitidas conforme política definida.

Documentar a matriz de permissões implementada.

O RootAdmin não deve receber automaticamente acesso operacional irrestrito aos dados privados dos tenants.

---

# PARTE G — TESTES

## 12. Testes automatizados obrigatórios

Implementar testes para:

1. CRUD de clientes.
2. CRUD de categorias.
3. CRUD de serviços.
4. CRUD de profissionais.
5. Vínculo entre profissionais e serviços.
6. Isolamento de clientes entre tenants.
7. Isolamento de categorias entre tenants.
8. Isolamento de serviços entre tenants.
9. Isolamento de profissionais entre tenants.
10. Bloqueio de relacionamentos cruzados.
11. Regras de preço e duração.
12. Autorização por perfil.
13. Bloqueio de contas suspensas.
14. Validação de dados inválidos.
15. Auditoria de alterações.
16. Compatibilidade com MySQL.
17. Preservação dos testes do Backend 01.

Executar a suíte completa.

Executar também:

- Laravel Pint.
- PHPStan, se configurado.
- TypeScript.
- Build do frontend.

Corrigir regressões introduzidas nesta etapa.

---

# PARTE H — EXPERIÊNCIA OPERACIONAL

## 13. Regra de simplicidade

O VetorSmart será destinado principalmente a pequenos prestadores de serviços.

Portanto:

- Evitar formulários extensos.
- Permitir cadastro rápido.
- Não exigir dados opcionais.
- Evitar telas intermediárias desnecessárias.
- Não repetir informações já cadastradas.
- Usar convenções consistentes entre módulos.
- Não introduzir aprovações administrativas desnecessárias.

Não implementar frontend completo nesta etapa, mas garantir APIs prontas para interfaces simples e responsivas.

O cadastro inicial de um serviço deverá exigir apenas seu nome; demais informações poderão ser preenchidas conforme necessidade, respeitando as validações técnicas.

---

# PARTE I — DOCUMENTAÇÃO E ENTREGA

## 14. Relatório técnico

Criar:

`docs/VETORSMART-BACKEND-02.md`

Incluir:

1. Estado inicial do projeto.
2. Homologação do Backend 01.
3. Pendências anteriores resolvidas.
4. Tabelas e relacionamentos criados.
5. Models, services e controllers.
6. Endpoints implementados.
7. Matriz de permissões.
8. Estratégia de isolamento.
9. Testes executados.
10. Resultado de Pint, PHPStan, TypeScript e build.
11. Problemas encontrados.
12. Pendências.
13. Recomendações para Backend 03.

Não afirmar que um recurso foi testado se não houve execução real.

## 15. Restrições finais

Não implementar:

- Agenda.
- Inteligência artificial.
- Chatbot.
- WhatsApp automatizado.
- Estoque.
- Financeiro.
- Emissão fiscal.
- Ordens de serviço complexas.
- Cobrança SaaS.
- Integrações com outros produtos.

Não publicar em produção.

Não executar commit, push ou merge sem autorização explícita.

Não apagar dados existentes.

## 16. Critérios de aceite

Considerar o Backend 02 concluído quando:

- O Backend 01 estiver homologado ou suas limitações documentadas.
- Clientes possuírem CRUD funcional.
- Categorias e serviços estiverem operacionais.
- Profissionais puderem ser cadastrados.
- Profissionais puderem ser associados a serviços.
- Configurações operacionais estiverem disponíveis.
- Todos os novos recursos respeitarem o tenant.
- Permissões estiverem funcionando.
- Testes automatizados passarem.
- Relatório técnico estiver completo.

**Execute no projeto existente, valide o código e registre somente resultados reais.**

Ao finalizar, apresente o status como CONCLUÍDO, PARCIAL ou BLOQUEADO, com evidências dos testes e pendências.

---

## Próxima etapa prevista

**VETORSMART-BACKEND-03 — Agenda Inteligente e Solicitações de Serviço**

Após esta execução, implementaremos disponibilidade real, horários de trabalho, bloqueios, agendamentos, solicitações e prevenção de conflitos.

Essa será a base necessária para permitir que a recepcionista com IA agende serviços de verdade.
