# VETORSMART-SERVICES-01.4 — Isolamento de testes e homologação na VPS

**Projeto:** VetorSmart  
**Diretório:** `gateway/vetorsmart/`  
**Servidor:** VPS de produção  
**Banco:** MySQL 8.4  
**Prioridade:** Crítica

## Objetivo

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

Executar:

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
