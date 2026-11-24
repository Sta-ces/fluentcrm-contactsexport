# Export FluentCRM Contacts

A **very lightweight** WordPress plugin (one PHP file, no dependencies, no settings) that exports **all FluentCRM contacts in one click** — with every field, lists, tags, statuses, and custom fields — to an Excel-ready CSV, or copies them as a table to your clipboard for pasting into Excel.

![Version](https://img.shields.io/badge/version-0.5.1-blue) ![License](https://img.shields.io/badge/license-GPL--2.0-green) ![Price](https://img.shields.io/badge/price-free-brightgreen)

## ✨ How it works

In your WordPress admin, the **Export Contacts** entry is available in two places:

1. **WordPress admin sidebar → FluentCRM → Export Contacts**, right after **Tags**. The link points directly to `admin.php?page=fce-contact-exporter`.
2. **FluentCRM sidebar → Contacts → Export Contacts**, right after **Tags** as well (injected through FluentCRM's own `fluent_crm/menu_items` filter).

> **Note about the URL**: the export page lives at the classic WordPress URL `/wp-admin/admin.php?page=fce-contact-exporter`. A `#` fragment is never sent to the server, and FluentCRM's compiled Vue router redirects any unknown `#/` route back to the dashboard, so a plugin page cannot reliably live inside the SPA's hash URL space — the classic URL is the robust choice.

### FluentCRM Pro?

If **FluentCRM Pro** is detected, the plugin **deactivates itself automatically** with an admin notice: Pro already ships contact export, so keeping the plugin enabled would only duplicate a paying feature you already have. Re-activating it when Pro is disabled is safe — the check runs on every admin request.

1. **⬇ Download for Excel (CSV)**: downloads a `fluentcrm-contacts-....csv` file encoded in UTF‑8 (with BOM), which **opens directly in Excel** with a double-click. The separator (`;` or `,`) is selectable depending on your Excel version.
2. **📋 Copy the table**: copies **all contacts to the clipboard** as a table (TSV). Click the first empty cell in Excel, then **paste (Ctrl+V)**: every value lands in its own column.

Both options accept an **optional status filter** (all, subscribed, unsubscribed, pending, bounced, unconfirmed).

### Why not use FluentCRM's own export?

FluentCRM (free version) has CSV **import** built in, but contact **export** is a Pro feature: the `fcrm_manage_contacts_export` capability exists in the core, yet no free export endpoint implements it. This plugin fills that gap — and it also honors that same FluentCRM capability for its AJAX/CSV actions, so CRM managers (not only admins) can export if you grant them the capability.

## 📦 Exported data

- Identity: first name, last name, title, email, phone, date of birth, timezone
- Address: address lines 1/2, postal code, city, state/province, country
- CRM: **status**, contact type, **lists**, **tags**, **custom fields** (discovered automatically), points, lifetime value, source, company ID, IP
- Tracking: created at, updated at, last activity

**Lists** and **tags** are exported into a single cell per contact (joined with `", "`), and **custom fields** are added as columns automatically, with zero configuration.

## 🪶 Why this plugin is lightweight

- **A single PHP file**, no bundled JS/CSS build, no third-party library.
- No settings page, no own storage: nothing to maintain.
- SQL queries **chunked in batches of 500 contacts**: works smoothly even with tens of thousands of contacts, without exceeding memory limits (500 contacts typically use only a few MB of RAM per batch — including for exports of 3,000+ contacts, or 30,000+).
- Native WordPress admin UI only: FluentCRM updates have minimal impact (the plugin reads the standard `fc_subscribers`, `fc_tags`, `fc_lists`, `fc_subscriber_pivot`, and `fc_subscriber_meta` tables).
- Fully translatable (textdomain `fluentcrm-contactsexport`) with bundled French, German, and Dutch translations, plus a `.pot` template.

## 🔒 Security

An export requires `manage_options` (administrator) or the FluentCRM `fcrm_manage_contacts_export` capability. Both flows (download and clipboard) are protected by WordPress nonces, and all data goes through validated, prepared SQL queries. Everything stays on your site — no data is sent anywhere.

## 🚀 Installation

1. Download the repository, then in WordPress: **Plugins → Add New → Upload Plugin** (or copy `fluentcrm-contactsexport.php` and the `languages/` folder into `wp-content/plugins/fluentcrm-contactsexport/`).
2. Activate **Export FluentCRM Contacts**.
3. Open **FluentCRM → Contacts → Export Contacts** and click a button.

Requirements: WordPress ≥ 5.2, PHP ≥ 7.0, FluentCRM active.

> In your Excel **site language**: German uses `;` (and `de_DE` UI translation), English dashboards use `,`.

## 🛠 Maintenance

No action required. The plugin automatically:
- discovers FluentCRM custom fields (via its internal API, with a fallback on the `fc_subscriber_meta` table);
- detects the standard FluentCRM tables and shows a clear message if they are missing (incomplete setup).

## 🌍 Translations

The admin UI is available in **English** (default), **French**, **German**, and **Dutch** (`languages/*.po` / `*.mo`). The `.pot` template in `languages/` lets you add any other language.

## 📄 License

[GPL‑2.0-or-later](LICENSE) — open source and free.
