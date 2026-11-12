# VETORSMART-ROOTADMIN-01.2 — Homologação autenticada e encerramento de segurança

Data: 12/11/2026. Local: `gateway/vetorsmart/`. Relatórios anteriores: `docs/VETORSMART-ROOTADMIN-01.md`, `docs/VETORSMART-ROOTADMIN-01.1-SEGURANCA-UX.md`.

**Decisão: aceitação recomendada, com uma ressalva registrada em §8.** Os critérios do §10 do brief foram atendidos, exceto a verificação sistemática de acessibilidade (teclado, foco e contraste), que não foi medida. Ver §9.

## 1. Ferramenta de navegador

- **Executável:** `chrome-headless-shell` 153.0.8010.12, já presente no cache do Playwright (`~/.cache/ms-playwright/chromium_headless_shell-1243`). Testado antes de qualquer uso: abriu, respondeu na porta de depuração e renderizou o login.
- **Controle:** protocolo Chrome DevTools (CDP) direto, por um driver mínimo em Node (WebSocket nativo do Node 24), mantido fora do projeto. **Nenhuma dependência foi adicionada** ao projeto nem instalado o pacote `playwright`.
- **Chrome DevTools MCP:** não utilizado. O Chrome não está instalado no ambiente, e o próprio MCP falhou ao localizá-lo.
- **Impedimentos técnicos comprovados:** nenhum para o fluxo homologado. A ferramenta padrão de ferramentas do navegador é que não funciona aqui.

## 2. Fluxos efetivamente executados

Ambiente: `php artisan serve` em `localhost:8001`, banco local, contas de homologação dedicadas.

### Administrador global (RootAdmin)

| Fluxo | 1440 px | 390 px |
|---|---|---|
| Login com senha leva à confirmação de senha | sim | sim (passo anterior) |
| Configuração obrigatória de MFA (mensagem de status, QR Code, chave, confirmação por TOTP) | sim | não repetido (feito em 1440) |
| Código TOTP inválido recusado | sim | — |
| Ativação após código válido | sim | — |
| Geração e exibição de códigos de recuperação (8 códigos) | sim | — |
| Regeneração dos códigos e uso do primeiro código novo | sim | — |
| Logout encerra a sessão; `/rootadmin` volta a exigir login | sim | sim |
| Login com senha leva ao desafio TOTP | sim | sim |
| Acesso direto a `/rootadmin` antes do segundo fator é bloqueado | sim | — |
| Reutilização do mesmo código TOTP é recusada | sim | — |
| Código de recuperação aceito; mesmo código recusado depois; segundo código aceito | sim | — |
| Entrada com TOTP válido chega ao console | sim | sim |
| Visão Geral, Empresas, Segmentos, Usuários e Acessos, Segurança e Auditoria | sim | sim |
| Detalhe da empresa: bloco Operação, Histórico administrativo, botão de suspensão desabilitado sem confirmação | sim | sim |
| Configurações de segurança (estado do MFA, códigos, alteração de senha) | sim | sim |
| 404 para empresa inexistente e página de rota desconhecida | sim | sim |

Em cada página percorrida, a execução registrou **overflow horizontal** (falso em todas), **respostas HTTP de erro** (nenhuma) e **erros de console** (nenhum).

### Usuário de empresa (sem privilégio global)

| Fluxo | 1440 px | 390 px |
|---|---|---|
| Login por senha (sem MFA, como usuário de empresa) | sim | sim |
| Acesso a `/rootadmin` recusado com página de acesso negado em português | sim | sim |
| Logout | sim | sim |

### Verificações adicionais

- Reutilização de código TOTP e de código de recuperação: recusada nos dois casos.
- Código expirado (janelas fora da tolerância): coberto por teste automatizado (`RootAdminMfaTest`), não repetido no navegador.
- Limitação de tentativas do desafio (429 após 5 tentativas): coberta por teste automatizado.

## 3. Evidências visuais

Capturas em `scratchpad/shots/` (fora do repositório). Nenhuma mostra QR Code, chave, código TOTP, códigos de recuperação ou senha:

- Login, desafio e configuração: `f1-challenge-desktop.png`, `f1-challenge-mobile.png`, `a3-mfa-required-desktop.png`, `a6-mfa-active-desktop.png`, `c2-challenge-invalid-desktop.png`, `c5-reuse-refused-desktop.png`, `d5-recovery-reuse-refused-desktop.png`.
- Visão Geral e empresas: `f2-dashboard-desktop.png`, `f2-dashboard-mobile.png`, `f3-companies-*.png`, `f4-company-detail-desktop.png`, `f4-company-detail-mobile.png`.
- Segurança e confirmação de senha: `f8-security-desktop.png`, `f8-security-mobile.png`, `f8-confirm-password-*.png`.
- Acesso negado e 404: `f10-access-denied-*.png`, `f11-not-found-*.png`.

Observação: as capturas `f4-company-detail-*` mostram o e-mail do usuário de homologação da empresa de teste. São dados de teste locais, não de cliente.

## 4. Problemas identificados e correções

| # | Problema verificado | Correção | Verificação |
|---|---|---|---|
| 1 | Após confirmar a senha, o administrador era enviado à **página pública inicial** (com "Ir para o painel"), porque a confirmação usava uma URL "pretendida" antiga, e o `LoginResponse` a ignorava. | `App\Http\Responses\PasswordConfirmedResponse` vinculado ao contrato do Fortify. Administrador vai ao console, ou à página de configurações apenas se ela for a URL pretendida. Outras URLs são descartadas. | Teste novo (`RootAdminMfaTest`): URL pretendida `/` leva ao console; URL de configurações volta à configuração. Browser: F8 ok. |
| 2 | Textos da área de segurança em inglês: "Security settings", "Update password", "Current password", "Save", "Confirm password", "Log out" etc. | Traduzidos para português brasileiro em `settings/security.tsx`, `auth/confirm-password.tsx`, `layouts/settings/layout.tsx` e `user-menu-content.tsx`. Campos obrigatórios marcados com `*` e texto explicativo. | Browser: F8, F9 e capturas. |
| 3 | Mensagens do Fortify em inglês ("The provided two factor authentication code was invalid."). | `lang/pt_BR.json` e `app()->setLocale('pt_BR')` em `AppServiceProvider::configureDefaults`. Chaves ausentes continuam em inglês por `fallback_locale`. | Browser: C2, C5, C7, D5 mostram texto em português. |
| 4 | Página 404 e 403 padrão do Laravel, em inglês. | `resources/views/errors/403.blade.php` e `404.blade.php` em português. | Browser: F10, F11, C17. |
| 5 | Itens "Em breve" do menu com o rótulo cortado ("EM BR…") no desktop. | Rótulo empilhado sob o título, sem corte. | Captura `f8-security-desktop.png`. |
| 6 | Na homologação de desafio, o toggle de código de recuperação mudava de texto após uma tentativa recusada. | Corrigido apenas no roteiro de teste (não é defeito do produto). | — |

Correções herdadas de etapas anteriores e confirmadas nesta: testes `PlatformStartTest` (landing) ajustados às props Inertia; `SecurityTest` atendido pelo controller; migration de 2FA aplicada.

## 5. Resultados dos testes

| Comando | Resultado |
|---|---|
| `php artisan test --compact` (suíte completa) | **328 testes: 323 aprovados, 0 falhas, 5 pulados** (testes existentes condicionados à feature) |
| `php artisan test --compact tests/Feature/RootAdmin/RootAdminMfaTest.php` | 14 aprovados |
| `vendor/bin/pint --dirty` | aprovado |
| `npx tsc --noEmit` | 0 erros |
| `npm run build` | aprovado |

Nenhum teste foi removido ou enfraquecido. As falhas da suíte anterior foram corrigidas pela causa (ver `executed.md`).

## 6. Situação das contas temporárias

Executado ao final dos testes:

- **`homolog.root@example.test`** (administrador global): status **suspenso**, senha substituída por valor aleatório não registrado, segundo fator **removido** (segredo, códigos de recuperação e data de confirmação limpos), token de sessão renovado. A conta **não foi excluída**: pode ser recriada com `php artisan platform:root-admin` e nova senha, e o MFA precisará ser configurado de novo.
- **`homolog.tenant@example.test`** (usuário de empresa): status **suspenso**, senha substituída por valor aleatório não registrado.
- **Arquivos de credenciais** no `scratchpad` (senha do administrador, senha do usuário de empresa, segredo TOTP, códigos de recuperação): **excluídos**. Eles estavam fora do repositório (`/tmp/...`), com permissão 600, e não aparecem no `git status`.
- **Dados de teste criados** no banco local e **mantidos**, sem exclusão: a empresa "Empresa Homologação Negado" e o vínculo do usuário de empresa. Remover depende de autorização. `homolog:purge` não foi executado.

## 7. Procedimento de recuperação de acesso

Quando um administrador global perder todos os fatores:

1. **Verificação de identidade** por procedimento previamente estabelecido com o titular, fora da plataforma (e-mail corporativo já verificado e contato telefônico).
2. **Autorização** de outro administrador global habilitado, quando existir mais de um. A autorização é registrada.
3. **Recuperação por canal administrativo protegido**: acesso ao servidor pelo operador responsável, sem endpoint público.
4. **Revogação dos fatores antigos** e **novo enrollment obrigatório** no próximo acesso.
5. **Registro auditável** da operação e **notificação de segurança** ao titular.

Restrições: não existe endpoint público para remover MFA; não há credencial fixa nem bypass permanente.

**Instalação com um único administrador global.** Não há, nesta versão, comando de operador que revogue o MFA de um administrador. Por isso, a recuperação excepcional depende de uma ação administrativa no servidor que ainda **não foi implementada**. O procedimento correto é implementar um comando protegido (`platform:mfa-reset`, somente via shell do servidor), que revogue os fatores, invalide sessões, exija novo enrollment e grave auditoria sem segredos. Até lá, a conta única fica sem recuperação segura. Ver §8.

## 8. Pendências restantes

1. **Comando de recuperação de MFA** (`platform:mfa-reset`), somente via shell, com auditoria. Necessário antes de produção com um único administrador.
2. **Verificação sistemática de acessibilidade** (teclado, foco visível, contraste, leitura de rótulos de erro). O brief pede. Não foi medida. Os rótulos, textos e responsividade foram verificados; teclado e contraste não.
3. **Páginas de perfil e aparência** ainda em inglês (`settings/profile.tsx`, `settings/appearance.tsx`). Fora do fluxo de segurança, mas listadas no menu de configurações.
4. **Mensagens de validação do Laravel** (campos obrigatórios, e-mail etc.) continuam em inglês: só há `lang/pt_BR.json` com as mensagens desta área.
5. **Página de erro 500** continua padrão do Laravel.
6. **Dados de teste** no banco local (empresa e usuário de homologação), a remover com autorização.
7. Pendências herdadas: modelo de assinaturas e cobrança, créditos de IA, tela de sites e domínios no RootAdmin, `homolog:purge` não alterado, horários históricos não alterados, licenciamento das imagens, MySQL 8, LGPD, integração real com provedor de IA.

## 9. Avaliação dos critérios de aceite (§10 do brief)

| Critério | Situação |
|---|---|
| O MFA funciona no navegador | **Atendido.** Configuração, confirmação, desafio, recuperação, reutilização e logout verificados. |
| Acesso global sem segundo fator é bloqueado | **Atendido.** Verificado no navegador (acesso direto antes do desafio) e em teste automatizado. |
| Fluxos administrativos funcionam em desktop e mobile | **Atendido**, nos fluxos percorridos. Sem overflow, sem erros de console nem HTTP de erro. |
| Sem falhas críticas de autenticação ou autorização | **Atendido.** Corrigido o redirecionamento pós-confirmação (problema 1). Sem falha aberta conhecida. |
| Recuperação de acesso documentada | **Parcialmente.** Procedimento documentado (§7), mas a ação de servidor para conta única **não existe** (pendência 1). |
| Credenciais temporárias protegidas e invalidadas | **Atendido** (§6). |
| Suíte completa sem falhas | **Atendido**: 0 falhas. |

**Decisão fundamentada:** o RootAdmin está **homologado no navegador** para os fluxos de autenticação, MFA e administração listados em §2. Recomenda-se o **aceite**, com as seguintes condições: (a) a pendência 1 (recuperação de conta única) é obrigatória antes de qualquer implantação com um único administrador; (b) a verificação de acessibilidade (pendência 2) entra como próxima etapa. A aceitação integral do brief não é afirmada, porque o §7 pede validação de teclado e contraste que não foi realizada.

**Não executar commit, push, merge ou deploy:** nenhum foi feito. Nenhuma cobrança, consumo de IA, módulo de SEO, sites, widget ou aplicativo foi iniciado.
