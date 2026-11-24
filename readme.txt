=== Export FluentCRM Contacts ===
Contributors: cedricstaces
Tags: fluentcrm, export, contacts, csv, excel
Requires at least: 5.2
Tested up to: 6.7
Requires PHP: 7.0
Stable tag: 0.5.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export all FluentCRM contacts in one click: Excel-ready CSV, or copy the table to the clipboard. Lightweight, free, zero settings.

== Description ==

Export **all** FluentCRM contacts in **one click**, with **every field**: lists, tags, statuses, custom fields, addresses, phones, tracking dates, and more.

= Two export modes =

* **Excel (CSV)**: downloads a UTF-8 file that opens directly in Excel (selectable `;` or `,` separator).
* **Clipboard**: copies the full table, ready to paste in Excel with Ctrl+V (one value per column).

= Highlights =

* Single PHP file, no dependency, no settings page.
* Works in batches of 500 contacts: suitable for large databases without memory issues.
* Custom fields detected automatically.
* Administrators (or users with the FluentCRM export capability) only. Nonces and prepared SQL statements for security.
* Translations: English, French, German, Dutch, Russian.

== Installation ==

1. Upload and activate the plugin (Plugins → Add New → Upload Plugin).
2. Open **FluentCRM → Contacts → Export Contacts** — the entry is available in the WordPress admin sidebar under FluentCRM (right after Tags) and in the FluentCRM sidebar under Contacts (right below Segments) — then click one of the two buttons.

The export page lives at the classic WordPress URL: `/wp-admin/admin.php?page=fce-contact-exporter`.

== Frequently Asked Questions ==

= Does it work with 3,000+ contacts? =
Yes. Contacts are fetched and streamed in batches of 500, so memory use stays constant regardless of the total count (tested against the FluentCRM database schema).

= The CSV file looks wrong in Excel? =
Switch the separator on the export page: Excel FR uses `;`, Excel EN uses `,`.

= Custom field columns are missing? =
They are detected automatically; make sure FluentCRM is active and the custom fields exist.

= Does the plugin collect any data? =
No. Nothing leaves your site: the export happens locally in your browser or in the downloaded file.

== Changelog ==

= 0.5.4 =
* Fixed the **Lists** and **Tags** columns always being empty (in the CSV download and the clipboard copy): the pivot queries compared the stored `object_type` ('FluentCrm\App\Models\Tag' / '…Lists') through a raw SQL literal, and MySQL silently strips unrecognized backslash escapes inside string literals, so the comparison never matched. The classes are now passed as prepared-statement values, which keeps the backslashes intact.

= 0.5.3 =
* The entry is inserted at the end of the Contacts sub-menu in the FluentCRM sidebar (right below **Segments**); internal cleanups.
* Added **Russian** translation; refreshed FR/DE/NL files (new button wording, standard plural entries).

= 0.5.2 =
* Refined export page layout (flex layout for the filters, cleaner copy button) and small document icon on the FluentCRM sidebar entry.
= 0.5.1 =
* Added an **Export Contacts** sub-menu entry in the WordPress admin sidebar under FluentCRM, right after Tags. The link points directly to `admin.php?page=fce-contact-exporter`, so it always resolves to the export page.
* Restored the 0.4.0 behavior inside the FluentCRM sidebar (entry under Contacts, right after Tags).

= 0.4.0 =
* The "Export Contacts" entry now lives inside the FluentCRM sidebar under **Contacts**, right after Tags, using FluentCRM's `fluent_crm/menu_items` filter.
* Fixed the 404 on the submenu: the page is registered as a hidden admin page at its own classic WordPress URL (`admin.php?page=fce-contact-exporter`) instead of a hash-based FluentCRM slug (a `#` fragment never reaches the server, so those links could never load a custom page reliably).

= 0.3.0 =
* The export page now lives under the FluentCRM menu URL scheme: `/wp-admin/admin.php?page=fluentcrm-admin#/export-contacts`.
* The plugin deactivates itself automatically when FluentCRM Pro is detected (Pro already ships contact export), with an admin notice.

= 0.2.0 =
* English strings only, with bundled French, German, and Dutch translations (.po/.mo + .pot template).
* Honor the FluentCRM `fcrm_manage_contacts_export` capability in export handlers.
* Files moved to the repository root; author credit updated to Cedric Staces.

= 0.1.0 =
* First release: CSV (Excel) download and clipboard table copy, status filter, custom fields, lists, tags, and statuses included.
