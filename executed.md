# Execução: VETORSMART-PANELS-04 — E-mail operacional e notificações

Data: 18/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`. Nada foi feito na VPS.

## Análise do correio

O `correio.md` mudou: este arquivo registrava a PANELS-03, e o correio atual é a **PANELS-04**. A etapa foi executada e o conteúdo anterior deste arquivo foi substituído.

## Parecer

**APROVADO COM RESTRIÇÕES.** Relatório completo: `docs/VETORSMART-PANELS-04.md`.

## Implementações

1. **PANELS-03 consolidada:** commits `32daf42` e `58da0e0`, com push na `origin/main`. Sem deploy.
2. **E-mail:**
   - tudo pela fila, com os e-mails operacionais entrando só após o commit;
   - 3 tentativas, depois `failed_jobs`;
   - tema VetorSmart (logo, cores e rodapé);
   - e-mails de autenticação em português (antes saíam em inglês);
   - links sempre no domínio da plataforma.
3. **Notificações:**
   - nova solicitação, agendamento confirmado ou remarcado, agendamento cancelado, conversa aguardando atendimento e aviso administrativo (suspensão ou reativação) para a equipe;
   - confirmação de solicitação, confirmação e cancelamento de agendamento e conclusão do serviço para o cliente com e-mail;
   - sem duplicidade (chave única por evento), sem aviso ao autor da ação, nada em rollback, sem WhatsApp.
4. **Central:** sino com o número de não lidas, `/notificacoes` paginada, "Abrir" (marca como lida e vai ao registro), marcar como lida e marcar todas. Isolada por empresa e por pessoa (404 para avisos alheios).
5. **Preferências:**
   - e-mail por categoria, para cada pessoa;
   - avisos administrativos obrigatórios;
   - e-mails aos clientes ligados e desligados pela empresa (só proprietário e administrador);
   - nenhuma comunicação comercial.

## Testes

- Pest: **552 de 552** (3426 asserções), incluindo:
  - rollback, duplicidade, isolamento e permissões;
  - escape de HTML;
  - falha e recuperação do SMTP com fila e worker reais.
- PHPStan: 0 erros. Pint: ok. `tsc`: ok. Build: ok.
- Navegador: funcional **9 de 9**. Visual **32 de 32** (Chromium e Firefox × desktop e celular × claro e escuro × proprietário e operador), com 0 violações de axe.

## Defeitos encontrados

- E-mails de autenticação em inglês: corrigido.
- Risco de links de e-mail apontando para o host da empresa: evitado.
- Função auxiliar de teste com nome duplicado: corrigido.

## Pendências de produção

1. Configurar o SMTP (`MAIL_*`) e `MAIL_FROM_ADDRESS` em `@vetorsmart.com.br`.
2. Configurar SPF, DKIM e DMARC.
3. Definir `APP_URL` e `PLATFORM_PUBLIC_URL` como `https://vetorsmart.com.br`.
4. Conferir o worker: reinício automático, `queue:restart` no deploy e acompanhamento de `failed_jobs`.
5. Executar no deploy as 5 migrations (2 da PANELS-02 e 3 da PANELS-04). Depois, enviar um e-mail de teste.

## Restrições

- RootAdmin sem homologação visual (a conta local não foi criada).
- Mudanças da PANELS-04 sem commit, aguardando autorização.
- As migrations novas foram aplicadas só no banco local.
- O `pnpm-lock.yaml` não foi tocado.
