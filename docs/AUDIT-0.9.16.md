# v0.9.16 — compact controls and Tron validation

Prepared 2026-09-09. Based on v0.9.15 commit `cc84a630c37c4263e0c273faa5c5559e24a79951`. This is a focused UI/default-theme revision; the earlier [full source audit](AUDIT-0.9.15.md) and its limitations remain applicable.

## Changes reviewed

- One shared `search-actions.html` component supplies the homepage and result-header icons. Native destinations, their order and complete accessible names remain. SVGs are decorative for assistive technology and require no separate request or JavaScript.
- The action column shrinks from 168 px to 36 px on a normal desktop root size. Buttons are 36 px square, with 44 px targets on coarse pointers. Small labels appear on hover or keyboard focus. Focus rings remain visible; native form submission is unchanged.
- Tron is the source and Compose default. The updater explicitly migrates the preserved instance default to Tron, rather than carrying forward old Lain configuration. It still preserves other effective settings and the rollback container. Explicit browser theme preferences remain valid.
- Candidate readiness checks asset marker 20, the effective Tron setting and rendered Tron stylesheet, in addition to the previous no-JavaScript/CSP/health/frame checks.
- Tron's default background is CSS-only. The existing `tron.gif` is 12,261,897 bytes and no longer appears in that stylesheet. The missing Christmas-hat decoration is removed; normal body/link/code text no longer has persistent glow.

A separate read-only review identified the old-theme migration trap and the large/broken decorative assets before implementation.

## Executed checks

The established `sh scripts/test.sh` suite passes: nine PHP regression suites, stateful Docker deployment simulations, real localhost HTTP controller tests and synthetic parser/template benchmarks. Existing checks were extended for accessible icon names, decorative SVG semantics, shared native routing, generated Tron configuration, saved Lain preference, old-default migration and candidate theme verification.

The HTTP suite retains its Play/Pause, date filter, concurrency, finite-transition and provider-error coverage. It uses an isolated fixture provider and sends no external search traffic. Homepage testing returned **50/50 successful responses**, median **1.89 ms**, p95 **6.31 ms**, with four clients and four local PHP workers. This measures loopback PHP responses; it excludes browser painting, media, provider searches, NPM/TLS and VPS conditions. It is not a controlled before/after speed comparison.

Edited CSS parses, including nested media rules. Fish syntax checks pass. Source/archive checks require the shared component, Tron stylesheet, asset marker 20 and matching deployment script; browser JavaScript and private-key directories remain excluded.

## Limits

Browser/device visual acceptance and tooltip/touch behavior were not tested in a browser. This is not an accessibility certification. Actual Docker build, live VPS deployment and provider availability remain unverified here. Existing upstream restrictions and the no-JavaScript timed-page alternative are unchanged.

The new default does not erase a saved browser theme. Use Settings → Theme → Tron to change an explicit previous choice. The supplied updater changes only the instance default; rollback restores the old container's original configuration.
