=== AgentClerk ===
Contributors: mnmatty, matthewyonan
Tags: woocommerce, ai, sales, support, automation
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your WooCommerce store into an AI-powered sales and support agent that answers buyers, closes sales, and handles support automatically.

== Description ==

AgentClerk turns your WooCommerce store into a sales and support agent. It handles the full buyer journey — answering questions, generating quotes, closing sales, fulfilling orders, and resolving post-purchase issues — for both human buyers and AI buyer agents.

**What it does:**

* Crawls your site on setup and builds agent knowledge automatically from your products, policies, FAQs, and content
* Answers product and service questions from human visitors and AI buyer agents
* Generates structured quotes with tracked payment links for conversion attribution
* Supports all WooCommerce fulfillment types: digital downloads, digital services, physical products, subscriptions
* Handles post-purchase setup, onboarding, support, and troubleshooting
* Makes your store discoverable and transactable by AI buyer agents via a machine-readable endpoint
* Supports the A2A (Agent-to-Agent) protocol for standardised agent interoperability
* Buyer-facing support page with escalation and email confirmation flow
* Full conversation logging with outcome tracking and demand analysis

**How it works:**

1. Install and activate the plugin
2. Choose BYOK (bring your own Anthropic API key, free) or TurnKey ($99 one-time, we manage the AI)
3. The plugin scans your site and builds your agent's knowledge base
4. Review what it found, fill any gaps through a short chat, and confirm your catalog
5. Choose where the agent appears — floating widget, product pages, or a dedicated /clerk page
6. Test your agent, then go live

**Pricing:**

* BYOK: Free to install. 1% or $1.00 minimum per agent-closed sale.
* TurnKey: $99 one-time setup. 1.5% or $1.99 minimum per agent-closed sale.
* Lifetime license: $49 one-time, eliminates all per-sale fees permanently.
* Free products and direct WooCommerce checkout sales are never charged.

**External Services:**

This plugin connects to the following third-party services:

1. **AgentClerk service** ([agentclerk.io](https://agentclerk.io))
   Used for: install registration, billing, fee tracking, TurnKey AI proxy, plugin support chat, and license management. The plugin communicates with the AgentClerk API at app.agentclerk.io during onboarding, when processing sales, and for billing operations.
   * [Terms of Service](https://abrilliantway.com/terms/)
   * [Privacy Policy](https://abrilliantway.com/privacy-policy/)

2. **Anthropic API** ([anthropic.com](https://www.anthropic.com))
   Used for: AI-powered conversations with buyers. BYOK users connect directly to the Anthropic API using their own API key. TurnKey users connect through the AgentClerk proxy. API calls are made server-side — keys are never exposed to the browser.
   * [Terms of Service](https://www.anthropic.com/terms)
   * [Privacy Policy](https://www.anthropic.com/privacy)

3. **Stripe** ([stripe.com](https://stripe.com))
   Used for: payment processing during onboarding (TurnKey setup fee, card on file for transaction fees, lifetime license purchase). Card data is stored by Stripe, never on AgentClerk servers. The plugin loads Stripe.js on the onboarding page.
   * [Terms of Service](https://stripe.com/legal)
   * [Privacy Policy](https://stripe.com/privacy)

**Data handling:**

* No buyer PII is transmitted to AgentClerk beyond transaction amount, product type, and timestamp for billing
* Conversation logs are stored on the seller's own WordPress installation
* API keys (BYOK) are encrypted with AES-256 and stored on the seller's server — AgentClerk never receives them
* Optional anonymized conversation data sharing for product improvement is opt-in and off by default

== Installation ==

1. Upload the `agentclerk` folder to `/wp-content/plugins/`, or install directly via Plugins > Add New.
2. Activate the plugin through the Plugins menu.
3. Navigate to AgentClerk > Overview to begin the onboarding wizard.
4. Choose your tier (BYOK or TurnKey) and complete setup — takes under 5 minutes for most stores.

**Requirements:**

* WordPress 6.2 or higher
* WooCommerce 7.0 or higher
* PHP 7.4 or higher
* An Anthropic API key (BYOK tier) or $99 setup payment (TurnKey tier)

== Frequently Asked Questions ==

= Do I need an Anthropic API key? =

Only if you choose the BYOK (Bring Your Own Key) tier. TurnKey users get a managed API key — no technical setup required.

= What happens if a buyer asks something the agent can't answer? =

The agent escalates to you. You choose how to be notified (email, WordPress admin notification, or both) and what message the buyer sees. Escalated conversations appear in your Support dashboard.

= Does AgentClerk charge fees on all my WooCommerce sales? =

No. Fees apply only to sales that the agent closes through a generated quote link. Free products, direct WooCommerce checkout sales, and any sale not initiated by the agent are never charged.

= Can I control which products the agent sells? =

Yes. Each product has an "Agent can sell this" toggle. Only enabled products appear in agent conversations and the ai-manifest.json discovery endpoint.

= What is ai-manifest.json? =

A machine-readable endpoint at yourstore.com/ai-manifest.json that makes your store discoverable by AI buyer agents. It contains only the products you've enabled and updates automatically when your catalog changes.

= What is A2A? =

A2A (Agent-to-Agent) is an open protocol that enables AI agents to communicate with each other. AgentClerk implements A2A so external AI shopping agents can discover your store, have conversations, and complete purchases through a standardised interface.

= Can I try it before going live? =

Yes. Step 6 of onboarding includes a test mode where you can chat with your agent as a buyer would, with a readiness checklist showing quality scores before you go live.

= What if I want to stop using AgentClerk? =

Deactivate the plugin. Your WooCommerce store continues to function normally. No data is deleted from your database unless you uninstall the plugin entirely.

== Screenshots ==

1. Onboarding Step 1 — Choose your tier (BYOK or TurnKey)
2. Onboarding Step 2 — Automatic site scan with live progress
3. Onboarding Step 3 — Review findings and fill gaps via chat
4. Onboarding Step 4 — Catalog confirmation with agent visibility toggles
5. Onboarding Step 5 — Choose where the agent appears
6. Onboarding Step 6 — Test your agent and go live
7. Settings — Business, catalog, placement, API key, and support configuration
8. Conversations — Full conversation log with outcome tracking
9. Sales — Agent-closed transactions, fees, and lifetime license upgrade
10. Buyer-facing widget — Floating chat on your storefront

== Changelog ==

= 1.2.1 =
* Added A2A (Agent-to-Agent) protocol support
* Added promo code support for checkout flows
* Added async scanning via WP-Cron (eliminates set_time_limit)
* Added wp_cache_get/set for custom table queries
* Added %i placeholder for table names (WordPress 6.2+)
* Added DB version tracking for schema migrations
* Fixed widget send button theme conflict with !important overrides
* Fixed browser cache issues with version bumping
* Improved error handling and loading states across all chat surfaces
* All WordPress Plugin Check (PCP) errors resolved

= 1.1.0 =
* Complete ground-up plugin rebuild
* Async site scanner with background processing and progress polling
* AI quote generation via Anthropic tool_use (structured output)
* Escalation notification method control (email, WP admin, or both)
* Admin UI matching wireframe v6 design system
* All SQL queries use prepared statements
* Full input sanitization and output escaping

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.2.1 =
A2A protocol support, promo codes, async scanning, improved WordPress coding standards compliance. Recommended for all users.

= 1.1.0 =
Complete rebuild with improved security, async scanning, and wireframe-accurate UI. Recommended for all users.
