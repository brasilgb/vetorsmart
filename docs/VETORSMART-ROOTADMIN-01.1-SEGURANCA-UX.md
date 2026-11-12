# VETORSMART-ROOTADMIN-01.1 — Segurança, MFA e homologação administrativa

Data: 11/11/2026 (execução concluída em 12/11/2026). Local: `gateway/vetorsmart/`.

**Resultado: CONCLUÍDO COM BLOQUEIOS.** O MFA obrigatório está implementado e coberto por testes automatizados. A homologação de ponta a ponta no navegador não foi concluída: o ambiente não tem uma sessão de navegador autenticada. Ver §7.

## 1. Diagnóstico das falhas

| Falha | Causa verificada | Situação |
|---|---|---|
| RootAdmin acessível só com senha | `platform-admin` checava apenas `is_root_admin` e status ativo | Corrigido: gate exige também TOTP confirmado; middleware bloqueia antes |
| Fortify 2FA não habilitado | `config/fortify.php` sem `twoFactorAuthentication`; colunas `two_factor_*` inexistentes no banco (o model as citava só em PHPDoc) | Corrigido: feature habilitada com confirmação e confirmação de senha; migration criada |
| Desafio de 2FA redirecionava para `/dashboard` | Fortify usa `TwoFactorLoginResponse`, não o `LoginResponse` por perfil | Corrigido: contrato vinculado ao mesmo `LoginResponse` |
| `PlatformStartTest` falhava (2 testes) | Os textos "Ir para o painel" e "Em desenvolvimento" são renderizados pelo React no navegador. O HTML inicial do Inertia contém só o JSON das props, então `assertSee` nunca encontraria esses textos | Corrigido no teste: asserts sobre props Inertia (`auth.user`) e varredura das fontes da landing. **A landing não foi alterada.** |
| `SecurityTest` falhava após a habilitação do 2FA | Teste espera a prop `canManageTwoFactor` e ausência de props de 2FA quando o recurso está desligado | Corrigido no controller, sem alterar o teste |

Falha de teste da suíte: nenhuma restante. O estado anterior tinha 2 falhas (landing); elas foram corrigidas pela razão acima, não por remoção de teste.

## 2. Implementação do MFA

Recurso: Fortify 1.41, TOTP com confirmação e códigos de recuperação.

Requisitos do brief:

1. **Fortify 2FA habilitado**: `config/fortify.php`, feature com `confirm` e `confirmPassword`. Limiter `two-factor` configurado.
2. **Aplicativo autenticador**: rotas do Fortify (`two-factor.enable`, `two-factor.qr-code`, `two-factor.secret-key`).
3. **QR Code e chave**: busca sob demanda pela tela de segurança. O QR é exibido como `data:` URI de SVG, sem `innerHTML`. A chave não fica nas props da página.
4. **Confirmação antes de ativar**: `two-factor.confirm` com código válido. Sem confirmação, o login não exige segundo fator (comportamento do Fortify).
5. **Códigos de recuperação**: gerados na confirmação, exibidos sob demanda, regeneráveis.
6. **Armazenamento protegido**: `two_factor_secret` e `two_factor_recovery_codes` são cifrados pelo Fortify e ocultos no model (`#[Hidden]`). Teste confirma que não aparecem em `toArray()`.
7. **Segundo fator após senha**: `RedirectIfTwoFactorAuthenticatable` redireciona para `two-factor.login` quando há segredo confirmado.
8. **Acesso bloqueado com MFA pendente**: `EnsureRootAdminHasTwoFactor` (alias `rootadmin.mfa`) é o primeiro item do grupo `rootadmin`. Administrador sem TOTP confirmado é redirecionado para `settings/security`.
9. **Revogação e regeneração**: `two-factor.disable` e `two-factor.regenerate-recovery-codes`, ambos com confirmação de senha recente.
10. **Auditoria sem segredos**: listeners de `TwoFactorAuthenticationEnabled`, `…Confirmed`, `…Disabled`, `RecoveryCodesGenerated` e `…Failed` (apenas administradores) gravam `mfa.enabled`, `mfa.confirmed`, `mfa.disabled`, `mfa.recovery_codes_regenerated`, `mfa.challenge_failed`, sem metadados.

Primeiro acesso: administradores existentes sem MFA entram normalmente, mas são enviados para a configuração do fator antes de qualquer tela administrativa. Enquanto isso, só a tela de segurança (com confirmação de senha) e o logout ficam disponíveis.

Recuperação sem códigos: não há mecanismo público de redefinição de MFA. O procedimento para perda de todos os fatores (verificação de identidade, autorização e auditoria) **não foi documentado em procedimento operacional** nesta etapa. Pendência.

## 3. Middleware e autorização

- `rootadmin` group: `auth` → `rootadmin.mfa` → `can:platform-admin`.
- Gate `platform-admin`: `isRootAdmin() && hasConfirmedTwoFactor()`. A verificação é repetida no gate, então nenhuma camada decide sozinha.
- Usuários de tenant (owner, admin, operator) recebem 403 em `/rootadmin`, mesmo com 2FA confirmado. Testado.
- Rate limiting: login já tinha limiter (5/min por e-mail+IP). Desafio de 2FA: 5/min por `login.id` pendente. Testado (sexta tentativa retorna 429).
- Sessão: `LoginResponse` decide o destino por perfil também no desafio. Logout invalida a sessão e a rota administrativa volta a redirecionar para login. Testado.
- Reuso de código TOTP: o Fortify guarda o código usado em cache pelo intervalo da janela. Testado.
- Código expirado: `oathTotp` com contador de 10 janelas atrás é recusado. Testado.
- Recovery code: consumido ao uso. Reuso recusado. Testado.

## 4. Testes executados

| Comando | Resultado |
|---|---|
| `php artisan test --compact tests/Feature/RootAdmin/RootAdminMfaTest.php` | 12 aprovados |
| `php artisan test --compact tests/Feature/Settings` + MFA | 22 aprovados |
| `php artisan test --compact tests/Feature/Platform tests/Feature/RootAdmin tests/Feature/Auth tests/Feature/Tenancy tests/Feature/Operations` | 222 aprovados, 5 pulados |
| `php artisan test --compact` (suíte completa) | **326 testes: 321 aprovados, 0 falhas, 5 pulados** |
| `vendor/bin/pint --dirty` | aprovado |
| `npx tsc --noEmit` | 0 erros |
| `npm run build` | aprovado |

Os testes rodam com o 2FA **efetivamente habilitado**: o model de usuário administrador de teste usa o estado `withConfirmedTwoFactor()` (ver `database/factories/UserFactory.php`). Os 5 testes pulados são os já existentes, condicionados à feature.

Cobertura do brief (§8): itens 1 a 13 cobertos pelo `RootAdminMfaTest.php` e por testes existentes de isolamento de tenant (`TenantIsolationTest`, `OperationalIsolationTest`).

## 5. Evidências de navegador

Ferramenta do navegador (chrome-devtools) indisponível: o Chrome não está instalado no ambiente. Foi usado o `chrome-headless-shell` do cache do Playwright, em modo de captura de tela, sem sessão.

- Login, 1440 px: renderiza corretamente (`login-desktop.png`).
- Login, 390 px: renderiza corretamente com `--virtual-time-budget` (`login-mobile-2.png`). Sem overflow horizontal visível. A primeira captura sem tempo de espera saiu em branco; é artefato de captura, não defeito.

**Não realizado:** login com senha, desafio de 2FA, configuração de MFA, dashboard, lista e detalhe de empresas, histórico, navegação, logout e estados de erro. Esses fluxos exigem sessão autenticada e não foram homologados visualmente. Por isso não há erros de console verificados.

Conta de homologação criada no banco local: `homolog.root@example.test`, administrador global **sem** MFA confirmado. A senha foi gerada e está em `scratchpad/homolog-root-password.txt` (permissão 600). Ela deve ser trocada ou a conta removida antes de qualquer uso fora de homologação. A remoção de dados exige autorização explícita.

## 6. Avaliação de UX

Mudanças desta etapa com efeito de UX:

- Tela de segurança mostra estado do MFA (Ativa, Aguardando confirmação, Inativa) e explica quando é obrigatória.
- Mensagem de redirecionamento para o administrador sem MFA em português, com role `status`.
- Desafio de 2FA com alternância clara entre código do aplicativo e código de recuperação, com `inputMode="numeric"` e `autoComplete="one-time-code"`.
- Ações destrutivas (desativar MFA) têm texto explicando a consequência para administradores.

Limitações observadas no código, não validadas no navegador:

- Os textos da tela de segurança existente (senha) continuam em inglês ("Update password", "Current password"). Não foram alterados para não ampliar o escopo.
- Não há confirmação em modal para desativar MFA; a ação é direta, com aviso no texto.
- Contraste, foco por teclado e layout dos novos blocos não foram verificados visualmente.

## 7. Pendências

1. **Homologação autenticada no navegador** (login, desafio, configuração de MFA, dashboard, empresas, histórico, logout, acesso negado) em 1440 px e 390 px. Bloqueada pela ausência de Chrome utilizável pela ferramenta. Requer sessão de navegador real ou ambiente com Chrome.
2. **Procedimento operacional** para perda de todos os fatores (verificação de identidade, autorização, auditoria). Não documentado.
3. **Conta de homologação** com senha conhecida no `scratchpad` e sem MFA: trocar ou remover com autorização.
4. Modelo de assinaturas, cobrança e créditos de IA: continuam fora do escopo, conforme o brief.
5. Pendências herdadas: `homolog:purge` não alterado; horários históricos não alterados; licenciamento das imagens; MySQL 8; LGPD; integração real com provedor de IA.

## 8. Riscos

- **Sem homologação visual autenticada**, há risco de defeito de UX ou de console nas telas administrativas que não aparece nos testes automatizados.
- **Recuperação por códigos é a única saída** para perda de fator. Se o administrador perder o aplicativo e os códigos, o caminho é o procedimento operacional ainda não escrito (pendência 2).
- **2FA opcional para tenants**: a feature é global no Fortify. Usuários de tenant podem ativá-lo, mas não são obrigados. Isso é intencional, mas deve constar da política de produto.
- **Migration nova** (`add_two_factor_columns_to_users_table`) já aplicada no banco local. Em outros ambientes, deve ser aplicada antes do deploy.

## 9. Recomendação de aceite

**Não aceitar ainda.** Os itens 1 a 10 do §3 do brief estão implementados e testados. Os critérios de aceite do §11 exigem homologação no navegador dos fluxos críticos e desktop e mobile funcionando. Esses critérios não estão comprovados.

Recomenda-se:

1. Homologar no navegador com Chrome real, seguindo a lista do §5, em 1440 px e 390 px.
2. Escrever o procedimento de perda de fatores (pendência 2).
3. Trocar ou remover a conta de homologação (pendência 3).

Depois disso, a etapa pode ser reavaliada como aprovada.

**Não executar commit, push, merge ou deploy** — nenhum foi feito.
