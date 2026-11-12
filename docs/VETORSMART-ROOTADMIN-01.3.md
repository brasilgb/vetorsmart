# VETORSMART-ROOTADMIN-01.3 — Encerramento de segurança administrativa

Data: 12/11/2026. Local: `gateway/vetorsmart/`. Banco de desenvolvimento e de testes: MySQL.
Relatórios anteriores: `docs/VETORSMART-ROOTADMIN-01.md`, `…01.1-SEGURANCA-UX.md`, `…01.2-HOMOLOGACAO-FINAL.md`.

**Resultado:** critérios de aceite atendidos. Recuperação operacional de MFA implementada e testada, acessibilidade homologada nos dois tamanhos com axe-core (WCAG 2.2 AA) e teclado, traduções concluídas, sem bloqueios críticos de segurança aberto. Ressalvas de produção em §6 (configuração de ambiente, não de código).

## 1. Recuperação operacional de MFA: `platform:mfa-reset`

### Implementação

Arquivo: `app/Console/Commands/ResetRootAdminMfa.php`. Somente linha de comando: não há rota HTTP para essa operação.

Uso:

```
php artisan platform:mfa-reset <e-mail> --reason="<justificativa>" --confirm-email=<e-mail>
```

Controles:

| Requisito | Como é atendido |
|---|---|
| Somente CLI | Comando Artisan, sem rota, sem tela. |
| Identificação do administrador | O e-mail é obrigatório. O comando recusa contas que não sejam administrador global. |
| Justificativa obrigatória | `--reason` com pelo menos 20 caracteres. Sem justificativa, nada é alterado. |
| Confirmação explícita | Em modo interativo, o operador digita o e-mail. Sem interação, `--confirm-email` precisa repetir o e-mail. Divergência cancela a operação. |
| Auditoria sem segredos | Registro `mfa.reset_by_operator` com o administrador afetado (subject), a justificativa e a quantidade de sessões encerradas. Segredo, códigos e tokens não entram no registro. Testado. |
| Invalidação dos fatores | Zera `two_factor_secret`, `two_factor_recovery_codes` e `two_factor_confirmed_at`. |
| Invalidação de sessões | Remove as linhas da tabela `sessions` do usuário e troca o `remember_token`. Quando o driver de sessão não é `database`, o comando avisa que as sessões precisam ser encerradas pelo mecanismo do driver. |
| Novo enrollment obrigatório | O middleware `rootadmin.mfa` e o gate `platform-admin` exigem fator confirmado. No próximo login, o administrador é levado à configuração. Testado no navegador (R2). |
| Senha | Não é alterada. A verificação de identidade é feita fora do sistema, antes da execução. |

Testes: `tests/Feature/RootAdmin/ResetRootAdminMfaCommandTest.php`, 5 casos: revogação com encerramento de sessões e novo enrollment exigido; auditoria sem segredos; conta comum e e-mail inexistente recusados; justificativa curta recusada; confirmação ausente ou divergente recusada.

### Procedimento operacional

Aplica-se quando um administrador global perdeu todos os fatores (aplicativo e códigos de recuperação).

1. **Verificação de identidade fora do sistema.** O titular confirma a solicitação por canal previamente cadastrado (telefone e e-mail corporativo já registrados em procedimento interno). Não se aceita pedido apenas pelo próprio canal da conta.
2. **Autorização.** Quando houver outro administrador global habilitado, ele aprova a operação por escrito antes da execução. Em instalação com um único administrador, a autorização é registrada pelo responsável da plataforma, com a mesma formalidade.
3. **Execução somente no servidor**, por quem tem acesso ao shell, com a justificativa que será registrada na auditoria. Não há endpoint público e não há credencial fixa.
4. **Revisão.** Após a execução, o operador confirma na trilha de auditoria a entrada `mfa.reset_by_operator`, com o motivo e a contagem de sessões encerradas.
5. **Novo enrollment.** O titular entra com a senha, é levado à configuração do fator, e gera um novo segredo e novos códigos de recuperação. Os fatores antigos já não funcionam.
6. **Notificação de segurança ao titular** sobre a revogação, a data, a justificativa e quem autorizou.

Procedimento de conta única: não há administrador de reserva. Os passos 1 e 2 se reduzem à verificação de identidade do titular pelo responsável da plataforma, com registro escrito. Recomenda-se manter um segundo administrador global habilitado para evitar essa situação.

## 2. Acessibilidade: homologação

### Ferramentas

- **Navegador:** `chrome-headless-shell` 153 do cache do Playwright, controlado por CDP (teclas reais, `Input.dispatchKeyEvent`). Driver mínimo fora do projeto; nenhuma dependência adicionada.
- **Verificador automático:** axe-core 4.14.0 instalado em pasta de trabalho fora do projeto, com as regras `wcag2a`, `wcag2aa`, `wcag21aa` e `wcag22aa`. Os resultados guardam apenas identificador da regra e seletor, sem trechos de HTML.
- **Verificação manual complementar:** ordem de tabulação, indicador de foco pintado (`outline` ou `box-shadow`) em cada parada, navegação por teclado no menu do usuário e no menu lateral mobile.

### Escopo e resultado

Duas execuções completas, 1440 px e 390 px. Em cada tamanho, 19 telas: login, verificação de senha, configuração do MFA (antes e depois de revelar o QR), erro de código inválido, desafio MFA (normal e com erro), visão geral, empresas, detalhe da empresa, segmentos, usuários, auditoria, segurança, configuração obrigatória após reset, acesso negado, 404.

**Resultado final: 0 violações axe em todas as 19 telas, nos dois tamanhos.** Nenhuma parada de teclado sem indicador de foco.

Fluxos completos por teclado nos dois tamanhos: confirmação de senha com Enter; abertura da configuração e revelação do QR; código inválido com mensagem; ativação; exibição dos códigos de recuperação; saída pelo menu do usuário com setas; desafio MFA com erro e sucesso; operador revoga o fator pelo CLI; novo login exige configuração; usuário de empresa recebe "Acesso negado".

### Problemas encontrados e corrigidos

| # | Achado | Causa | Correção | Verificação |
|---|---|---|---|---|
| 1 | Código TOTP inválido no enrollment não mostrava mensagem | Fortify põe o erro em um *error bag* nomeado, que o Inertia não compartilha com a página | A confirmação passou a usar JSON; o 422 com `errors.code` aparece no campo | Teste PHP `postJson` 422; navegador S4 |
| 2 | Aviso "configure o MFA" sumia após confirmar a senha | Flash consumido por um redirecionamento intermediário | O aviso passou a ser parte do cartão de MFA, calculado pelas props | Navegador S2 e R2 |
| 3 | `color-contrast` em paginação desabilitada | `text-vs-muted/60` com opacidade | Cor plena e `aria-disabled` | axe nas telas de auditoria |
| 4 | `scrollable-region-focusable` em metadados de auditoria e tabela de usuários | Área rolável sem foco | `tabIndex={0}` e rótulo (`aria-label`) | axe em auditoria e detalhe da empresa, mobile |
| 5 | Link de logo sem nome acessível nas telas de autenticação | Título vazio no layout | `aria-label` em português | axe no desafio MFA |
| 6 | `color-contrast` no momento de ativação, em uma execução | Verificação feita durante recarregamento da página | Espera pela estabilização antes da auditoria; em duas execuções seguidas o resultado foi 0 | axe S5 nas duas execuções finais |

Os itens 1 a 5 são defeitos de produto, corrigidos no código. O item 6 é tempo de captura: a execução seguinte, depois da espera, não reproduziu a violação, e o teste de contraste dos tokens (branco sobre `#2563EB`) dá 5,17:1, acima de 4,5:1.

### Limites desta verificação

- O axe-core cobre apenas o que pode ser automatizado. Leitura por leitor de tela (NVDA, VoiceOver) não foi feita.
- Contraste de texto sobre imagem de fundo (tela de login) não é calculado pelo axe; a sobreposição escura de 72% foi considerada suficiente, mas não medida.
- Não foi testado zoom de 200% nem preferência de movimento reduzido.

## 3. Traduções

Concluídas:

- Área de segurança, confirmação de senha, configurações (menu, cabeçalho), perfil e aparência.
- Cartão de exclusão de conta e seleção de tema (Claro, Escuro, Sistema).
- Menu do usuário ("Sair"), páginas 403, 404, 429 e 500.
- Mensagens do Fortify (`lang/pt_BR.json`) e regras de validação mais comuns (`lang/pt_BR/validation.php`). O locale padrão da aplicação passou a ser `pt_BR` em `AppServiceProvider::configureDefaults`.

Pendente (inglês como fallback): regras de validação não listadas em `lang/pt_BR/validation.php`. Nenhuma tela percorrida exibiu texto em inglês.

## 4. Testes automatizados

| Comando | Resultado |
|---|---|
| `php artisan test --compact` | **334 testes: 329 aprovados, 0 falhas, 5 pulados** |
| `VETORSMART_CONCURRENCY=1 php artisan test --compact tests/Feature/Operations/MysqlConcurrencyTest.php` | 5 aprovados (MySQL) |
| `vendor/bin/pint --dirty` | aprovado |
| `npx tsc --noEmit` | 0 erros |
| `npm run build` | aprovado |

### Os cinco testes pulados

São os cinco de `tests/Feature/Operations/MysqlConcurrencyTest.php`. Eles são pulados por desenho quando não se define `VETORSMART_CONCURRENCY=1` com `DB_CONNECTION=mysql`, porque exigem dois servidores de sessão MySQL com bloqueios reais e transações concorrentes. Com a variável definida, os cinco passam no MySQL de teste (`vetorsmart_test`). Não são lacuna de cobertura. Para a execução de release, a recomendação é definir a variável no pipeline.

## 5. Configuração de segurança para produção

Verificação com `php artisan config:show`, sem leitura de `.env` e sem alteração de segredos. Valores observados no ambiente local:

| Chave | Valor local | Valor exigido em produção |
|---|---|---|
| `app.env` | `local` | `production` |
| `app.debug` | `true` | `false` |
| `session.secure` | `false` | `true` (HTTPS obrigatório) |
| `session.http_only` | `true` | `true` |
| `session.same_site` | `lax` | `lax` ou `strict` |
| `session.driver` | `database` | `database` (necessário para o encerramento de sessões do reset) |
| `APP_KEY` | definida | definida, rotacionada se houver suspeita de vazamento |
| Política de senha | regra de produção (mínimo 12, mistura, símbolos, senhas vazadas) só ativa em `production` | mesma |
| Limitação de login | 5 por minuto por e-mail e IP | mesma |
| Limitação de desafio MFA | 5 por minuto por tentativa pendente | mesma |
| Hosts confiáveis | central, subdomínios e domínios verificados | mesmo |

Ação para o responsável de implantação: definir `APP_ENV=production`, `APP_DEBUG=false` e `SESSION_SECURE_COOKIE=true` no ambiente de produção, e confirmar que o servidor atende apenas HTTPS. Nenhum desses valores foi alterado nesta etapa.

## 6. Riscos e ressalvas

1. **Ambiente de produção ainda não configurado** (§5). Com `APP_DEBUG=true` ou cookies sem `Secure`, o risco é de vazamento de dados de diagnóstico ou de sessão. É o único item bloqueante para implantação.
2. **Conta única sem administrador de reserva.** O procedimento é executável, mas depende de verificação de identidade feita pelo responsável da plataforma. Recomenda-se um segundo administrador global habilitado.
3. **Encerramento de sessões depende do driver `database`.** Com outro driver, o comando avisa, mas não encerra sessões. Em produção, o driver deve permanecer `database`, ou o procedimento deve incluir a revogação pelo driver.
4. **Acessibilidade automatizada não substitui leitor de tela** (§2, limites).
5. **Dados de teste no banco local**: a empresa "Empresa Homologação Negado" e o usuário de empresa de homologação permanecem no banco de desenvolvimento, desativados. Remoção depende de autorização.

## 7. Situação das contas de homologação

Ao final:

- `homolog.root@example.test`: suspensa, senha substituída por valor aleatório não registrado, fatores de MFA removidos (também pelo próprio reset da execução), sessões encerradas.
- `homolog.tenant@example.test`: suspensa, senha substituída por valor aleatório não registrado, sessões encerradas.
- Arquivos de credenciais no scratchpad (senhas, segredo TOTP e códigos de recuperação): excluídos. Estavam fora do repositório.
- Relatórios de acessibilidade (`a11y-results-*.json`, `a11y-*.log`) e capturas (`shots/`) no scratchpad: sem segredos. As capturas de enrollment foram tiradas antes de revelar o QR ou os códigos, ou em telas sem esses dados.

## 8. Alterações desta etapa

Código:

- `app/Console/Commands/ResetRootAdminMfa.php` (novo).
- `app/Providers/AppServiceProvider.php`: locale `pt_BR`.
- `app/Http/Controllers/Settings/SecurityController.php`: props de estado do MFA.
- `app/Http/Responses/PasswordConfirmedResponse.php`: destino pós-confirmação para administradores.
- `resources/js/components/two-factor-section.tsx`: confirmação por JSON, aviso no cartão, textos.
- `resources/js/components/delete-user.tsx`, `appearance-tabs.tsx`, `nav-main.tsx`, `resources/js/layouts/settings/layout.tsx`, `resources/js/layouts/auth/auth-simple-layout.tsx`, `resources/js/components/vetor-smart/pagination.tsx`, `resources/js/components/user-menu-content.tsx`, `resources/js/pages/settings/*.tsx`, `resources/js/pages/auth/confirm-password.tsx`, `resources/js/pages/rootadmin/audit/index.tsx`, `resources/js/pages/rootadmin/tenants/show.tsx`: acessibilidade e traduções.
- `resources/views/errors/429.blade.php`, `500.blade.php` (novos).
- `lang/pt_BR/validation.php` e `lang/pt_BR.json` (novos).

Testes:

- `tests/Feature/RootAdmin/ResetRootAdminMfaCommandTest.php` (novo, 5 casos).
- `tests/Feature/RootAdmin/RootAdminMfaTest.php`: caso de confirmação por JSON e caso de retorno à configuração pós-confirmação.

Nenhum commit, push, merge ou deploy. Nenhuma cobrança, consumo de IA, módulo novo, alteração da landing, de regras de assinatura ou de `homolog:purge`. Nenhum dado foi excluído.

## 9. Pendências

1. Configurar o ambiente de produção conforme §5 (responsável de implantação).
2. Segundo administrador global habilitado, para eliminar a dependência de conta única.
3. Leitura com leitor de tela, zoom de 200% e movimento reduzido (fora do alcance do axe).
4. Regras de validação do Laravel ainda sem tradução completa (fallback em inglês).
5. Dados de homologação no banco local (remoção com autorização).
6. Pendências herdadas: modelo de assinaturas e cobrança, créditos de IA, tela de sites e domínios no RootAdmin, licenciamento das imagens, LGPD, integração real com provedor de IA.

## 10. Recomendação de aceite

**Aceite recomendado para o RootAdmin**, nos termos do §11 do brief:

- MFA obrigatório funciona para administradores globais: verificado no navegador, em ambos os tamanhos.
- Recuperação operacional segura: implementada, testada e documentada.
- Acessibilidade homologada: 0 violações axe e navegação por teclado completa nas 19 telas dos dois tamanhos, com as limitações do §2.
- Traduções concluídas, com a ressalva de validação do Laravel (§3).
- Sem bloqueios críticos de segurança ou autorização abertos. Os testes de isolamento de tenant continuam verdes.

A aceitação depende da configuração de produção (§5), que não é código e não foi alterada aqui.
