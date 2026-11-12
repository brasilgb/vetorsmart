# VETORSMART-AUTH-UX-01: Padronização da autenticação

Data: 12/11/2026. Diretório: `gateway/vetorsmart/`.

## Diagnóstico

- **Texto invisível nos campos:** o `Input` usa `bg-transparent` e herda a cor do texto do `body`. Com o tema escuro ativo (preferência do sistema), a cor do texto ficava `#E2E8F0`, quase branca, sobre o cartão branco do login. Resultado: texto claro em fundo claro.
- **Identidade inconsistente:** login, cadastro e 2FA usavam o `AuthShell` (fundo da marca e cartão branco). Recuperação, redefinição e confirmação de senha usavam o layout simples do starter kit, sem a imagem e com textos em inglês. A página de 2FA era envolvida duas vezes, pelo `AuthShell` e pelo `AuthLayout`.
- **Política de senhas:** em produção valia `min(12)` com maiúsculas, minúsculas, números, símbolos e `uncompromised` para todos os usuários.
- **Favicon:** `public/favicon.svg` e `public/apple-touch-icon.png` eram o logotipo do Laravel. Como o SVG tem prioridade nos navegadores, o ícone do Laravel era o exibido.

## Correções

### Imagem de fundo e layout
- A imagem oficial existente (`public/images/auth-background.svg`) foi mantida.
- `AuthLayout` passou a usar o `AuthShell`. As 6 telas (login, cadastro, recuperação, redefinição, confirmação e 2FA) agora têm o mesmo fundo, o mesmo cartão branco sólido, o logotipo e o título.
- A película escura sobre a imagem foi reduzida de 0,72 para 0,55, com cobertura `cover`/`center`.
- O padding do cartão é de 24 px no mobile e 32 px a partir de `sm`.
- A dupla moldura da página de 2FA foi removida (`app.tsx`).
- Os textos de recuperação e redefinição foram traduzidos para pt-BR.

### Legibilidade
- A classe de escopo `.vs-auth` (`resources/css/app.css`) fixa os tokens claros dentro das telas de autenticação, mesmo em tema escuro. O variant `dark:` não se aplica dentro dela.
- Contrastes sobre branco:
  - texto `#0B1220`: 18:1
  - labels e dicas `#475569`: 7,6:1
  - placeholder `#64748B`: 4,8:1
  - erros `text-red-600`: 4,8:1 (WCAG AA)
- Estado de foco com borda azul `#2563EB` e anel de 3 px.
- Autofill no Chrome e Safari (`-webkit-autofill`) e no Firefox (`:autofill`): texto escuro sobre fundo `#EFF6FF`, sem perder o anel de foco.
- O ícone mostrar/ocultar senha usa `muted-foreground` (`#475569`). Rótulos acessíveis em pt-BR e `aria-pressed`.

### Política de senhas
- Usuários comuns (`Password::defaults`): mínimo de 8 caracteres, de qualquer tipo. Senhas só com números são aceitas. Não há máximo de 8 caracteres.
- Administradores globais (`is_root_admin`): política rigorosa preservada em `App\Services\PasswordPolicy`. Exige 12 caracteres, letras maiúsculas e minúsculas, números e símbolos, e `uncompromised` em produção. O MFA obrigatório não foi alterado.
- Mesma regra no cadastro, na redefinição (`ResetUserPassword`, pelo usuário-alvo) e na alteração em Configurações (`PasswordUpdateRequest`). A tela de Segurança envia o `passwordrules` do perfil do usuário.
- O frontend exibe a dica "Mínimo de 8 caracteres. Pode conter apenas números." no cadastro e na redefinição.
- Mantidos: hash bcrypt, rate limit de login e 2FA. Nenhuma senha existente ou sessão foi alterada.

### Favicon
- Símbolo "V" oficial, gerado a partir de `public/images/vetor-smart-logo-transparent.png` (sem texto).
- Arquivos:
  - `public/favicon.ico` (16/32/48)
  - `public/images/vetor-smart-favicon-32.png`
  - `public/images/vetor-smart-favicon-192.png`
  - `public/apple-touch-icon.png` (180 px, fundo branco)
- `public/favicon.svg`, que era o logotipo do Laravel, foi removido.
- `resources/views/app.blade.php` foi atualizado com `?v=2` para invalidar o cache. Vale para todas as rotas Inertia, públicas e privadas. As páginas de erro usam o `/favicon.ico` padrão, que agora é o da marca.

## Arquivos modificados

- `app/Services/PasswordPolicy.php` (novo)
- `app/Concerns/PasswordValidationRules.php`
- `app/Providers/AppServiceProvider.php`
- `app/Actions/Fortify/ResetUserPassword.php`
- `app/Http/Requests/Settings/PasswordUpdateRequest.php`
- `app/Http/Controllers/Settings/SecurityController.php`
- `resources/css/app.css`
- `resources/js/app.tsx`
- `resources/js/layouts/auth-layout.tsx`
- `resources/js/components/vetor-smart/auth-shell.tsx`
- `resources/js/components/password-input.tsx`
- `resources/js/pages/auth/{confirm-password,forgot-password,register,reset-password,two-factor-challenge}.tsx`
- `resources/views/app.blade.php`
- `public/favicon.ico`, `public/apple-touch-icon.png`, `public/images/vetor-smart-favicon-{32,192}.png` (novos ou substituídos)
- `public/favicon.svg` (removido)
- `tests/Feature/Auth/PasswordPolicyTest.php` (novo)

## Testes

Banco isolado `vetorsmart_test`, conforme o `phpunit.xml`. O banco operacional não foi usado.

| Verificação | Resultado |
|---|---|
| `php artisan test --compact tests/Feature/Auth/PasswordPolicyTest.php` | 9 aprovados |
| `php artisan test --compact` | 346 testes: 341 aprovados, 0 falhas, 5 pulados (concorrência MySQL, pulados por padrão) |
| `vendor/bin/pint --dirty` | aprovado |
| `npx tsc --noEmit` | 0 erros |
| `npm run build` | aprovado |

Cobertura nova:
- Cadastro aceita senhas de 8 dígitos, só letras minúsculas e frases longas com símbolos.
- Cadastro recusa 7 caracteres, com a mensagem "O campo senha deve ter pelo menos 8 caracteres."
- Usuário comum redefine e altera a senha para 8 dígitos.
- RootAdmin tem senha fraca recusada na redefinição e na alteração, e senha forte aceita.
- Tela de cadastro envia `passwordRules` `minlength: 8;`.

Os testes já existentes de autenticação, cadastro, redefinição e MFA continuam aprovados.

## Pendências

1. **Evidências visuais desktop (1440 px) e mobile (390 px):** não coletadas. O servidor local não foi iniciado nesta sessão. Validar no navegador as 6 telas, o autofill no Chrome e no Firefox e o favicon.
2. **Implantação na VPS:** não realizada. Requer o procedimento controlado de deploy (`npm run build` já incluído nos assets), sem alteração direta em containers.
3. **Dica de senha na redefinição:** a dica mostra a regra simples. Um RootAdmin que redefine a senha recebe do backend a mensagem da política rigorosa.
4. **Comando `platform:root-admin`:** mantém a validação própria (mínimo de 12 caracteres), sem alteração.
5. **Sites públicos dos tenants (`resources/views/website`):** não declaram favicon próprio e usam o `/favicon.ico` padrão. Não foram alterados.
