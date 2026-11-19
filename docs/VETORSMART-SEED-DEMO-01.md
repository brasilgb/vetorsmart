# VETORSMART-SEED-DEMO-01 — Base de demonstração para homologação visual e funcional

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem push, deploy, VPS ou DNS público.
- Sem Efí, cobranças ou assinaturas.
- Sem migrations.
- Sem dependências novas.
- Autenticação e MFA não foram alterados.

**Consolidação da INTEGRATION-02:** commit `0c17037`.

## Parecer final

**Concluída.** O seeder `VetorSmartDemoSeeder` cria três empresas fictícias completas pelo fluxo real do sistema.

As datas passadas foram geradas com o relógio controlado: cada etapa roda no momento em que teria acontecido. Por isso, histórico, auditoria, situações e "Meu Crescimento" saem das regras de negócio reais. Nenhum total foi gravado diretamente.

**Testes e verificação:**
- **Validação visual:** 66 de 66 verificações nas telas do painel e nos sites. Os 3 logins funcionaram, e o portal filtrou por segmento, estado e município.
- **Testes direcionados:** 5 de 5.
- **Execução repetida:** não duplicou nada.

## 1. Como executar

1. **Senha:** defina a senha das contas fictícias no `.env` local (o arquivo é ignorado pelo Git):

   ```
   VETORSMART_DEMO_PASSWORD=<senha com 12 ou mais caracteres>
   ```

   Para gerar uma senha aleatória sem exibi-la no terminal:
   ```
   printf '\nVETORSMART_DEMO_PASSWORD=%s\n' "$(openssl rand -base64 18 | tr -d '/+=')" >> .env
   ```

   Para consultar a senha quando for entrar no painel: `grep VETORSMART_DEMO_PASSWORD .env`.

   No ambiente de desenvolvimento a variável já foi criada com uma senha aleatória. A senha não está no repositório nem nos logs da aplicação.

2. **Execução:**

   ```
   php artisan config:clear
   php artisan db:seed --class=VetorSmartDemoSeeder
   ```

3. **Repetição:** pode ser executado de novo com o mesmo comando.
   - Uma empresa de demonstração que já existe não é recriada: só o nome e a senha dos seus usuários de demonstração são atualizados.
   - Para trocar a senha, altere `VETORSMART_DEMO_PASSWORD`, rode `php artisan config:clear` e execute o seeder outra vez.

O seeder **não** foi incluído no `DatabaseSeeder`.

## 2. Usuários de demonstração

Todos os usuários estão ativos e com e-mail verificado. A senha de todos é o valor de `VETORSMART_DEMO_PASSWORD`.

| Empresa | Owner | Operador |
|---|---|---|
| Silva Elétrica | `owner@silva-eletrica.example.test` (Roberto Silva) | `operador@silva-eletrica.example.test` (Patrícia Lima) |
| ClimaSul Refrigeração | `owner@climasul.example.test` (Marcos Bertolini) | `operador@climasul.example.test` (Luana Zanella) |
| AutoTech Mecânica | `owner@autotech.example.test` (Sérgio Weber) | `operador@autotech.example.test` (Renata Flores) |

Login: `http://localhost:8000/login`.

O operador foi criado para demonstrar a atribuição de responsáveis e as permissões por perfil.

## 3. URLs locais

| Recurso | URL |
|---|---|
| Painel | `http://localhost:8000/dashboard` |
| Site da Silva Elétrica | `http://localhost:8000/eletricistas/silva-eletrica` |
| Site da ClimaSul | `http://localhost:8000/climatizacao/climasul` |
| Site da AutoTech | `http://localhost:8000/oficinas/autotech` |
| Portal Regional | `http://portal.vetorsmart.test:8000/` |
| Portal por estado e município | `http://portal.vetorsmart.test:8000/regioes/rs/porto-alegre` |
| Portal por segmento | `http://portal.vetorsmart.test:8000/categorias/eletricistas` |

O portal responde pelo host `portal.vetorsmart.test`. Para abri-lo no navegador, adicione a entrada abaixo ao arquivo de hosts local (no Windows, `C:\Windows\System32\drivers\etc\hosts`; no Linux, `/etc/hosts`). O DNS público não é alterado.

```
127.0.0.1  portal.vetorsmart.test
```

Cada empresa recebeu também o seu subdomínio (`<empresa>.vetorsmart.test`). Os sites por subdomínio continuam desligados (`PLATFORM_SUBDOMAIN_SITES=false`).

## 4. Registros criados

**Por empresa** (os números são iguais nas três, exceto os serviços):

| Entidade | Silva Elétrica | ClimaSul | AutoTech |
|---|---|---|---|
| Empresa (tenant independente) | 1 | 1 | 1 |
| Usuários (Owner e Operador) | 2 | 2 | 2 |
| Categorias de serviço | 2 | 2 | 2 |
| Serviços ativos | 5 | 5 | 6 |
| Profissionais | 2 | 2 | 2 |
| Janelas de disponibilidade (segunda a sexta, 8h às 12h e 13h às 18h) | 20 | 20 | 20 |
| Clientes | 8 | 8 | 8 |
| Site publicado e listado no portal | 1 | 1 | 1 |
| Solicitações | 15 | 15 | 15 |
| Agendamentos | 12 | 12 | 12 |
| Bloqueio de agenda (treinamento) | 1 | 1 | 1 |
| Conversa da IA aguardando atendimento humano | 1 | 1 | 1 |

**Situação das 15 solicitações de cada empresa:**

| Situação | Quantidade | Detalhe |
|---|---|---|
| Concluídas | 6 | 4 com valor informado e 2 sem valor; entre 56 e 6 dias atrás |
| Agendadas para os próximos 30 dias | 3 | Uma delas foi remarcada |
| Em andamento | 2 | |
| Aguardando triagem | 2 | |
| Canceladas | 2 | Uma antes da triagem e outra já agendada, ambas com motivo |

**Outros registros de operação:**
- **Remarcações:** 2 por empresa (uma em serviço concluído e outra em agendamento futuro).
- **Origens das solicitações:** Portal Regional, site individual, atendimento IA (origem suportada pelo sistema) e painel.
- **Responsáveis:** atribuídos ao Owner, ao Operador ou deixados em branco, sempre com registro no histórico.

**Receita declarada (soma dos valores informados):**

| Empresa | Receita declarada |
|---|---|
| Silva Elétrica | R$ 1.535,00 |
| ClimaSul | R$ 1.500,00 |
| AutoTech | R$ 1.235,00 |

As solicitações canceladas não têm valor.

**Localização (fictícia, no RS):**
- Silva Elétrica: Porto Alegre;
- ClimaSul: Caxias do Sul;
- AutoTech: Santa Maria.

Os segmentos usados são os já existentes: Eletricistas, Climatização e Oficinas.

**Identificação dos dados de demonstração:**
- `tenant_settings.settings.demo_seed = "seed-demo-01"`;
- e-mails sob `example.test`;
- nomes de profissionais e textos marcados como "(demonstração)" ou "(demo)";
- registro de auditoria `tenant.created` com `demo_seed`.

## 5. Segurança

O seeder:
- **Roda somente em `local` ou `testing`.** Em produção é recusado com erro, mesmo com `--force` (testado).
- **Exige `VETORSMART_DEMO_PASSWORD`** com pelo menos 12 caracteres. Sem ela, não roda.
- **Não apaga dados:** não usa `migrate:fresh`, `truncate` nem exclusões.
- **Não altera nada fora do conjunto de demonstração:**
  - um slug que já pertença a uma empresa de fora do conjunto é ignorado, sem alterações (testado);
  - um e-mail que já pertença a outro usuário faz a empresa correspondente ser ignorada.
- **Não envia nada para fora:**
  - e-mails e notificações são interceptados (`Notification::fake`, `Mail::fake`);
  - chamadas HTTP são bloqueadas;
  - nenhum job é colocado na fila (testado).
  - Os avisos internos do painel (sino) são registros reais do sistema: eles foram criados.
- **Não cria assinatura nem cobrança** (testado).
- **É atômico por empresa:** cada empresa é criada numa transação, então uma falha não deixa dados parciais.

## 6. Validação visual

A verificação usou o Chromium headless (Playwright já instalado localmente), com login real dos três Owners. Cada tela foi aberta em dois modos:
- desktop (1366 px), tema claro;
- celular (390 px), tema escuro.

Telas verificadas:
- painel;
- Solicitações (coluna "Responsável");
- detalhe de uma solicitação concluída (valor realizado e histórico completo);
- detalhe de uma solicitação em andamento ("Concluir atendimento" com campo de valor);
- agenda nas visões dia, semana e mês;
- Meu Crescimento (últimos 3 meses);
- Atendimento IA;
- site individual;
- página do segmento no portal.

| Verificação | Resultado |
|---|---|
| Login dos três usuários | 3 de 3 |
| Telas com resposta HTTP 200, sem erro no console e sem rolagem horizontal | 66 de 66 |
| Conteúdo esperado presente ("Responsável", "Valor realizado", "Concluir atendimento", "Receita declarada") | Atendido |
| Modo escuro no painel e no portal | Legível, com contraste adequado |
| Portal: página inicial e estado RS listam as três empresas; Porto Alegre lista só a Silva Elétrica | Atendido |
| Meu Crescimento igual ao banco (Silva: 6 concluídos, 2 cancelados, R$ 1.535,00, 2 sem valor) | Atendido |

**Correção feita durante a validação:** o avatar mostrava "R(" por causa do sufixo "(demo)" no nome dos usuários. O sufixo foi retirado dos usuários e mantido só nos profissionais.

**Senha exposta na sessão:** a senha usada na primeira validação apareceu nesta sessão de trabalho. Ela foi trocada por uma nova senha aleatória, e a validação foi repetida com a senha nova.

## 7. Testes

| Teste | Resultado |
|---|---|
| `tests/Feature/Platform/DemoSeederTest.php` | **5 de 5** |

O arquivo cobre:
- relacionamentos e histórico real;
- coerência entre os registros e Meu Crescimento;
- site público;
- idempotência;
- isolamento entre empresas;
- recusa em produção e sem senha;
- empresa alheia preservada;
- ausência de filas e de assinaturas.

**Análise estática e formatação:** PHPStan e Pint, sem erros.

## 8. Limitações

1. Os sites individuais aparecem sempre no tema claro, mesmo com o sistema no escuro. É o comportamento atual do modelo de site, não alterado nesta etapa. O portal e o painel seguem o tema.
2. Na agenda, uma solicitação "em andamento" continua com o agendamento na situação "Agendado" até a conclusão. É o comportamento atual do sistema.
3. As datas são relativas ao dia da execução. Uma base criada hoje "envelhece": rodar de novo não recria os registros. Para renovar as datas, seria preciso remover as três empresas de demonstração manualmente, o que esta etapa não faz.
4. As empresas de demonstração não são marcadas como homologação, porque empresas de homologação não aparecem no portal. Por isso, entram nos números da plataforma no RootAdmin do ambiente local.
5. Não há imagens por segmento no sistema. Os sites usam o modelo base com a cor de destaque de cada empresa, e nenhuma imagem externa foi baixada.

## 9. Arquivos

- `database/seeders/VetorSmartDemoSeeder.php` (novo)
- `tests/Feature/Platform/DemoSeederTest.php` (novo)
- `config/platform.php`: chave `demo_password`, lida de `VETORSMART_DEMO_PASSWORD`
- `docs/VETORSMART-SEED-DEMO-01.md` (este relatório)
- `.env` local, ignorado pelo Git: variável `VETORSMART_DEMO_PASSWORD`

## 10. Git

- `0c17037`: INTEGRATION-02.
- **SEED-DEMO-01 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**

Nenhuma outra etapa foi iniciada.
