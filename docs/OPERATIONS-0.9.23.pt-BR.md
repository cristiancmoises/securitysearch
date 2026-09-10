# v0.9.23 — operação e publicação

Aplicação 0.9.23, assets 27. Use o kit correspondente em um checkout main limpo e
exato da v0.9.22-r2, r1 ou original. Alterações desconhecidas são preservadas.

```fish
cd ~/Downloads
and sha256sum -c securitysearch-update-0.9.23.tar.gz.sha256
and tar -xzf securitysearch-update-0.9.23.tar.gz
and fish ~/Downloads/securitysearch-update-0.9.23/apply-securitysearch.fish ~/securitysearch
and fish ~/Downloads/securitysearch-update-0.9.23/deploy-securitysearch.fish \
    ~/securitysearch \
    --theme-assets ~/.local/share/securitysearch/operator-themes-v1 \
    --rank-refresh
```

Reutilize o pacote de temas já preparado fora do Git. Sem `--theme-assets`, o
resultado usa as alternativas públicas de paleta/Matrix. O destino continua
root@securityops.co, SSH 5119, Docker 172.17.0.1:5140 -> 80 e redes existentes;
o NPM não é reconfigurado. Preserve todos os backups, inclusive seus snapshots.

## Notícias verificadas antes da troca

libre.securityops.co sai do roteamento ativo. O padrão compilado é
redlib.privacyredirect.com; nadeko.net e privadency.com completam o conjunto fixo
(com os hosts completos redlib.nadeko.net e redlib.privadency.com). A lista oficial
foi consultada, mas pertencer à lista não comprova disponibilidade.

Depois da auditoria offline e da inicialização do candidato, uma verificação real
usa o adaptador e o parser atuais na rede da VPS. A MESMA instância deve fornecer
itens reconhecidos tanto no feed news+worldnews quanto numa busca neutra por
technology. Uma página inicial, desafio HTTP 200, feed vazio ou apenas feed
funcionando não basta. A origem aprovada fica em FOURGET_REDLIB_PRIMARY no novo
contêiner e é confirmada após a troca. Links e avisos acompanham essa configuração.

Se nenhuma passar, o contêiner atual não é parado. Veja redlib-live.json no backup
exibido; ele contém origens, contagens, duração e classe de erro, sem conteúdo,
credenciais ou buscas pessoais. O processo tem limite de 40 segundos; DNS síncrono
não fica coberto somente pelo timeout do cURL. A primeira resolução pode demorar.
A disponibilidade não foi comprovada no ambiente de autoria: o teste da VPS é
obrigatório. Permanecem o teste real do Binternet e as proteções de rollback.
Consultas não são enviadas simultaneamente para todas as instâncias. Paginação
mantém sua origem; links antigos de uma origem removida precisam ser reiniciados.

## Minha imagem

Na página inicial, clique **Use a picture from this device** e depois **Choose File**.
O primeiro botão apenas salva a preferência Custom. O editor abre imediatamente,
antes dos temas; o arquivo escolhido é aplicado sem outro botão de salvar.
Configurações também exibem o editor com esse tema selecionado. JavaScript local
é opcional para o restante do site, mas necessário para essa funcionalidade.

JPEG, PNG, WebP e GIF são reconhecidos pelos bytes, mesmo sem MIME informado pelo
sistema. GIF vira um quadro estático. Limites: entrada de 16 MiB, 48 megapixels
após decodificação, lado maior de 1920 px e URL normalizada de até 2 MiB. HEIC/HEIF
e SVG não são aceitos; exporte-os como JPEG/PNG. A decodificação do navegador pode
alocar memória antes da validação de dimensões.

Não há envio do arquivo, nome ou metadados por formulário, cookie ou requisição.
A imagem reprocessada fica na sessão da aba por padrão; restauração de sessão do
navegador pode recuperá-la. **Remember on this device** permite armazenamento local
persistente. **Remove my picture** limpa ambas as opções quando permitido. Se o
armazenamento estiver bloqueado ou cheio, a imagem ainda funciona na página atual,
com aviso. Isso não é um cofre criptografado: o navegador compartilhado e scripts
da mesma origem podem ler os dados. Falhas ao limpar armazenamento são informadas.
Sem JavaScript, o seletor fica desabilitado e explica o motivo; buscas comuns e
temas incluídos continuam funcionando.

## Release e retomada de um único host

```fish
fish ~/Downloads/securitysearch-update-0.9.23/publish-securitysearch.fish ~/securitysearch
```

Cria/reutiliza a tag anotada v0.9.23 e os pacotes do commit real exato; os arquivos
ficam em ~/securitysearch-release-v0.9.23/. A release recebe tar.gz de fonte PHP e
checksum, não uma imagem Docker. O bundle incremental local depende da v0.9.20.
Tokens são solicitados de forma privada. Tags, notas e anexos conflitantes não
são substituídos. As imagens privadas Lain/SecOps e derivados continuam fora dos
commits novos e anexos dos quatro hosts, inclusive Codeberg; reutilize o pacote
somente no deploy privado. Histórico antigo não é reescrito.

```fish
fish ~/Downloads/securitysearch-update-0.9.23/publish-securitysearch.fish \
    ~/securitysearch --host git.securityops.co
```

Esse comando retoma a release e anexos somente no host indicado. Publicar não faz
deploy. O comando audit-providers.fish do kit executa uma amostra real separada;
saída 2 indica provedor vazio/indisponível. Consulte AUDIT.md do kit para os testes
executados e limitações, sem transformar mocks ou bloqueios em aprovação nativa.
