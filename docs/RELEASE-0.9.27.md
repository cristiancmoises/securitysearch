# SecuritySearch v0.9.27

Application 0.9.27 · asset31. A renderer-only performance release; upstream search
behavior and the complete existing acceptance gates are preserved.

## English

Fixed public templates, homepage CSS and public theme metadata are compiled before
Apache starts. Existing OPcache can retain those unrendered literals instead of
repeating filesystem discovery and source preparation on visitor requests. The
homepage uses a small shared page renderer rather than loading the full search
results class. Search pages retain the same frontend API; unused shell fragments
are not assembled for partial templates. No visitor HTML, searches or pictures
enter a shared response cache. Rendered page content and layout remain unchanged.

All themes, browser-local pictures, image animations, Google/CSE and Brave fixes,
Binternet, RSS news, onion/Tranco information, third-party Redlib notices and private
Lain/SecOps artwork boundaries remain. No NPM, TLS, rate-limit, timeout or worker-count
change is made. A safe dynamic path handles disabled or unavailable compiled assets.

A read-only origin diagnostic distinguishes PHP processing from localhost transfer
and compression. It performs no search and no deployment. The supplied competitive
benchmark remains an optional manual script: it is not run or added to audit tests,
and no winner/result table is published. Nothing here proves a global speed ranking.

The source package and checksum are built from the new immutable v0.9.27 tag. Prior
tags, release assets, working changes and backups remain untouched. Private operator
artwork and generated resource bundles do not enter public Git/source releases.

## Português do Brasil

Os templates públicos fixos, CSS da página inicial e metadados dos temas são
compilados antes de iniciar o Apache. O OPcache existente pode reutilizar esses
textos ainda não renderizados, reduzindo leituras e preparação repetidas. A página
inicial usa um renderizador menor, sem carregar a classe completa de resultados.
Nenhuma página personalizada, consulta ou imagem do usuário entra em cache de
respostas compartilhado. Conteúdo, formulários, temas e layout são preservados.

São mantidos Google/CSE, Brave, Binternet, RSS, imagens locais, animações, o aviso de
Redlib independente e a separação das imagens privadas Lain/SecOps. Não se alteram
NPM, TLS, limites, timeouts ou quantidade de workers. Uma falha de compilação usa o
caminho dinâmico funcional. Um diagnóstico opcional somente de leitura observa a
origem local, sem buscas ou reinícios. O benchmark comparativo fica manual; não foi
executado nem incluído na auditoria. Não há promessa de superar serviços em todas
as redes. A publicação usa uma nova tag v0.9.27 e preserva versões anteriores.
