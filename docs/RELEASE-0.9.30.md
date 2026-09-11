# SecuritySearch v0.9.30

Application **0.9.30** · asset **34**.

## English

v0.9.30 separates Apache connection/static-file concurrency from PHP execution by making
Apache event MPM + PHP-FPM the verified candidate runtime. PHP remains capped at sixteen
children, matching the previous PHP concurrency ceiling, while event workers can handle
static and keep-alive work separately. The prior prefork/mod_php runtime remains an explicit
operator fallback but is never selected silently during a normal deployment.

Docker health now exercises a PHP-backed route as well as the anonymous static homepage.
Candidate readiness additionally verifies event MPM, absence of mod_php and a live FPM pid
before any production cutover.

Cold result favicons are bounded so decorative work cannot monopolize PHP capacity: at most
four remote favicon refreshes run concurrently when APCu is available, each with a 2.5-second
shared budget. Duplicate-host work and recent failures are briefly suppressed; successful
cached favicons and the placeholder gain bounded browser caching. Existing favicon discovery,
validation and fallback functionality is retained.

Google/CSE, Brave search/result behavior, Binternet, RSS news, local pictures, animated
previews, themes, external-Redlib ownership notices, anonymous-home delivery and private
operator-artwork policy are otherwise preserved.

This release does not claim to beat 4get.ca. The competitive benchmark remains an explicit
operator action after deployment.

## Português do Brasil

A v0.9.30 separa a concorrência de conexões/arquivos estáticos do Apache da execução PHP,
utilizando Apache event MPM + PHP-FPM no candidato verificado. O PHP continua limitado a
16 processos filhos, igual ao teto de concorrência PHP anterior, enquanto workers event
podem atender arquivos estáticos e keep-alive separadamente. O runtime antigo
prefork/mod_php permanece como fallback explícito do operador, sem downgrade silencioso.

O healthcheck passa a verificar também uma rota PHP. A prontidão do candidato confirma
event MPM, ausência do mod_php e processo FPM ativo antes da troca de produção.

Favicons frios dos resultados recebem limites para não monopolizar capacidade PHP: no máximo
quatro atualizações remotas simultâneas quando APCu está disponível, cada uma dentro de um
orçamento total de 2,5 segundos. Requisições duplicadas do mesmo host e falhas recentes são
suprimidas temporariamente; ícones em cache e o placeholder recebem cache de navegador
limitado. Descoberta, validação e fallbacks existentes continuam funcionais.

Google/CSE, Brave, Binternet, notícias RSS, My Picture, animações, temas, aviso de operação
independente do Redlib, homepage rápida e política dos assets privados permanecem.

Esta versão não afirma ser mais rápida que 4get.ca. O benchmark competitivo continua sendo
uma ação manual após o deploy.
