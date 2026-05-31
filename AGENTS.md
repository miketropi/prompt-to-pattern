# Prompt to Pattern — agent briefing

Use this file when changing code under `wp-content/plugins/prompt-to-pattern/`. Full architecture: [`PROJECT.md`](./PROJECT.md).

## What this plugin is

A WordPress plugin that lets users compose pages and FSE templates from existing block patterns using natural-language prompts and AI. The agent reads the pattern library, picks matching patterns, orders them, and returns assembled block markup for user approval.

**Stack:** PHP 8.1+, `@wordpress/scripts` build, React editor UI via `@wordpress/*` packages, core AI Client (`wp_ai_client_prompt`) for provider-agnostic AI calls.

## Naming

| Context | Value |
|---------|--------|
| Plugin folder / text domain | `prompt-to-pattern` |
| Main file | `prompt-to-pattern.php` |
| PHP namespace | `PromptToPattern` |
| PHP constants / hooks / options | `prompttopattern_*` |
| REST namespace | `prompt-to-pattern/v1` |
| Script handle | `prompt-to-pattern-editor` |
| Store name | `prompt-to-pattern` |
| Ability name | `prompt-to-pattern/compose-page` |
| Skill file | `skills/compose/SKILL.md` |

## Architecture overview

```
prompt-to-pattern.php                Entry point, constants, version guard, bootstrap
includes/class-plugin.php            Singleton bootstrapper, assets, REST/abilities/connectors hookup
includes/class-rest-controller.php   GET /patterns (read catalog), POST /compose (compose flow)
includes/class-pattern-context.php   Reads WP_Block_Patterns_Registry + theme.json tokens
includes/class-ai-service.php        Wraps wp_ai_client_prompt(), builds prompts, parses JSON
includes/class-composer.php          Slug→markup resolution, assembly, content overrides
includes/class-block-validator.php   Mode B validation (block-parse, allowlist, token-only styles)
includes/class-ability.php           Abilities API: prompt-to-pattern/compose-page registration
includes/class-connectors.php        Opt-in DeepSeek connector + prompttopattern_system_instruction filter
includes/class-admin-settings.php    Settings → Prompt to Pattern admin page
src/                                  Editor React app (wp-scripts → build/)
skills/compose/SKILL.md               Agent compose instructions sent to the AI model
```

### Data flow

1. **Editor:** User types prompt in sidebar → JS dispatches via `apiFetch` to REST.
2. **REST:** `GET /patterns` returns sanitized catalog (patterns + design tokens).
3. **Compose:** `POST /compose` builds prompt → calls core AI Client → parses structured JSON → resolves slugs to block markup → returns assembled output.
4. **Preview:** JS renders result in modal; user reviews before inserting into editor.

### Two modes

- **Mode A (default):** AI selects existing patterns by slug only. Invalid slugs are dropped; never fabricates markup.
- **Mode B (fallback, V2):** AI may generate new block markup. Passes through `Block_Validator` — must use allowed blocks, must reference design-token slugs only (no hardcoded hex/px), rejected on violation with safe placeholder fallback.

## Files you usually touch

| Area | Edit (source) | Do not edit directly |
|------|---------------|----------------------|
| Editor UI | `src/**/*.js` | `build/index.js`, `build/index.asset.php` |
| PHP behavior | `includes/**/*.php`, `prompt-to-pattern.php` | — |
| AI instructions | `skills/compose/SKILL.md` | — |

After changing `src/`, run **`npm run build`** (or **`npm run start`** for watch).

## REST API

Namespace: `prompt-to-pattern/v1`

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/patterns` | Returns `{ patterns: [], design_tokens: {} }` |
| POST | `/compose` | Body: `{ prompt, target?, allow_generation? }` → `{ markup, sections, unmet, notes }` |

Permission: `edit_posts`. Nonce required.

## AI Client integration

All model calls go through `wp_ai_client_prompt()` → `using_system_instruction()` → `with_text()` → `as_json_response()` → `generate_text()`. Provider selection is entirely handled by WordPress core's Connectors screen; the plugin declares no provider preference.

The compose prompt sends:
- System instruction (SKILL.md)
- User's natural-language request
- Available patterns catalog (slug, title, categories, description, block_types)
- Design tokens (color, font, spacing slugs from theme.json)
- Target type and generation mode

## Provider support

- **Any provider** configured via Settings → Connectors works out of the box (OpenAI, Anthropic, Google).
- **DeepSeek:** Recommended path is the "AI Provider for DeepSeek" plugin. Optional bundled connector available via Settings → Prompt to Pattern (opt-in, stores key only; provider registration handled by the external plugin).
- **Extensibility:** `prompttopattern_system_instruction` filter for overriding the SKILL.md instructions per provider.

## Commands

```bash
npm run start       # Watch editor assets
npm run build       # Production build → build/
npm run lint:js     # ESLint + Prettier
npm run lint:js:fix # Auto-fix
```

## Conventions

- **PHP:** Follow WordPress Coding Standards (WPCS). No yoda conditions. All strings internationalized with text domain `prompt-to-pattern`.
- **JS:** Use `@wordpress/*` packages exclusively. All strings via `@wordpress/i18n`. No localStorage for sensitive data.
- **Security:** Nonce + capability on every REST endpoint. All input sanitized. All output escaped. AI output never trusted — parsed as JSON, validated through block parser.
- **No proxy, no shared key.** The plugin never handles or stores API keys. Site owner configures their provider via Settings → Connectors.

## Key dependencies

- **WordPress 7.0+** (core AI Client, Connectors API, Abilities API)
- **PHP 8.1+** (typed properties, enums, readonly)
- **No external PHP libraries** — only WordPress core APIs
