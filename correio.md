# VETORSMART-WILDCARD-01.4 — Validação DNS e SSL

**Ambiente:** VPS Hostinger KVM 4  
**Diretório:** `gateway/vetorsmart/`

## 1. Verificar DNS

Consultar servidores autoritativos e resolvedores públicos para:

- `vetorsmart.com.br`
- `www.vetorsmart.com.br`
- `teste.vetorsmart.com.br`

Confirmar resolução para `2.25.235.159`.

Verificar também eventuais registros AAAA conflitantes.

Se houver divergências, registrar os resultados e interromper antes de alterar certificados ou aplicações.

## 2. Sincronizar Git

Preservar os arquivos locais `correio.md` e `executed.md`.

Sincronizar os commits pendentes do VetorSmart sem alterar outros projetos.

Executar os testes automatizados e validar o build.

## 3. Preparar certificado SSL

Verificar a configuração atual do Certbot e do Nginx.

Validar o hook DNS-01 da Hostinger e a disponibilidade do token de API.

Não emitir certificado até que o desafio DNS-01 esteja validado.

Após a emissão, testar a renovação automática.

## 4. Homologação

Verificar:

- HTTPS no domínio principal.
- HTTPS em subdomínio de teste.
- Isolamento entre tenants.
- Funcionamento de serviços, chat e agendamento.
- Continuidade operacional dos demais SaaS.

Ativar `PLATFORM_SUBDOMAIN_SITES=true` somente após aprovação completa.

## 5. Relatório

Registrar em `docs/VETORSMART-WILDCARD-01.4.md` os resultados, alterações, testes, bloqueios e parecer final.

Não realizar alterações destrutivas nem modificar sistemas fora do escopo.
