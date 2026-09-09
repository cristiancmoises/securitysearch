# SecuritySearch 0.9.20 — operação

O kit é um patch para o fonte v0.9.19 (asset 23), não um histórico Git substituto. A base do Codeberg não pôde ser obtida no ambiente de autoria. Os blobs essenciais do arquivo anterior foram conferidos com o mirror GitHub. O manifesto verifica cada arquivo alterado antes e depois do patch.

```fish
fish ./apply-securitysearch.fish ~/securitysearch
fish ./deploy-securitysearch.fish ~/securitysearch
fish ./push-securitysearch.fish ~/securitysearch
```

A primeira etapa exige `main` limpo e identidade Git configurada. Cria um commit somente com o patch. Arquivos modificados, diferenças na base e arquivos não rastreados fazem o script parar: não há reset, stash automático nem descarte de trabalho do Codex. Falha de hook/assinatura deixa o patch no índice e é informada. Não force a aplicação apagando trabalho local.

O deploy gera um arquivo do commit verificado, calcula SHA-256 e envia por SSH **5119** para **root@securityops.co**, sem desativar verificação de chave do servidor. Na VPS, exige Python 3, Docker, curl e flock. Preserva a instalação conhecida: contêiner `security-search`, porta **172.17.0.1:5140 → 80**, redes, opções de execução e dados privados compatíveis. Montagens sobre o código/configuração, IP estático ou porta inesperada são recusados. Nginx Proxy Manager não é alterado.

O serviço antigo permanece ligado durante o build. Uma imagem descartável de auditoria instala Python/Node/Git/fish, sem modificar a imagem de produção. A suíte completa é executada sem rede, sem volumes privados e sem o ambiente do serviço atual. Qualquer erro impede a troca. O log fica no diretório privado de backup.

Depois, um candidato não publicado passa pelas verificações de versão, tema preto, assets e CSP. Ele executa uma busca neutra `teste` no Binternet usando a rede da VPS; a continuação é verificada quando existe. O JSON registra contagem, tempo, estado e classe de erro, não resultados ou tokens. Se o provedor continuar indisponível, a implantação para antes de desligar o serviço antigo.

A troca causa uma interrupção curta. Falha de prontidão do substituto aciona restauração do contêiner anterior; se a recuperação também falhar, o caminho exato do rollback é informado. O teste da porta interna não substitui conferir a página pública, DNS, TLS e o encaminhamento no NPM.

**Não apague o diretório de backup informado.** A versão nova pode montar snapshots privados desse local. O rollback também depende do contêiner/imagem retidos. Execute exatamente o caminho exibido em `Rollback: bash .../rollback.sh`; não escolha um backup por suposição. Não há comando de prune no kit.

O envio aos quatro repositórios pede os tokens separadamente, em terminal interativo. Não salva tokens em arquivos, URLs, argumentos ou repositório. O askpass temporário só responde ao host HTTPS, proprietário e repositório previstos. Credenciais continuam presentes no ambiente dos processos Git enquanto usados: execute em uma máquina confiável e sem wrappers de rastreamento.

Os quatro repositórios e branches `main` devem existir. São verificados antes do envio. Não há force push, criação de repositórios, merge automático, movimento de tags nem criação de releases. Remotes locais não são modificados. Falha no preflight não envia nada; falha parcial posterior preserva os envios concluídos e é informada. Repetir o comando reconcilia o estado, sem prometer atomicidade entre servidores.

Para conferir provedores reais depois do deploy:

```fish
fish ./audit-providers.fish
```

O script envia uma busca neutra por combinação de provedor/página, sequencialmente, e copia o JSON para o diretório do kit. Pode levar alguns minutos. Vazio, indisponível, bloqueado ou sem configuração não é contado como busca bem-sucedida; o código de saída será 2 se nem todas tiverem resultados. Uma amostra por provedor não mede p50/p95 e não comprova ganho de velocidade.

Os novos ajustes de operador são `FOURGET_PROVIDER_CONNECT_TIMEOUT_MS=3000`, `FOURGET_PROVIDER_TIMEOUT_MS=12000` e `FOURGET_PROVIDER_TOTAL_TIMEOUT_MS=20000`. Aplicam-se ao helper legado; CSE e Brave mantêm limites próprios. Reduzir limites pode descartar respostas lentas válidas. Não foi adicionado cache compartilhado de buscas nem loop de tentativas em segundo plano.

A origem Binternet continua `images.securityops.co`; `img.securityops.co` da navegação não é substituído automaticamente. Uma falha no teste real exige investigar DNS, NPM e a resposta do Pinterest nessa origem. O parser não corrige bloqueios 403/429 ou indisponibilidade externa.

Quem já tiver um tema salvo continuará vendo esse tema. Use **Choose appearance → Pure black → Save appearance** para mudar o navegador atual. As miniaturas são locais e estáticas; escolher um wallpaper animado carrega o arquivo completo existente por opção do usuário.
