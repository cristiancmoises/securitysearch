# v0.9.24-r1 — news audit and minimal-runtime repair

Application **0.9.24**, asset marker **28**. This revision fixes two defects found
while deploying the RSS news update. It does not restore the failed Redlib default,
relax decoder visibility, disable a test, or bypass a live news/Binternet gate.

## Reported failures

The supplied audit summary has 53 successful commands and two failures:
`tests/reddit-regression.php` and `tests/news-http-policy-regression.php`.

The Reddit test reused `$provider` for its default-provider assertion. With News
RSS as the default, that variable became a `newswire` object. Later assertions
then called its protected `decode()` instead of the original Reddit decoder.
The assertion now uses a separate `$default_provider`; an explicit identity check
keeps the Reddit fixture intact. The success banner moved after ALL assertions.
Production decoder visibility and provider selection are unchanged.

The RSS policy called `ctype_digit()` for every response's Retry-After value,
including an absent header. The image recipe does not install Alpine's separate
ctype extension. An actual `php -n` run reproduces the undefined-function fatal.
The supplied terminal excerpt omitted this second exception's message, so this
reproduction is not a claim to have read the complete remote log. The code now
uses core PCRE to accept only a nonempty sequence of ASCII digits. Empty strings,
signs, whitespace, fractions and non-ASCII digits remain rejected. Numeric and
HTTP-date delays retain the same 80-byte input and 3,600-second output bounds.
No extra production package, extension substitute or php.ini change is required.

## Diagnostics and coverage

The Docker audit shell merges stderr into stdout before executing the unchanged
keep-going runner. This prevents Docker's separate output streams from placing a
failure marker below the next test heading in the excerpt. Full logs are retained;
nonzero process OR container exit still blocks cutover.

A new mandatory runtime regression runs the complete response-policy suite in a
child PHP process with no php.ini and `ctype_digit` unavailable. It also executes
the actual default-selection assertion, preserves object identity and protected
visibility, checks numeric/date/refusal cases, and exercises the merged shell
stream. All 55 previous commands remain in the same order and with the same
arguments. The new command brings the total to 56.

Release-package metadata is corrected to asset marker 28 (the previous package
manifest literal still said 26). Package tests now assert that field explicitly.
The application version and tag name do not change.

## Apply and deploy

Use `securitysearch-update-0.9.24-r1` on the exact clean, already-applied v0.9.24
checkout. The matching helper verifies the source, applies this repair and makes a
normal local commit. Repeating the exact repaired state creates no extra commit.
Conflicting work or an existing different tag is preserved, not reset or moved.

Reuse the external operator-theme pack with `--theme-assets`; never put it in Git
or forge releases. Preserve `/root/securitysearch-backups/20260910T192605Z` and
other backup/rollback paths. The full isolated native audit, candidate readiness,
real fresh RSS feed AND keyword search, live Binternet gate and replacement
verification remain required. Local fixture results are not live-source approval.

## Português do Brasil

O teste Reddit sobrescrevia seu objeto ao verificar o novo provedor padrão RSS.
Agora usa uma variável separada e só mostra sucesso após todas as verificações.
O tratamento de Retry-After deixa de depender de `ctype_digit`, ausente na receita
Alpine, e usa PCRE com os mesmos limites e validação ASCII. A auditoria une as
saídas antes da execução para não misturar mensagens entre os testes. Todos os
55 comandos anteriores continuam obrigatórios, mais uma nova regressão. O campo
de assets do manifesto do pacote passa de 26 para o valor correto 28.
Use o kit r1 correspondente, mantenha os temas privados fora do Git e preserve os
backups. Nenhum teste, timeout, proteção TLS ou critério de implantação é ignorado.
