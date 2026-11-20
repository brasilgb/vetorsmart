# VETORSMART-SMTP-01 — Habilitar SMTP em produção

Na VPS srv1990862, configure o VetorSmart para usar o SMTP já configurado no .env da infra-abrasil. Use contato@abrasilsistemas.com.br como remetente e VetorSmart como nome de exibição.

1. Confira o config/mail.php e o Docker Compose para identificar quais variáveis o VetorSmart usa e de qual arquivo cada serviço as recebe. Reutilize do .env da infra-abrasil o host, a porta, o esquema de conexão, o usuário e a senha. Transfira somente as variáveis de e-mail necessárias, sem exibir credenciais em comandos, saída, relatórios ou commits.
2. Guarde uma cópia protegida da configuração atual. Troque MAIL_MAILER de log para smtp apenas no VetorSmart. Recarregue o cache de configuração e atualize os serviços do VetorSmart que consomem essas variáveis, incluindo o worker da fila. Preserve o e-mail e os serviços dos demais sistemas.
3. Com uma conta de homologação autorizada, solicite o reenvio da confirmação. Confira o recebimento com o remetente esperado, o link no domínio do VetorSmart e a confirmação efetiva ao abri-lo. Confira os logs e a fila sem expor o conteúdo do link. Se não houver acesso à caixa de entrada, registre o envio ao SMTP e deixe o recebimento e o clique como pendentes de validação.
4. Se a mudança causar falha operacional, restaure a configuração anterior do VetorSmart e confirme a saúde dos serviços. No relatório, registre os serviços atualizados e o resultado de cada verificação, sem incluir segredos.

Mantenha as regras de verificação de e-mail. Esta etapa cobre apenas SMTP e a homologação da confirmação. O ajuste do filtro Canal e a publicação do item 7 seguem em etapa própria.


# VETORSMART-GROWTH-03.1 — Filtro Canal e publicação do item 7

Ambiente: VPS srv1990862, projeto gateway/vetorsmart/.
Último SHA implantado conforme o relatório: 00e028a.
Item 7: commit local 4d13d1f na branch feat/growth-03-item7,
no worktree /opt/infra-abrasil/gateway/vetorsmart-item7.

## 1. Corrigir o filtro Canal

No worktree separado, confirme o estado da branch e reproduza o defeito.
Ao selecionar Painel (?origem=panel), Origem comercial somou 1
solicitação e Canal de entrada somou 2.

Faça as duas visões usarem o mesmo conjunto de solicitações filtrado
por empresa, período e canal. Confira a chamada a byChannel() no
GrowthController e reutilize a lógica de canal já existente, inclusive
o agrupamento dos registros históricos.

Adicione um teste de regressão com solicitações de canais distintos.
Ao selecionar Painel, as duas visões devem somar 1 e a visão de canal
deve mostrar apenas Painel. Confira também o estado sem filtro e a
combinação dos filtros de período e canal. Faça um commit separado.

## 2. Revisar o item 7

Revise o diff de 4d13d1f e preserve a implementação já feita. Confira
Origem comercial, Canal de entrada e Atendimento no detalhe da
solicitação e em cada solicitação do histórico da ficha do cliente.
Valide registros antigos, IA com encaminhamento à equipe e isolamento
entre empresas.

Execute GrowthCommercialTest e RequestTraceabilityTest no MySQL
isolado. Confira TypeScript, build e as duas telas em desktop e mobile.

## 3. Publicar e homologar

Atualize as referências do remoto e confira o diff entre o SHA em
produção e a versão candidata. Publique a branch e integre os dois
commits pelo fluxo do projeto. Se o intervalo de deploy incluir
alterações fora deste escopo, reporte quais são antes de implantá-las.

Implante o SHA integrado pelo procedimento já usado na VPS, com ponto
de retorno registrado. Os dois ajustes dispensam migrations novas.

Na empresa de homologação, compare os totais das duas visões com o
mesmo período, com e sem o filtro Painel. Abra uma solicitação e a
ficha do cliente para conferir os três campos. Verifique a saúde de
web, worker e scheduler após a troca.

Informe os SHAs da correção, do item 7 integrado e da versão em
produção, os totais observados, os testes executados e qualquer
pendência. Preserve as configurações de cobrança, Efí e autenticação.
