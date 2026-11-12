# VETORSMART-SERVICES-01.4 — Isolamento de testes e homologação na VPS

**Projeto:** VetorSmart  
**Diretório:** `gateway/vetorsmart/`  
**Servidor:** VPS de produção  
**Banco:** MySQL 8.4  
**Prioridade:** Crítica

---

# VETORSMART-AUTH-UX-01 — Padronização da autenticação

**Projeto:** VetorSmart  
**Diretório:** `gateway/vetorsmart/`  
**Ambiente:** VPS de produção  
**Prioridade:** Alta

## 1. Objetivo

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

Não alterar as regras de autenticação administrativa ou MFA.

## 2. Imagem de fundo

Revisar todas as telas:

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

Aplicar a mesma identidade visual.

Requisitos:

- Reutilizar a imagem oficial de autenticação já existente no projeto, se disponível.
- Imagem responsiva, cobrindo toda a área de fundo.
- Evitar distorção e cortes inadequados.
- Garantir boa visualização em desktop e mobile.
- Utilizar uma superfície sólida ou semitransparente no formulário para separar os campos da imagem.
- Evitar gradientes excessivos sobre a imagem.
- Preservar o padrão visual e a paleta oficial do VetorSmart.

Não substituir a imagem por outra sem necessidade.

## 3. Correção da legibilidade

O texto digitado nos campos de login está praticamente invisível.

Corrigir:

- Contraste entre texto e fundo dos inputs.
- Cor dos placeholders.
- Cor dos labels.
- Estados de foco.
- Mensagens de validação.
- Campos preenchidos automaticamente pelo navegador.
- Cor dos ícones de mostrar/ocultar senha.

Utilizar contraste acessível, preferencialmente WCAG AA.

O texto digitado deve permanecer visível em todos os estados, inclusive com autofill do Chrome e Firefox.

Não utilizar texto branco em input branco nem cores de baixo contraste.

## 4. Política de senhas

Simplificar a criação de senhas dos usuários comuns.

Requisitos:

- Mínimo de 8 caracteres.
- Permitir senhas contendo somente números.
- Permitir letras, números e caracteres especiais.
- Não exigir obrigatoriamente letras maiúsculas, símbolos ou combinações complexas.
- Não estabelecer máximo de 8 caracteres.
- Aplicar as mesmas regras no cadastro, redefinição e alteração de senha.
- Manter armazenamento seguro por hash e proteção contra tentativas excessivas de login.

**Exceção:** preservar as políticas mais rigorosas e o MFA obrigatório dos administradores globais.

Revisar validações de frontend e backend para evitar divergências.

Não modificar senhas existentes nem invalidar sessões sem necessidade.

## 5. Favicon

Verificar o favicon atual e corrigir sua apresentação.

Requisitos:

- Utilizar a identidade visual oficial do VetorSmart.
- Preferir o símbolo da marca, sem textos pequenos ilegíveis.
- Conferir arquivos SVG, PNG e ICO disponíveis.
- Corrigir referências no layout principal e nas páginas de autenticação.
- Garantir funcionamento nas rotas públicas e privadas.
- Corrigir cache e versionamento de assets quando necessário.
- Verificar visualização em navegadores desktop e mobile.

Não utilizar o favicon padrão do Laravel, Vite ou de outro produto.

## 6. Responsividade

Homologar em:

- Desktop de 1440 px.
- Mobile de 390 px.

As telas devem manter:

- Imagem de fundo adequada.
- Formulário centralizado.
- Campos legíveis.
- Botões acessíveis.
- Login sem rolagem desnecessária.
- Cadastro organizado, evitando rolagem sempre que possível sem cortar conteúdo.

## 7. Validação

Executar testes relacionados à autenticação, cadastro, redefinição de senha e MFA.

Verificar que as novas regras não prejudicam a segurança.

Testar campos vazios, senhas numéricas de 8 caracteres, senhas maiores, mensagens de erro e autenticação válida.

Executar as verificações técnicas em ambiente isolado, sem utilizar o banco operacional para testes automatizados.

## 8. Implantação

Preparar alterações e verificar o impacto antes da publicação.

Não alterar diretamente containers operacionais sem procedimento de implantação controlado.

Não modificar cobrança, regras de negócio, dados dos tenants ou permissões administrativas.

## 9. Entrega

Criar:

`docs/VETORSMART-AUTH-UX-01.md`

Atualizar `executed.md` com:

- Arquivos modificados.
- Correções visuais realizadas.
- Política de senhas implementada.
- Favicon utilizado.
- Testes executados.
- Evidências desktop e mobile.
- Status de implantação na VPS.
- Pendências existentes.

**Critério de aceite:** todas as telas de autenticação devem apresentar identidade visual consistente, campos claramente legíveis, favicon correto e política de senhas simplificada, sem regressões de segurança.

