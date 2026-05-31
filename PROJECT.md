# Prompt to Pattern — Project Plan & Build Specification

> **Document purpose:** This is an executable specification written for an AI coding agent (e.g. Claude Code) to read and implement, phase by phase. Each phase has explicit deliverables and acceptance criteria. Follow phases in order. Do not skip the acceptance check at the end of each phase.

---

## 1. Project Overview

### 1.1 What this plugin does

Prompt to Pattern is a WordPress plugin that lets an end-user, working inside the block editor, type a natural-language prompt and have an AI agent **compose a complete Page or FSE template by selecting and arranging block patterns that already exist** on the site — both WordPress core patterns and patterns registered by the active theme.

The user types something like *"A pricing page with a hero, a three-tier pricing table, an FAQ section, and a call to action."* The agent reads the available pattern library, picks the patterns that fit, orders them, optionally fills placeholder text, and produces editable block markup that the user reviews before it is applied.

### 1.2 Core design philosophy (non-negotiable)

1. **Pattern-first, not markup-first.** The agent's primary job is to *select and order existing patterns*, returning a structured list of pattern slugs — NOT to invent raw block HTML. This guarantees valid markup and on-brand design. Free-form markup generation is a fallback mode only, gated behind validation (see Phase 4).
2. **BYO key (Bring Your Own Key).** The site owner supplies their own AI provider credentials via the WordPress 7.0 Connectors screen. The plugin never proxies requests, never handles billing, never stores a shared key. This removes all server-operation and cost-liability burden.
3. **Provider-agnostic.** Never hardcode a provider. Call the core AI Client and let WordPress route to whatever provider the site has configured (OpenAI, Anthropic, Google, DeepSeek, local models, etc.).
4. **Design-system aware.** Before composing, the agent reads `theme.json` design tokens (colors, spacing, typography) and only references those tokens — never hardcoded values.
5. **Human-in-the-loop.** Generated output is always previewed and explicitly approved by the user before being written. Nothing is published automatically.

### 1.3 What this plugin explicitly does NOT do

- Does not run a proxy server or any external service owned by the plugin author.
- Does not implement a credit/billing system.
- Does not store or transmit a shared API key.
- Does not auto-publish content (drafts/preview only until user approves).
- Does not generate arbitrary executable code or `wp:html` blobs in the default mode.

---

## 2. Target Environment

- **WordPress:** 7.0 or later, REQUIRED. The plugin depends directly on the core AI Client, the Connectors API, and the Abilities API shipped/stabilized in 7.0. Do not add compatibility shims for 6.9 or earlier; declare `Requires at least: 7.0` and bail gracefully on older versions with an admin notice.
- **PHP:** 8.1+ (match WordPress 7.0 minimum; use typed properties, enums, readonly where useful).
- **Node:** Use the version pinned by `@wordpress/scripts` current release for the build toolchain.
- **Editor:** Block editor (Gutenberg) — both the post/page editor and the Site Editor (FSE).

---

## 3. Architecture

### 3.1 High-level flow (BYO, no proxy)

```
[ Block Editor (JS) ]
   prompt input UI (PluginSidebar / modal)
        │  REST request (nonce-protected)
        ▼
[ Plugin PHP — REST controller ]
   1. Verify capability + nonce
   2. Gather context:
        - registered block patterns (core + theme)
        - theme.json design tokens
        - target type (page content vs FSE template)
   3. Build the compose prompt
        │  via core AI Client (wp_ai_client_*)
        ▼
[ Core AI Client ]  ──►  configured provider (user's key)
        │  structured JSON response (ordered pattern slugs [+ optional content])
        ▼
[ Plugin PHP — composer ]
   4. Resolve slugs → pattern block markup
   5. Assemble final block markup
   6. (fallback mode only) validate any generated markup
        │  return markup to editor
        ▼
[ Block Editor (JS) ]
   preview → user approves → insert into page OR write template
```

### 3.2 Layers

| Layer | Responsibility | Key tech |
|---|---|---|
| **Editor UI** | Prompt input, mode toggle, preview, approve/insert | `@wordpress/plugins`, `@wordpress/components`, `@wordpress/block-editor`, `@wordpress/data` |
| **REST API** | Auth, orchestration, prompt assembly, response handling | `WP_REST_Controller`, nonce, capability checks |
| **Pattern context** | Read registered patterns + theme.json tokens | `WP_Block_Patterns_Registry`, `wp_get_global_settings()` |
| **AI invocation** | Provider-agnostic prompt call | core AI Client API |
| **Abilities** | Register "compose page from patterns" as an ability | Abilities API |
| **Composer** | Slug → markup resolution, assembly, validation | `do_blocks`, block parser |
| **Provider bridge** | Register DeepSeek + extensibility for others | Connectors API (`wp_connectors_init`) |

### 3.3 Two output targets (both supported)

The user (or the editing context) selects which target:

1. **Page content** — agent output becomes the `post_content` of a Page. Applied via editor block insertion; saved as a draft for review.
2. **FSE template / template part** — agent output becomes a block template (`templates/*.html`) or template part. Written through the Site Editor flow / template REST endpoints. Treat this as the higher-risk path: always preview in the Site Editor before committing, and never overwrite an existing template without explicit confirmation.

---

## 4. Core Features (by mode)

### 4.1 Mode A — Compose from existing patterns (DEFAULT, ship first)

The agent receives a catalog of available patterns (slug, title, category, short description, block types used) and the user prompt. It returns a structured JSON object: an ordered list of pattern slugs, optionally with per-pattern content overrides (e.g. suggested heading/body text that still fits the pattern's structure).

- Markup is guaranteed valid because patterns are author-written.
- The composer resolves each slug to its registered markup and concatenates in order.
- If the agent proposes a slug that does not exist, drop it and log; never fabricate markup in this mode.

### 4.2 Mode B — Generate a new pattern (FALLBACK, ship in V2)

Triggered only when no existing pattern reasonably satisfies a requested section. The agent generates new block markup, constrained hard:

- Must use only block types on an allowlist (core blocks + theme-supported).
- Must reference only `theme.json` token slugs for color/spacing/typography — no hardcoded hex/px.
- Output passes through a **validation layer**: parse with the block parser, reject on any invalid/unrecognized block, retry up to N times, then fall back to a safe placeholder + surface the issue to the user.
- Newly generated patterns are inserted as draft content for review; optionally offer to save as a reusable pattern (synced pattern / `wp_block`) — design storage for this in V3.

### 4.3 Shared behaviors

- **Preview before apply** for every generation, both modes.
- **Streaming/loading state** in the UI while the model responds.
- **Graceful failure**: if no provider is configured, show a clear CTA pointing the user to Settings → Connectors. If the call fails, show a friendly retry message, never a raw error or a broken page.
- **Token/length guardrails**: cap prompt size; truncate the pattern catalog intelligently (most relevant categories first) if it is large.

---

## 5. Provider Support (DeepSeek + extensibility)

### 5.1 Principle

Do NOT hardcode any provider. All model calls go through the core AI Client, which routes to whatever the site owner configured on Settings → Connectors. Core ships Anthropic, Google, and OpenAI as defaults; everything else is added as a connector.

### 5.2 DeepSeek

Two supported paths, document both for the user:

1. **Recommended:** instruct users to install the existing "AI Provider for DeepSeek" plugin, which auto-registers DeepSeek with the AI Client. Prompt to Pattern needs zero DeepSeek-specific code in this path.
2. **Bundled convenience (optional):** register a DeepSeek connector directly via the `wp_connectors_init` hook so DeepSeek appears on the Connectors screen out of the box. DeepSeek exposes an OpenAI-compatible API (`https://api.deepseek.com/v1`), so the connector is a metadata array with `authentication.method = api_key` and the DeepSeek base URL. Keep this behind a setting so it is opt-in and does not clutter sites that do not want it.

### 5.3 Extensibility for other providers

Expose a documented filter so third parties / the user can register additional connectors or override prompt-building for a specific provider. Provider selection logic must remain entirely inside the AI Client; the plugin only declares its needs (text generation, structured/JSON output, function calling if used).

---

## 6. Coding & Quality Standards (WordPress, 2026)

The agent MUST follow these throughout. Treat violations as build failures.

### 6.1 PHP

- Follow **WordPress Coding Standards (WPCS)**; run PHP_CodeSniffer with the `WordPress` ruleset and keep it clean.
- Namespacing: use a PHP namespace (e.g. `PromptToPattern`) for all classes; prefix global functions/hooks/options with `prompttopattern_` (or chosen slug) to avoid collisions.
- Security is mandatory and non-negotiable:
  - Verify a **nonce** on every REST/AJAX entry point.
  - Check **capabilities** (`current_user_can( 'edit_posts' )` or stricter for template writes) on every privileged action.
  - **Sanitize** all input (`sanitize_text_field`, `wp_kses_post`, etc.).
  - **Escape** all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`).
  - Never trust model output as safe HTML — run it through the block parser and `wp_kses`-equivalent validation before it touches a page.
- Use the WordPress HTTP layer / core AI Client for all external calls; never use raw `curl`.
- Internationalize every user-facing string with a single text domain (`__()`, `_e()`, `esc_html__()`), text domain matching the plugin slug.
- Register the Abilities API ability on the correct init hook; declare inputs, outputs, permission callback, and execute callback explicitly.

### 6.2 JavaScript / Editor

- Build with **`@wordpress/scripts`** (`wp-scripts`); no custom webpack unless justified.
- Use official `@wordpress/*` packages: `@wordpress/plugins`, `@wordpress/edit-post` / `@wordpress/editor`, `@wordpress/block-editor`, `@wordpress/components`, `@wordpress/data`, `@wordpress/api-fetch`, `@wordpress/i18n`.
- Use `apiFetch` with nonce middleware for REST calls; never embed keys or secrets in JS.
- Do NOT use browser `localStorage`/`sessionStorage` for anything sensitive; use the data store / transient REST state.
- All strings localized via `@wordpress/i18n`.

### 6.3 Block / metadata

- If registering any block, use `block.json` metadata (the canonical 2026 approach) and `register_block_type` pointing at the metadata file.
- Patterns the plugin itself may ship are registered via `register_block_pattern` with proper headers (slug, title, categories) and are export/translation-ready.

### 6.4 Accessibility & UI/UX

- Follow WordPress accessibility guidelines (WCAG-aligned): keyboard navigation, ARIA labels, focus management in the modal/sidebar, `prefers-reduced-motion` respected for any loading animation.
- Match the editor's native look: compose UI entirely from `@wordpress/components` (`Button`, `TextareaControl`, `Panel`, `Spinner`, `Notice`, `Modal`), do not ship a custom design language that fights the editor.
- No emojis in generated content or UI chrome.
- Clear empty/loading/error states. Preview must be visually distinct from applied content.

### 6.5 Repo hygiene

- `readme.txt` in the wordpress.org format with an **External Services** disclosure (the plugin sends prompt + site context to a third-party AI provider configured by the user) — required for review.
- `composer.json` / `package.json` with pinned versions.
- Linting config committed (`phpcs.xml`, ESLint via `@wordpress/scripts`).
- Unit/integration test scaffold (PHPUnit + `@wordpress/e2e-test-utils` or Playwright for editor flows).

---

## 7. Proposed File Structure

```
prompt-to-pattern/
├── prompt-to-pattern.php          # main plugin file: header, bootstrap, version guard
├── readme.txt                    # wp.org readme incl. External Services disclosure
├── composer.json
├── package.json
├── phpcs.xml
├── .editorconfig
├── includes/
│   ├── class-plugin.php          # bootstrap / DI container
│   ├── class-rest-controller.php # /compose, /patterns endpoints
│   ├── class-pattern-context.php # read patterns + theme.json tokens
│   ├── class-ai-service.php      # wraps core AI Client, builds prompts
│   ├── class-composer.php        # slug→markup resolve, assemble, validate
│   ├── class-ability.php         # Abilities API registration
│   └── class-connectors.php      # optional DeepSeek connector registration
├── src/                          # editor JS (built by wp-scripts)
│   ├── index.js                  # registerPlugin entry
│   ├── components/
│   │   ├── ComposePanel.js
│   │   ├── PromptInput.js
│   │   ├── ModeToggle.js
│   │   └── PreviewModal.js
│   ├── store/                    # @wordpress/data store
│   └── api.js                    # apiFetch wrappers
├── build/                        # compiled assets (gitignored)
├── skills/
│   └── compose/SKILL.md          # agent instructions for the compose step (see §9)
├── languages/
└── tests/
    ├── php/
    └── e2e/
```

---

## 8. Implementation Roadmap (phased)

> Build in order. Each phase ends with an acceptance check. Do not begin a phase before the previous one passes.

### Phase 0 — Scaffold
- Generate plugin skeleton, headers (`Requires at least: 7.0`, `Requires PHP: 8.1`), `wp-scripts` build, linting config, version guard with admin notice on unsupported WP.
- **Acceptance:** plugin activates cleanly on WP 7.0, deactivates cleanly, shows notice on < 7.0, `phpcs` and ESLint pass on the empty scaffold.

### Phase 1 — Pattern context + REST read endpoint
- Implement `Pattern_Context` to enumerate registered patterns (core + theme) and read `theme.json` tokens via `wp_get_global_settings()`.
- Add `GET /patterns` REST endpoint (nonce + capability protected) returning the catalog.
- **Acceptance:** endpoint returns a correct, sanitized catalog on a site with a block theme; unauthorized requests are rejected.

### Phase 2 — Editor UI (no AI yet)
- `registerPlugin` + `PluginSidebar`/modal with prompt input, mode toggle, target toggle (page vs template), and a stubbed preview that just echoes selected patterns.
- **Acceptance:** UI renders in both post editor and Site Editor, fully keyboard-navigable, matches editor styling, calls `/patterns` and lists them.

### Phase 3 — Mode A compose (core feature)
- Implement `AI_Service` (core AI Client wrapper, structured JSON prompt) and `Composer` (slug→markup→assembled output). Add `POST /compose`. Register the Abilities API ability. Wire preview → approve → insert into page content.
- **Acceptance:** a real prompt produces an ordered set of existing patterns assembled into valid, editable blocks; invalid slugs are dropped safely; works with at least two providers (e.g. OpenAI + DeepSeek) purely by switching the configured connector, with zero code change.

### Phase 4 — FSE template target + Mode B fallback
- Add template/template-part output path with Site Editor preview and explicit overwrite confirmation.
- Add Mode B generation with the validation layer (block-parse, allowlist, token-only styling, retry, safe fallback).
- **Acceptance:** can compose an FSE template; Mode B never emits invalid blocks (validation provably rejects bad output); no hardcoded color/spacing values appear in generated markup.

### Phase 5 — Provider polish + DeepSeek + extensibility
- Optional bundled DeepSeek connector (opt-in setting) via `wp_connectors_init`; documented filter for third-party providers/prompt overrides; graceful "no provider configured" CTA.
- **Acceptance:** DeepSeek selectable both via the external provider plugin and via the bundled connector; "no provider" state guides the user to Settings → Connectors.

### Phase 6 — Hardening & release prep
- Full security pass, i18n pass, accessibility audit, `readme.txt` with External Services disclosure, tests green, performance check on large pattern libraries.
- **Acceptance:** clean `phpcs`/ESLint, passing tests, a11y audit notes resolved, ready-to-submit package.

---

## 9. Agent Compose Instructions (SKILL.md / system prompt)

> This is the instruction block the plugin sends to the model (or stores as `skills/compose/SKILL.md`) when performing the compose step. Keep it version-controlled and adjustable via filter.

```markdown
# SKILL: Compose a WordPress page from existing block patterns

## Role
You select and arrange existing WordPress block patterns to fulfill a user's
page request. You are a layout composer, not a code generator.

## Inputs you receive
- `user_prompt`: natural-language description of the desired page.
- `available_patterns`: array of objects { slug, title, categories, description,
  block_types }. These are the ONLY patterns you may use in default mode.
- `design_tokens`: color, spacing, typography token slugs from theme.json.
- `target`: "page_content" or "fse_template".
- `allow_generation`: boolean. If false, you MUST only select existing patterns.

## Output contract (STRICT)
Return ONLY a JSON object, no prose, no markdown fences:
{
  "sections": [
    { "slug": "<existing pattern slug>", "content_overrides": { ... optional } }
  ],
  "notes": "<one short sentence on layout reasoning>",
  "unmet": [ "<requested section you could not satisfy>", ... ]
}

## Rules
1. Prefer existing patterns. Choose the smallest set that satisfies the request,
   ordered top-to-bottom as they should appear on the page.
2. Use a pattern slug ONLY if it appears in `available_patterns`. Never invent a slug.
3. If a requested section has no matching pattern:
   - if `allow_generation` is false: add it to `unmet`, do not fabricate.
   - if `allow_generation` is true: you MAY emit a "generated" section (see below).
4. `content_overrides` may suggest replacement text for headings/paragraphs that
   already exist in the pattern. Never introduce new block types via overrides.
5. Respect the page's purpose: a landing page leads with a hero and ends with a CTA;
   a content page leads with structure, not decoration. Keep it coherent.
6. Do not use emojis. Do not include explanatory prose outside the JSON.

## Generation mode (only if allow_generation = true)
A "generated" section looks like:
  { "generated": true, "markup": "<valid block markup>", "label": "<short name>" }
- Use ONLY core blocks plus block types listed as theme-supported.
- Reference ONLY token slugs from `design_tokens` for color/spacing/typography.
  Never write raw hex colors or pixel values.
- Produce valid block grammar (proper <!-- wp:* --> delimiters and JSON attributes).
- Markup you generate will be parsed and validated; invalid blocks are rejected.

## Self-check before responding
- Is every slug present in available_patterns? If not, remove or move to unmet.
- Is the output valid JSON with no surrounding text?
- Did I avoid inventing block types and hardcoded style values?
```

---

## 10. Open Decisions (track and resolve during build)

- Whether newly generated patterns (Mode B) are persisted as synced patterns (`wp_block`) for reuse, and where that storage lives — resolve before V3.
- How aggressively to truncate the pattern catalog when it is very large (relevance ranking vs. category filtering) — measure on real themes.
- Whether to expose a per-section "regenerate" action in the preview, or only whole-page regeneration.
- Localization scope for generated content (should the model match the site locale by default?).

---

## 11. Plugin Name

Final name: **Prompt to Pattern**. The name describes the core flow literally
(prompt in, pattern-composed page out) and reads well for search.

- Plugin slug / text domain: `prompt-to-pattern`
- PHP namespace: `PromptToPattern`
- Hook/option/function prefix: `prompttopattern_`
- Display name in UI and `readme.txt`: "Prompt to Pattern"