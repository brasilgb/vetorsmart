# VETORSMART-BACKEND-05.3 — Homologação final do atendimento

**Projeto:** VetorSmart  
**Diretório:** `gateway/vetorsmart/`  
**Banco:** MySQL

## Objetivo

Eliminar as pendências críticas da BACKEND-05.2 e preparar o atendimento para liberação controlada em produção.

Preservar todas as funcionalidades existentes.

## 1. Homologação real de navegador

Preparar uma empresa de teste isolada, com site publicado, serviços, profissionais e horários configurados.

Validar em desktop (1440 px) e mobile (390 px):

- Site público e widget de atendimento.
- Seleção de serviço e identificação do visitante.
- Preferência de horário e confirmação.
- Indisponibilidade e escolha de alternativa.
- Encaminhamento humano sem identificação.
- Identificação posterior pelo operador.
- Conversão em solicitação.
- Triagem, execução e conclusão do serviço.

Registrar capturas e resultados. Não declarar homologação visual sem navegador real.

## 2. Concorrência real

Executar processos simultâneos com conexões MySQL independentes, disputando o mesmo profissional e horário.

Verificar:

- Ausência de agendamentos conflitantes.
- Integridade transacional.
- Tratamento de deadlocks e timeouts.
- Idempotência sob requisições concorrentes.
- Ausência de solicitações duplicadas.

Usar somente dados de teste.

## 3. Falhas controladas

Simular falha na gravação do agendamento.

Garantir:

- Rollback da transação.
- Nenhuma confirmação indevida.
- Nenhuma solicitação parcialmente gravada.
- Mensagem adequada ao visitante.
- Registro técnico sem exposição de dados sensíveis.

## 4. Privacidade

Implementar aviso público com acesso à política de privacidade.

Permitir configuração de URL específica por empresa.

Definir tratamento seguro para empresas sem política própria, distinguindo as responsabilidades da plataforma e da prestadora.

Não apresentar texto jurídico genérico como garantia automática de conformidade com a LGPD.

## 5. Limites e segurança

Homologar respostas HTTP 429 nas rotas públicas, verificando limites por contexto apropriado, recuperação após bloqueio e ausência de interferência indevida entre empresas.

Preservar proteções existentes.

## 6. Documentação e entrega

Atualizar a documentação do atendimento.

Apresentar:

- Arquivos modificados.
- Testes executados.
- Evidências de navegador.
- Resultado da concorrência simultânea.
- Resultado das falhas controladas.
- Pendências e riscos restantes.
- Necessidade de migrações.
- Situação de commit e deploy.

## Restrições

- Não integrar IA paga.
- Não alterar infraestrutura compartilhada.
- Não executar operações destrutivas.
- Não implantar na VPS sem autorização.
- Não fazer commit sem autorização.
- Não iniciar novos módulos comerciais.

**Critério de aceite:** funcionamento comprovado no navegador, ausência de conflitos de agenda sob concorrência real e comportamento seguro em falhas de persistência.
