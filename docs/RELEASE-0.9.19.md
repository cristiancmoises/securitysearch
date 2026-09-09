# SecuritySearch v0.9.19

## English

This release brings the completed v0.9.18 update into the published Codeberg history and adds release/publication tooling. The package starts from Codeberg commit `0751f14`, preserves the removal of `static/misc/lain.gifv`, and advances the asset marker from 17 in the previous published checkout to 23.

- Google stays the default; a failed new web/image search gets one labeled Brave fallback within a shared deadline. Existing pagination keeps its provider.
- GIF, animated WebP and APNG previews play when visible, with bounded loading and active playback, static posters and separate motion/scroll settings.
- Four small native icons sit inside the search bar's right edge. Tron remains the default, with saved theme choices respected.
- YouTube via Invidious, Pinterest via Binternet, six image views, quality/format choices, Reddit news and the plain Wiki/Git footer remain available.
- Both READMEs include the same real homepage screenshot, captured from securityops.co on 2026-09-09. It shows asset 22; this release keeps that interface and advances the package marker to 23.
- An annotated tag, complete source archive, checksums and a Git bundle make the exact update importable by fast-forward into the existing repository.
- A token publisher updates the four Git hosts, creates or resumes drafts, verifies release files and publishes only complete releases. Conflicting tags/assets are preserved and reported.

No upstream provider can guarantee every search. Brave image search has no continuation. Earlier Redlib checks returned 503; that service remains an external dependency. Docker/VPS deployment is a separate operation; the preserved updater uses SSH 5119 and bind 172.17.0.1:5140→80.

## Português do Brasil

Esta versão incorpora a atualização completa v0.9.18 ao histórico publicado no Codeberg e acrescenta ferramentas de release/publicação. Parte do commit `0751f14`, mantém a remoção de `static/misc/lain.gifv` e passa do asset 17 do checkout publicado anteriormente para o asset 23.

- Google continua padrão; uma falha na primeira busca web/imagens tenta Brave uma vez, com identificação visível e prazo compartilhado. A paginação mantém seu provedor.
- GIF, WebP animado e APNG reproduzem quando visíveis, com limites de carregamento/reprodução, posters estáticos e preferências separadas de animação/rolagem.
- Quatro ícones nativos pequenos ficam dentro da extremidade direita da barra. Tron permanece padrão, respeitando temas salvos.
- Permanecem YouTube pelo Invidious, Pinterest pelo Binternet, seis layouts, opções de qualidade/formato, notícias do Reddit e rodapé Wiki/Git sem caixa.
- Os dois READMEs incluem a mesma captura real da página inicial de securityops.co em 2026-09-09. Ela mostra o asset 22; esta versão mantém a interface e atualiza o marcador do pacote para 23.
- Tag anotada, fonte completo, checksums e bundle Git permitem importar a atualização exata por fast-forward no repositório existente.
- O publicador com tokens atualiza os quatro hosts Git, cria ou retoma rascunhos, verifica os arquivos e publica apenas releases completos. Tags/arquivos conflitantes são preservados e informados.

Provedores externos podem falhar. Brave imagens não oferece continuação. A verificação anterior do Redlib retornou 503; o serviço continua sendo uma dependência externa. O deploy Docker/VPS é separado; o atualizador preservado usa SSH 5119 e o bind 172.17.0.1:5140→80.
