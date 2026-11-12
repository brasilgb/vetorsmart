# VETORSMART-SERVICES-01.1 — Correção de regressões e homologação final

Data: 12/11/2026. Diretório: `gateway/vetorsmart/`. Banco: MySQL.

## 1. Análise

Continuação da execução SERVICES-01. Pendências identificadas: teste de remarcação com divergência 200 × 422; agenda que omitia histórico; remarcação sem mensagem e com HTTP 405; e-mail em inglês; homologação mobile, conflito de remarcação no navegador e canal público pendentes.

## 2. Teste de remarcação: causa real

A expectativa do teste (422 para horário ocupado) foi mantida. A divergência vinha da fixture:

- Os agendamentos eram gravados com horário local (America/Sao_Paulo), enquanto o sistema grava e compara em UTC.
- A consulta de sobreposição usava o instante UTC do horário pedido; o horário local gravado era lido como UTC, a sobreposição não era detectada e a remarcação era aceita.
- Correção: as fixtures passaram a gravar em UTC (`->utc()`). O teste agora também confirma que o agendamento original não muda após a recusa.

Resultado: `AgendaHistoryTest` e `AgendaTest`, 17 aprovados.

## 3. Correções de comportamento

- Agenda mantém atendimentos concluídos, não realizados e cancelados. Ações de remarcar e cancelar só para agendamentos ativos.
- Remarcação mostra a mensagem do servidor junto ao campo. Sucesso recarrega a agenda.
- Remarcação pela interface não retorna mais HTTP 405 (resposta JSON 200 para chamadas JSON).
- Validação de e-mail em português em todos os campos de e-mail.

## 4. Arquivos modificados

- `app/Http/Controllers/Tenant/AgendaController.php`
- `resources/js/pages/tenant/agenda/index.tsx`
- `resources/js/lib/email-validation.ts` (novo)
- `resources/js/pages/auth/login.tsx`, `register.tsx`, `forgot-password.tsx`, `reset-password.tsx`, `settings/profile.tsx`, `tenant/team/index.tsx`
- `tests/Feature/Operations/AgendaHistoryTest.php` (novo)

## 5. Testes e verificações

| Comando | Resultado |
|---|---|
| `php artisan test --compact` | 337 testes: 332 aprovados, 0 falhas, 5 pulados |
| `VETORSMART_CONCURRENCY=1 php artisan test --compact tests/Feature/Operations/MysqlConcurrencyTest.php` | 5 aprovados |
| `vendor/bin/pint --dirty` | aprovado |
| `npx tsc --noEmit` | 0 erros |
| `npm run build` | aprovado |

Os 5 pulados são os de concorrência MySQL, que dependem de variável de ambiente. Com a variável definida, passam.

## 6. Situação da homologação

- **Validado por testes automatizados**: agenda, conflito, remarcação com recusa, histórico, isolamento e concorrência MySQL.
- **Homologado no navegador anteriormente (desktop, 1440 px)**: fluxo operacional completo.
- **Pendente de homologação remota via VPN**: remarcação recusada no navegador, mobile 390 px, solicitação pública até a conclusão, e verificação do ambiente real.

Nenhuma homologação mobile foi declarada.

## 7. Contas e dados

- Contas `srv.owner.a@example.test` e `srv.owner.b@example.test` continuam ativas. Senhas em arquivos protegidos fora do repositório.
- Dados `[HOMOLOG-SRV]` mantidos. Nada excluído.
