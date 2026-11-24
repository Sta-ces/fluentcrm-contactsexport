=== Export Contacts FluentCRM (gratuit) ===
Contributors: sta-ces
Tags: fluentcrm, export, contacts, csv, excel
Requires at least: 5.2
Tested up to: 6.7
Requires PHP: 7.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Exportez en un clic tous vos contacts FluentCRM en Excel (CSV) ou copiez-les dans le presse-papiers. Léger, gratuit, zéro réglage.

== Description ==

Exportez **en un clic** tous les contacts de FluentCRM avec **toutes leurs informations** : listes, étiquettes (tags), statuts, champs personnalisés, adresses, téléphones, dates de suivi, etc.

= Deux exportations possibles =

* **Excel (CSV)** : télécharge un fichier UTF-8 qui s'ouvre directement dans Excel (séparateur `;` ou `,` sélectionnable).
* **Presse-papiers** : copie le tableau complet, à coller dans Excel avec Ctrl+V (une donnée par colonne).

= Points forts =

* Un seul fichier PHP, aucune dépendance, aucune page de réglages.
* Fonctionne par lots de 500 contacts : adapté aux bases volumineuses sans dépassement mémoire.
* Champs personnalisés détectés automatiquement.
* Réservé aux administrateurs, nonces et requêtes préparées (sécurité WordPress).

== Installation ==

1. Téléversez le plugin puis activez-le (Extensions → Ajouter → Téléverser).
2. Ouvrez **FluentCRM → Export Contacts** et cliquez sur un des deux boutons.

== Frequently Asked Questions ==

= Le fichier CSV s'ouvre mal dans Excel ? =
Choisissez un autre séparateur sur la page d'export : Excel FR utilise `;`, Excel EN utilise `,`.

= Les colonnes des champs personnalisés n'apparaissent pas ? =
Elles sont détectées automatiquement ; assures-vous que FluentCRM est bien activé et que les champs personnalisés existent.

= Le plugin collecte-t-il des données ? =
Non. Aucune donnée ne quitte votre site : l'export se fait localement dans votre navigateur ou dans le fichier téléchargé.

== Changelog ==

= 0.1.0 =
* Première version : export CSV (Excel) et copie en tableau via le presse-papiers, filtre de statut, champs personnalisés, listes, étiquettes et statuts inclus.
