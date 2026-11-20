# VETORSMART-JOURNEY-01 — Integração da jornada pública e preservação da origem comercial

Data: 20/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

**Restrições cumpridas:**
- Sem push, deploy, VPS ou produção.
- Sem dependências novas.
- Cobrança, Efí e autenticação não foram alteradas.
- Nenhuma chamada externa feita pela aplicação. Na verificação visual, o navegador de teste chegou a abrir uma vez o link do WhatsApp (seção 6).
- **1 migration local**, justificada na seção 3.

**Consolidação da GROWTH-01:** commit `72b85a2`. Ficaram fora do commit `correio.md`, `executed.md` e `.correio.md.swp` (arquivo temporário do editor).

## Parecer final

**Concluída.** A jornada pública funciona de ponta a ponta: Portal Regional → site do prestador → solicitação ou atendimento IA → triagem → agendamento → conclusão.

O principal problema encontrado foi de **atribuição forjável**:
- a origem "Portal Regional" vinha de `?origem=portal` e de um campo oculto do formulário, e qualquer pessoa podia digitar esse parâmetro;
- além disso, a origem não chegava ao atendimento IA.

**Como ficou:** os links do portal são assinados, o site registra a chegada **no servidor**, e a forma como o cliente descobriu a empresa ficou separada do canal que ele usou.

## 1. Integração portal → site

- **Links assinados:** os cartões do portal levam `?origem=portal&ref=<momento>.<assinatura>`. A assinatura (HMAC-SHA256 com a chave da aplicação) cobre a empresa e o momento, e o link vale por 7 dias.
- **Serviço preservado:** o serviço pesquisado no portal (`servico`) continua indo para o site e já vem selecionado no formulário (verificado no navegador).
- **Links limpos no site:** como a atribuição fica na sessão do servidor, os links internos do site não carregam mais `?origem=portal`.
- **Classificação do portal inalterada:** continua orgânica e igual para todos. O teste "a ordem do portal ignora o volume de pedidos" continua aprovado.
- **Celular:** portal, site e formulário verificados sem rolagem horizontal e sem erros.

## 2. Preservação da origem

| Situação | Canal (`origin`) | Descoberta (`discovery`) |
|---|---|---|
| Formulário do site, chegada pelo link assinado do portal | Portal Regional | portal |
| Formulário do site, visita direta | Site público | — |
| Conversa da IA iniciada após chegada pelo portal | Site / IA | portal |
| A mesma conversa identificada por um operador (IA → humano) | Site / IA | portal (mantida) |
| Painel | Painel | — (o painel nunca define descoberta) |

**O que não concede a origem portal** (testado):
- `?origem=portal` digitado à mão, sem assinatura;
- `ref` assinado para outra empresa;
- `ref` adulterado;
- `ref` com mais de 7 dias;
- o campo `origem` enviado pelo navegador no formulário: é ignorado, e um valor diferente de `portal` continua recusado;
- uma visita registrada há mais de 24 horas.

**Outras garantias:**
- **Dados de navegação:** a sessão guarda apenas o horário da chegada, por empresa. Nenhum dado pessoal ou de navegação é registrado.
- **Duplicidade:** o token de envio único e a trava contra reenvio foram mantidos (testes de reenvio e de concorrência aprovados).
- **Origem imutável:** a triagem não altera a origem (testado).

## 3. Migration

`2026_11_19_194852_add_discovery_to_service_requests_and_assistant_conversations`:
- `service_requests.discovery`, nullable, com índice `(tenant_id, discovery, created_at)`;
- `assistant_conversations.discovery`, nullable;
- **preenchimento dos registros antigos:** solicitações com origem `portal` recebem `discovery = portal`, porque vieram do portal por definição. Os demais registros ficam `NULL` (desconhecido); nada é presumido.

**Por que é indispensável:** sem uma coluna separada, a visita que chega pelo portal e usa a IA perderia uma das duas informações: o canal "IA" ou a descoberta "portal".

**Execução:** aplicada somente no MySQL local, onde 18 de 18 solicitações antigas com origem portal foram preenchidas. O banco de testes é migrado automaticamente.

## 4. Solicitação, atendimento e agendamento

**Solicitação e atendimento (verificados e mantidos):**
- escolha do serviço, descrição da necessidade e contato do consumidor;
- confirmação "Solicitação recebida", exibida somente depois que o pedido é salvo;
- encaminhamento automático para a triagem;
- transferência da IA para um operador humano, com histórico;
- nenhuma conta é exigida do consumidor.

**Agendamento:** sem alteração de código. As garantias pedidas já existem e foram confirmadas pelos testes:
- o consumidor só vê horários realmente disponíveis;
- o conflito é verificado dentro da transação, com bloqueio de registro;
- o agendamento pela IA usa chave de idempotência;
- a confirmação vem somente depois da gravação;
- mensagens claras quando o horário não está disponível;
- histórico das alterações preservado.

**Painel da equipe:** o detalhe da solicitação informa "O cliente chegou pelo Portal Regional" quando o canal foi outro, por exemplo a IA.

## 5. Arquivos

**Novos:**
- `app/Enums/DiscoverySource.php`
- `app/Services/Portal/PortalReferral.php`
- a migration da seção 3
- `tests/Feature/Websites/PortalAttributionTest.php`

**Alterados:**
- `app/Http/Controllers/Portal/PortalController.php`: link assinado por cartão.
- `resources/views/portal/partials/cards.blade.php`: 2 links, editados com sua autorização.
- `app/Http/Controllers/Site/SiteController.php`, `SiteRequestController.php` e `AssistantController.php`: registram a chegada e leem a atribuição do servidor.
- `app/Services/ServiceRequestService.php` e `app/Services/Assistant/AssistantOperations.php`: guardam a descoberta.
- `app/Models/ServiceRequest.php` e `app/Models/AssistantConversation.php`
- `app/Http/Controllers/Tenant/ServiceRequestController.php` e `resources/js/pages/tenant/requests/show.tsx`: descoberta no detalhe da solicitação.
- `database/seeders/VetorSmartDemoSeeder.php`: o bloqueio de chamadas HTTP agora vale só durante a execução do seeder. Antes ele continuava ativo depois e quebrava um teste quando o servidor Vite estava rodando.
- `tests/Feature/Websites/RegionalPortalTest.php` e `tests/Feature/Operations/IntegrationJourneyTest.php`: passaram a chegar pelo link assinado. Antes usavam o parâmetro forjável, que deixou de valer de propósito.

**Não alterados, a seu pedido:**
- `resources/views/website/solicitar.blade.php`: o campo oculto não é mais exibido, porque o controller deixou de informá-lo à view, e é ignorado pelo servidor.
- Meu Crescimento: continua como no commit da GROWTH-01, sem o indicador "chegaram pelo portal por qualquer canal".

## 6. Testes e verificações

| Verificação | Resultado |
|---|---|
| `PortalAttributionTest` (novo): link assinado; outra empresa, link expirado, adulterado ou sem assinatura; janela de 24 horas; IA → humano mantendo a descoberta; conversa de visita direta; painel e origem portal | **9 de 9** |
| Websites, Assistant, Agenda, concorrência MySQL, fluxo e isolamento de solicitações, jornada de integração, gestão operacional, Growth e base de demonstração | **285 testes: 276 aprovados, 9 ignorados (já eram antes), 0 falhas** |
| PHPStan, Pint, TypeScript e build | ok |
| Navegador headless, 3 empresas de demonstração (celular com tema escuro e desktop com tema claro): portal (categoria, região, busca) → cartão → site → formulário | **6 de 6 jornadas**: links assinados, serviço preservado, sem campo forjável, sem rolagem horizontal e sem erros |

**Observação sobre a verificação visual:** na primeira execução, o seletor do script pegou o botão de WhatsApp do site, e o navegador abriu `wa.me` com o número fictício da demonstração. Nenhum dado foi enviado. O script passou a bloquear qualquer host externo, e a verificação foi repetida.

O formulário não foi enviado na verificação visual, para não alterar a base de demonstração. O envio está coberto pelos testes.

## 7. Pendências e limitações

1. **Links antigos do portal:** links copiados antes desta etapa, sem `ref`, passam a contar como visita direta.
2. **Link compartilhado:** um link copiado do portal e repassado a outra pessoa mantém a atribuição por até 7 dias. É uma visita que, de fato, partiu do portal.
3. **Cookies:** a atribuição depende do cookie de sessão do site. Navegadores que bloqueiam cookies contam a visita como direta.
4. **Sites por subdomínio:** o fluxo foi validado no endereço padrão. O de subdomínio (`PLATFORM_SUBDOMAIN_SITES`) segue desligado e usa os mesmos controllers.
5. **Indicador não implementado:** o indicador "chegaram pelo portal por qualquer canal" no Meu Crescimento ficou fora desta etapa, a seu pedido. Os dados já estão gravados em `discovery`.

## 8. Git

- `72b85a2`: GROWTH-01.
- **JOURNEY-01 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**

Aguardando nova orientação.
