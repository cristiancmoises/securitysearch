# Audit scope: v0.9.34

Run `sh scripts/test.sh --keep-going` in the native candidate audit image. All 93 previous
commands remain in order; favicon cache tests, actual HTTP tests, release contracts and
0.9.34 package tests add four commands (97 total).

New checks exercise real core-PHP filesystem operations and a real local PHP HTTP server.
The HTTP fixture uses a network tripwire in place of proxy delivery; a valid cache hit must
not load that tripwire, and invalid cache entries must fall through to its controlled miss.
This proves boundary behavior, not live remote favicon discovery or actual upstream availability.

Validate both an exported source layout without .git and a startup-prepared layout with a
synthetic private-theme fixture. Include negative mutation checks for validator parsing,
symlink rejection and transport initialization order. Test cumulative operators with real
Git and archive files while clearly labelling SSH/Docker/API doubles. The kit VALIDATION.txt
and raw logs document the results actually executed; unavailable extensions are not passes.

Native Docker/FPM and live RSS/Binternet/Google Web+Images acceptance on the VPS remain required.
Do not claim the preparation environment passed a native audit if dependencies are missing.
