# Search-first interface

Security Search v0.9.3 uses a deliberately minimal landing page. The logo and
search field are the primary visual anchors; navigation and SecurityOps links
remain available without competing with the search task.

## Landing-page hierarchy

The first page presents these elements in order:

1. A single Settings utility action.
2. The Security Search logo.
3. The primary search field and submit action. Searches use Google by default.
4. One compact privacy/provider hint: Google is the default and Brave is
   available from the Scraper picker.
5. Two understated external links: [securityops.co](https://securityops.co/)
   and [securityops.com.br](https://securityops.com.br/).

The portal links are quiet text links, not a button wall or card grid. Additional
services stay in the low-priority expandable directory and footer rather than
competing with the logo or search field in the hero.

## Theme behavior

SecOps is the default theme for a visitor who has not chosen a theme. Theme
selection remains a browser preference:

- A valid saved theme cookie takes precedence over the configured default.
- An absent or invalid theme cookie falls back to SecOps.
- Changing the default must not overwrite or delete a user's valid selection.
- Theme changes must retain readable contrast, visible keyboard focus, and
  usable search controls on both the home and results pages.

Container deployments set `FOURGET_DEFAULT_THEME=SecOps`; the generated
`config::DEFAULT_THEME` fallback must use the same value. Keep these defaults
aligned when changing deployment configuration.

## Responsive and accessible behavior

- Keep the logo and search form centered and visible without requiring the user
  to pass through promotional controls.
- Support widths from 320px through desktop without horizontal page overflow.
- Allow the search field and submit control to reflow cleanly on narrow screens.
- Keep interactive controls large enough for touch and expose visible
  `:focus-visible` states for keyboard users.
- Do not rely on JavaScript for search, Settings, theme selection, or portal
  navigation.
- Avoid autofocus that unexpectedly opens a mobile keyboard or shifts the page.
- Give external links descriptive accessible names and an appropriate `rel`
  value when they open a new browsing context.

## Image-result flow

Automatic loading is enabled by default for image results, but remains a
progressive enhancement. Settings lets the user turn it off with **Load more
image results automatically while scrolling: No**. The server-rendered **Next
page** link must remain in the page and work when the preference is disabled,
JavaScript is unavailable, or the browser lacks the required APIs. Status
messages use a polite live region; automatic retries stop after an error and
replace the consumed continuation URL with a clearly labelled first-page
restart that preserves the query and filters.

## Provider messaging

The landing hint communicates availability without becoming another control:
Google is the default web and image provider; Brave is a selectable alternative
for both. Provider configuration, precedence, and operational limitations are
documented in [PROVIDERS.md](PROVIDERS.md).

## Release checks

Before release, verify the home page at representative phone, tablet, and
desktop widths; keyboard traversal; contrast; valid-theme-cookie preservation;
the SecOps fallback; and successful Google-default web and image searches.
Also select Brave for web and image searches and confirm that upstream
anti-bot responses are presented as provider errors rather than application
failures. Test image auto-loading, its Settings opt-out, and the ordinary
**Next page** fallback with JavaScript disabled.
