# Export Contacts FluentCRM (gratuit)

Plugin WordPress **très léger** (un seul fichier, aucune dépendance, aucun réglage) pour exporter **en un clic** tous les contacts de [FluentCRM](https://fluentcrm.com/) — avec **toutes leurs informations**, listes, étiquettes (tags), statuts et champs personnalisés.

![Version](https://img.shields.io/badge/version-0.1.0-blue) ![Licence](https://img.shields.io/badge/licence-GPL--2.0-green) ![Gratuit](https://img.shields.io/badge/prix-gratuit-brightgreen)

## ✨ Fonctionnement

Dans l'administration WordPress : **FluentCRM → Export Contacts** — c'est tout.

1. **⬇ Télécharger en Excel (CSV)** : télécharge un fichier `fluentcrm-contacts-....csv` encodé UTF‑8 (avec BOM), qui s'ouvre **directement dans Excel** par double-clic. Le séparateur (`;` ou `,`) est sélectionnable selon l'Excel installé.
2. **📋 Copier le tableau** : copie tous les contacts **dans le presse-papiers** en tableau (TSV). Cliquez ensuite sur la première cellule vide d'Excel et **Collez (Ctrl+V)** : chaque donnée arrive dans sa colonne.

Les deux options acceptent un **filtre facultatif de statut** (tous, inscrits, désabonnés, en attente, rebondis, non confirmés).

## 📦 Données exportées

- Identité : prénom, nom, civilité, e-mail, téléphone, date de naissance, fuseau horaire
- Adresse : adresse 1/2, code postal, ville, région/état, pays
- CRM : **statut**, type de contact, **listes**, **étiquettes**, **champs personnalisés** (découverts dynamiquement), points, valeur vie client, source, entreprise, IP
- Suivi : date de création, dernière modification, dernière activité

Les **listes** et **étiquettes** sont exportées dans une seule cellule par contact (séparées par `", "`), et les **champs personnalisés** sont ajoutés automatiquement en colonnes, sans réglage.

## 🪶 Pourquoi ce plugin est léger

- **Un seul fichier PHP** (~500 lignes), pas de JavaScript/CSS empaqueté, pas de bibliothèque tierce.
- Aucune page de réglages, aucun stockage propre : rien à maintenir.
- Requêtes SQL **par lots de 500 contacts** : fonctionne même avec des dizaines de milliers de contacts, sans dépasser la mémoire.
- Interface 100 % admin native de WordPress : la montée des versions de FluentCRM ou de WordPress n'a pas d'impact (le plugin lit les tables standard `fc_subscribers`, `fc_tags`, `fc_lists`, `fc_subscriber_pivot`, `fc_subscriber_meta`).
- Compatible i18n (textdomain `fluentcrm-contactsexport`).

## 🔒 Sécurité

Export réservé aux administrateurs (`manage_options`), nonces WordPress sur les deux parcours (téléchargement et copie), entrées validées et requêtes préparées.

## 🚀 Installation

1. Téléchargez le dossier `fluentcrm-contactsexport/` (ou l'archive du dépôt) puis : **Extensions → Ajouter → Téléverser une extension**.
2. Activez **Export Contacts FluentCRM (gratuit)**.
3. Ouvrez **FluentCRM → Export Contacts** et cliquez.

Prérequis : WordPress ≥ 5.2, PHP ≥ 7.0, FluentCRM activé.

## 🛠 Maintenance

Aucune action requise. Le plugin détecte automatiquement :
- les champs personnalisés de FluentCRM (via son API interne, avec repli sur la table `fc_subscriber_meta`),
- les tables standard de FluentCRM et affiche un message clair si elles sont absentes (installation inachevée).

## 📄 Licence

[GPL‑2.0-or-later](LICENSE) — publié en open source, gratuitement.
