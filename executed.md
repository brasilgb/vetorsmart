# Execução: VETORSMART-PANELS-02 — Correções críticas e homologação autenticada

Data: 18/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`. Nada foi feito na VPS.

## Análise do correio

O `correio.md` mudou: este arquivo registrava a PANELS-01, e o correio atual é a **PANELS-02**. A etapa foi executada e o conteúdo anterior deste arquivo foi substituído.

## Parecer

**APROVADO COM RESTRIÇÕES.** Relatório completo: `docs/VETORSMART-PANELS-02.md`.

## Correções

- **D1:** o `homolog:purge` agora remove as tabelas operacionais e as do atendimento, em ordem, sem desativar as chaves estrangeiras. Administradores da plataforma nunca são removidos.
- **D2:** a tela de login tem o link "Esqueci minha senha". O fluxo completo foi validado no navegador.
- **R1:** limite de 20 cadastros por hora por IP (o limite de 5 por minuto já existia; a PANELS-01 errou ao dizer que não havia nenhum). Campo-isca contra robôs. Verificação de e-mail obrigatória para o painel, o RootAdmin e a API. As contas existentes foram marcadas como verificadas pela migration.
- **R2:** nova coluna `tenants.is_homolog`, preenchida pela migration a partir do prefixo. O cadastro público recusa o prefixo `[HOMOLOG]`. Novo comando `homolog:mark` para marcar empresas.
- **R3:** as props compartilhadas reaproveitam o `TenantContext`, com 2 consultas a menos por página.
- **R4:** "nova solicitação" busca os clientes no servidor, com limite de 50 (com 300 clientes: 20,5 KB → 11,4 KB).

## Verificações

- Pest: **513 de 513**, 3273 asserções.
- PHPStan: 0 erros. Pint: ok. `tsc`: ok. Build: ok.
- Fluxo funcional no navegador: **24 de 24** (cadastro, confirmação de e-mail, `homolog:mark`, cliente, solicitação, equipe, permissões do operador, recuperação de senha).
- Matriz visual do painel (Chromium e Firefox × desktop e celular × claro e escuro, proprietário e operador): 196 verificações. 188 aprovadas, com 0 violações de axe, sem rolagem horizontal e sem erro de JavaScript. As outras 8 são o redirecionamento esperado de `/email/verify` para quem já está verificado.

## Restrições

1. **RootAdmin não homologado visualmente.** A redefinição de senha local foi bloqueada pela política de permissões da sessão. A homologação do painel usou contas novas, criadas pelo próprio cadastro.
2. WebKit não testado.
3. **Antes do deploy:** confirmar o envio de e-mail em produção (o cadastro depende dele) e executar as duas migrations novas.
4. Os navegadores antigos do cache do Playwright foram removidos pelo instalador, ao baixar as versões compatíveis.

## Git

Sem commit nem push. O `pnpm-lock.yaml` já estava modificado e não foi tocado. As migrations foram aplicadas só no banco local.

## Para destravar (proprietário)

1. Autorizar commit e push.
2. Conferir o e-mail de produção.
3. Informar uma credencial de root de homologação para a homologação visual do RootAdmin.
