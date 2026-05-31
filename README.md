# Prompt to Pattern

Compose WordPress pages and FSE templates from existing block patterns using natural-language prompts and AI.

## Quick start

1. Install and activate the plugin.
2. Configure an AI provider at **Settings → Connectors**.
3. Open any page/post in the block editor.
4. Click the **Prompt to Pattern** sidebar icon.
5. Type a description (e.g. *"A pricing page with hero, pricing table, FAQ, and CTA"*).
6. Click **Compose**, review the preview, and approve.

## Requirements

- WordPress 7.0 or later
- PHP 8.1 or later
- An AI provider configured via Settings → Connectors (OpenAI, Anthropic, Google, DeepSeek, or any registered provider)

## Modes

- **Pattern mode (default):** AI selects from existing block patterns only. Guaranteed valid markup.
- **Generation mode (V2):** AI may generate new blocks when no pattern matches. Validated against design tokens and allowed block types.

## Provider setup

1. Go to **Settings → Connectors** in WordPress admin.
2. Enter your API key for your chosen provider.
3. The plugin calls the provider through WordPress core's AI Client — no proxy, no shared key.

### DeepSeek

Install the [AI Provider for DeepSeek](https://wordpress.org/plugins/ai-provider-for-deepseek/) plugin, then optionally enable the bundled connector at **Settings → Prompt to Pattern** for key management.

## Hooks & filters

| Hook | Type | Description |
|------|------|-------------|
| `prompttopattern_system_instruction` | Filter | Override the SKILL.md instructions sent to the AI |
| `prompttopattern_enable_deepseek_connector` | Filter | Enable/disable the bundled DeepSeek connector |
| `wp_ai_client_default_request_timeout` | Filter | Adjust AI request timeout (default 30s) |

## REST API

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/wp-json/prompt-to-pattern/v1/patterns` | GET | Returns available patterns + design tokens |

## Build (development)

```bash
npm install
npm run build     # Production build
npm run start     # Watch mode
```

## License

GPL-2.0-or-later
