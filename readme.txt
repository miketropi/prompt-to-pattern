=== Prompt to Pattern ===
Contributors: nextora
Tags: ai, block-patterns, patterns, block-editor, site-editor, page-builder
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compose WordPress pages and FSE templates from existing block patterns using natural-language prompts and AI.

== Description ==

Prompt to Pattern lets you type a description of the page you want — like "A pricing page with a hero, a three-tier pricing table, an FAQ section, and a call to action" — and have an AI agent select and arrange existing block patterns to compose the page.

[Full readme content to be completed in Phase 6]

== External Services ==

This plugin sends the user's natural-language prompt together with the site's registered block pattern catalog (slug, title, description) to a third-party AI provider (such as OpenAI, Anthropic, Google, or DeepSeek) configured by the site owner via WordPress Settings → Connectors.

The plugin does not store, proxy, or transmit any API key itself. The site owner provides their own credentials directly through the WordPress Connectors screen. No data is shared with any service other than the provider the user has explicitly configured.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/`.
2. Activate through the 'Plugins' screen.
3. Configure an AI provider via Settings → Connectors.
4. Start composing pages in the block editor.

== Frequently Asked Questions ==

= What WordPress version is required? =

WordPress 7.0 or later (core AI Client, Connectors API, and Abilities API are required).

= Which AI providers are supported? =

Any provider registered with the core AI Client. WordPress 7.0 ships Anthropic, Google, and OpenAI out of the box. DeepSeek is supported via its own connector plugin or through the built-in optional connector.

= Does the plugin store my API key? =

No. The plugin never handles, proxies, or stores API keys. All credentials are managed by WordPress core's Connectors screen.

= Is the generated content published immediately? =

No. All output is shown as a preview first. Nothing is saved or published until you explicitly approve it.

== Changelog ==

= 1.0.0 =
* Initial release.
