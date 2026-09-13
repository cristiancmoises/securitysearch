# Operações v0.9.42

As instruções completas estão no [README em português](../README.pt-BR.md).
SSH root@securityops.co:5119. Uma execução normal prepara commit, envia fonte, compila, executa
130 testes obrigatórios, valida os provedores, promove e verifica o serviço e só então remove
objetos antigos reconhecidos. Não precisa rodar --audit-only antes. Falha de auditoria ou provedor
mantém a produção e não dispara retenção. A remoção do rollback antigo após sucesso é intencional.
Publique apenas após DEPLOY COMPLETE com securitysearch-v0.9.42-publish-four-remotes.fish.
