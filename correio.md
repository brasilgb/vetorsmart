# VETORSMART-AUTH-UX-01 — Padronização da autenticação

**Projeto:** VetorSmart  
**Diretório:** `gateway/vetorsmart/`  
**Ambiente:** VPS de produção  
**Prioridade:** Alta

## 1. Objetivo

Corrigir o contraste e a legibilidade das telas de autenticação, padronizar a imagem de fundo, simplificar os requisitos de senha e corrigir o favicon do VetorSmart.

Não alterar as regras de autenticação administrativa ou MFA.

## 2. Imagem de fundo

Revisar todas as telas:

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

