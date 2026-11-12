# Execução — VETORSMART-SERVICES-01.4 (Isolamento de testes e homologação na VPS)

Data: 12/11/2026. Diretório: `gateway/vetorsmart/`. Servidor: VPS de produção. Banco: MySQL 8.4.11.

## Instrução

- `correio.md` difere da versão executada anteriormente (SERVICES-01.3). Esta execução seguiu a versão atual (SERVICES-01.4).

## Resultado

**TÉCNICO (testes, Pint, tsc, build): APROVADO.** 332 de 337 testes aprovados e 5 pulados (concorrência, rodada à parte: 5 de 5). Única falha anterior corrigida no teste de auditoria (ordem de chaves JSON no MySQL 8.4).

**HOMOLOGAÇÃO NO NAVEGADOR (desktop, 390 px, multi-tenant, atendimento público): NÃO EXECUTADA.** Credenciais das contas de homologação indisponíveis; a busca foi negada pelo classificador de permissões e não foi contornada.

**LIBERAÇÃO: NÃO APROVADA.**

Atualização: banco `vetorsmart_test` removido pelo responsável; deploy realizado (ver abaixo). Detalhes em `docs/VETORSMART-SERVICES-01.4.md`.

## Ambiente de testes

| Item | Resultado |
|---|---|
| Container de teste | `vetorsmart-test-mysql` (MySQL 8.4.11), rede `vetorsmart-test-net`, sem portas publicadas. Parado ao final. |
| Banco e usuário | `vetorsmart_test`; usuário `vs_test` com privilégio só nesse banco. `root` não usado. |
| Código testado | Cópia isolada em scratchpad, com `.env` de teste. Nada gravado em `public/build` do repositório. |
| Configuração de teste | `APP_URL=http://localhost`, padrões de `config/platform.php` (esperados pelos testes). |
| Senhas | Fora do repositório e fora dos relatórios. |

## Verificações (resultados reais)

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | 337 testes: 332 aprovados, 5 pulados, 0 falhas |
| Correção | `tests/Feature/Tenancy/AuditLogTest.php`: `toBe` → `toEqualCanonicalizing` (MySQL 8.4 reordena chaves JSON; asserções mantidas). |
| `VETORSMART_CONCURRENCY=1 … MysqlConcurrencyTest.php` | 5 de 5 aprovados (MySQL 8.4.11) |
| `vendor/bin/pint --test` | Reprovado em 7 arquivos; corrigidos com `pint` (somente formatação); agora aprovado |
| `npx tsc --noEmit` | 0 erros (Wayfinder gerado na cópia isolada) |
| `npm run build` | Aprovado (cópia isolada) |

Arquivos corrigidos pelo Pint: `app/Policies/ServiceCatalogPolicy.php`, `app/Models/Customer.php`, `app/Http/Resources/Api/V1/{ServiceResource,ServiceCategoryResource,ProfessionalResource,CustomerResource}.php`, `app/Http/Controllers/Api/V1/CustomerController.php`.

## Homologação

- Site respondendo: `https://vetorsmart.com.br/` → 200; `/login` → 200.
- Não realizados: desktop, 390 px, isolamento das empresas A e B, site público e assistente, fluxo completo até a conclusão.
- Resultado: **não verificado**. Nada declarado como aprovado.

## Não realizado / restrições

- Homologação no navegador (acima).
- Nenhuma alteração no `.env` de produção, nos containers `vetorsmart`/`worker`/`scheduler`, em migrations ou em credenciais.
- Nenhuma conta, solicitação ou dado criado na produção.
- Deploy realizado com autorização: imagens `vetorsmart`, `vetorsmart-worker` e `vetorsmart-scheduler` reconstruídas e reiniciadas (`docker compose up -d --no-deps`). Sem migrations (nenhuma pendente). Rollback: tags `rollback-pre-services-01.4`.
- Nenhum commit, push ou merge.

## Pendências e riscos

---

# Execução: VETORSMART-AUTH-UX-01 (Padronização da autenticação)

Data: 12/11/2026. Local: `gateway/vetorsmart/`. Detalhes completos em `docs/VETORSMART-AUTH-UX-01.md`.

## Instrução

`correio.md` difere da instrução anterior (VETORSMART-SERVICES-01.2). A nova instrução é VETORSMART-AUTH-UX-01: legibilidade, imagem de fundo, política de senhas e favicon das telas de autenticação.

## Resultado

**IMPLEMENTAÇÃO E TESTES AUTOMATIZADOS:** aprovados.

**EVIDÊNCIAS VISUAIS DESKTOP E MOBILE:** pendentes.

**IMPLANTAÇÃO NA VPS:** não realizada.

## Arquivos modificados

- Backend:
  - `app/Services/PasswordPolicy.php` (novo)
  - `app/Concerns/PasswordValidationRules.php`
  - `app/Providers/AppServiceProvider.php`
  - `app/Actions/Fortify/ResetUserPassword.php`
  - `app/Http/Requests/Settings/PasswordUpdateRequest.php`
  - `app/Http/Controllers/Settings/SecurityController.php`
- Frontend:
  - `resources/css/app.css`
  - `resources/js/app.tsx`
  - `resources/js/layouts/auth-layout.tsx`
  - `resources/js/components/vetor-smart/auth-shell.tsx`
  - `resources/js/components/password-input.tsx`
  - `resources/js/pages/auth/` (confirm-password, forgot-password, register, reset-password, two-factor-challenge)
  - `resources/views/app.blade.php`
- Ícones:
  - `public/favicon.ico` e `public/apple-touch-icon.png` (substituídos)
  - `public/images/vetor-smart-favicon-32.png` e `public/images/vetor-smart-favicon-192.png` (novos)
  - `public/favicon.svg` (removido; era o logotipo do Laravel)
- Testes: `tests/Feature/Auth/PasswordPolicyTest.php` (novo)
- Documentação: `docs/VETORSMART-AUTH-UX-01.md` (novo)

## Correções visuais

- **Causa do texto invisível:** em tema escuro, os inputs herdavam texto quase branco (`#E2E8F0`) sobre o cartão branco. A classe `.vs-auth` agora fixa os tokens claros nas telas de autenticação e desativa o variant `dark:` dentro delas.
- **Contraste (WCAG AA):**
  - texto 18:1
  - labels e dicas 7,6:1
  - placeholder 4,8:1
  - erros 4,8:1
- **Estados e ícones:** foco azul com anel de 3 px. Autofill tratado no Chrome, Safari e Firefox. O ícone mostrar/ocultar usa `#475569` e tem rótulos em pt-BR.
- **Mesma identidade nas 6 telas** (login, cadastro, recuperação, redefinição, confirmação e 2FA):
  - imagem oficial `auth-background.svg` mantida, em `cover`
  - película reduzida para 0,55
  - cartão branco sólido
- **Telas corrigidas:** a dupla moldura da 2FA foi removida, e recuperação e redefinição foram traduzidas para pt-BR.

## Política de senhas implementada

- **Usuários comuns:** mínimo de 8 caracteres de qualquer tipo, incluindo só números. Sem máximo de 8 caracteres e sem exigência de maiúsculas ou símbolos.
- **Onde vale:** cadastro, redefinição e alteração de senha.
- **Frontend:** dica de senha no cadastro e na redefinição, e `passwordrules` vindo do backend.
- **RootAdmin:** política rigorosa preservada (12 caracteres, maiúsculas e minúsculas, números, símbolos e `uncompromised` em produção). MFA obrigatório inalterado.
- **Mantido sem alteração:** hash, rate limit de login, senhas existentes e sessões.

## Favicon utilizado

Símbolo "V" oficial do VetorSmart, sem texto, gerado de `public/images/vetor-smart-logo-transparent.png`:

- ICO com 16, 32 e 48 px
- PNG de 32 e 192 px
- apple-touch-icon de 180 px com fundo branco

As referências estão em `app.blade.php` com `?v=2` para invalidar o cache, e valem para as rotas públicas e privadas.

## Testes executados

Banco isolado `vetorsmart_test`; o banco operacional não foi usado.

| Verificação | Resultado |
|---|---|
| `php artisan test --compact tests/Feature/Auth/PasswordPolicyTest.php` | 9 aprovados |
| `php artisan test --compact` | 346 testes: 341 aprovados, 0 falhas, 5 pulados (concorrência MySQL, pulados por padrão) |
| `vendor/bin/pint --dirty` | aprovado |
| `npx tsc --noEmit` | 0 erros |
| `npm run build` | aprovado |

Casos cobertos:
- senha de 7 caracteres recusada com a mensagem em pt-BR
- senhas aceitas: 8 dígitos, só letras e frase longa com símbolos
- usuário comum redefine e altera a senha para 8 dígitos
- RootAdmin com senha fraca é recusado e com senha forte é aceito
- os testes já existentes de login, cadastro, redefinição e MFA continuam aprovados

## Evidências desktop e mobile

Não coletadas. A inicialização do servidor local foi recusada nesta sessão, então não houve verificação em 1440 px e 390 px.

## Status de implantação na VPS

Não implantado. Nenhum commit, push, deploy ou alteração em containers.

1. Fornecer, por canal autorizado, as credenciais das contas de homologação A/B (ou outro método autorizado) para executar a homologação.
2. Pendências herdadas: verificadas em 12/11/2026. O banco `vetorsmart` de produção tem 0 usuários e 0 tenants; não existem contas `srv.owner.a/b@example.test` nem dados `[HOMOLOG-SRV]`. Nada foi removido. Confirmar se o homologação anterior ocorreu em outro banco.

**Atualização:** `php artisan db:seed --force` executado em produção. Seeder revisado antes: apenas `SegmentSeeder` (10 segmentos de referência, `updateOrCreate`, sem contas nem senhas). Resultado: `segments`=10; `users`=0; `tenants`=0.

## Critério final

Não atendido. O módulo não está validado nem homologado no ambiente real.

---

1. Homologação visual em 1440 px e 390 px das 6 telas, incluindo autofill no Chrome e no Firefox, tema escuro do sistema e favicon (desktop e mobile).
2. Implantação na VPS pelo procedimento controlado.
3. A dica de senha na redefinição mostra a regra simples. Para um RootAdmin, a política rigorosa é aplicada e informada pelo backend.
4. O comando `platform:root-admin` mantém a validação própria (mínimo de 12 caracteres).
5. Os sites públicos dos tenants não foram alterados e usam o `/favicon.ico` padrão.
