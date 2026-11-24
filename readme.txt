=== Export FluentCRM Contacts ===
Contributors: cedricstaces
Tags: fluentcrm, export, contacts, csv, excel
Requires at least: 5.2
Tested up to: 6.7
Requires PHP: 7.0
Stable tag: 0.3.0
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
* Translations: English, French, German, Dutch.

== Installation ==

1. Upload and activate the plugin (Plugins → Add New → Upload Plugin).
2. Open **FluentCRM → Export Contacts** and click one of the two buttons.

== Frequently Asked Questions ==

= My URL looks like admin.php?page=fluentcrm-admin%23%2Fexport-contacts — is that normal? =
Yes. It is the classic WordPress admin URL (`admin.php?page=...`) using FluentCRM's menu slug convention. The encoded form is what makes the browser actually load the page (a literal "#" would never reach the server and FluentCRM's app router would redirect it to the dashboard).

= Does it work with 3,000+ contacts? =
Yes. Contacts are fetched and streamed in batches of 500, so memory use stays constant regardless of the total count (tested against the FluentCRM database schema).

= The CSV file looks wrong in Excel? =
Switch the separator on the export page: Excel FR uses `;`, Excel EN uses `,`.

= Custom field columns are missing? =
They are detected automatically; make sure FluentCRM is active and the custom fields exist.

= Does the plugin collect any data? =
No. Nothing leaves your site: the export happens locally in your browser or in the downloaded file.

== Changelog ==

= 0.3.0 =
* The export page now lives under the FluentCRM menu URL scheme: `/wp-admin/admin.php?page=fluentcrm-admin#/export-contacts`.
* The plugin deactivates itself automatically when FluentCRM Pro is detected (Pro already ships contact export), with an admin notice.

= 0.2.0 =
* English strings only, with bundled French, German, and Dutch translations (.po/.mo + .pot template).
* Honor the FluentCRM `fcrm_manage_contacts_export` capability in export handlers.
* Files moved to the repository root; author credit updated to Cedric Staces.

= 0.1.0 =
* First release: CSV (Excel) download and clipboard table copy, status filter, custom fields, lists, tags, and statuses included.
