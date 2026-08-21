# JOOIM Withdrawal — Rétractation contractuelle en ligne

[English](README.md) · [Slovenčina](README.sk.md) · [Čeština](README.cs.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Polski](README.pl.md)

**JOOIM Withdrawal** est un module PrestaShop destiné à recevoir, confirmer et administrer les déclarations de rétractation en ligne des consommateurs. Il fournit une fonction directe de rétractation, un parcours sécurisé par référence de commande, une rétractation totale ou partielle, une preuve immuable, des confirmations par e-mail/PDF, une séparation multiboutique et un historique d’audit.

Dépôt : https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal  
Auteur : JOO INTERNET MEDIA LTD  
Nom technique et dossier obligatoire : `jooim_withdrawal`  
Version publiée : `1.0.0`  
État : **version publique stable**

L’anglais est la langue source du code, des modèles, de la documentation et des catalogues de traduction. Le français, le slovaque, le tchèque, l’allemand et le polonais sont fournis sous forme de traductions.

## Fonctions principales

- fonction directe de rétractation en ligne avec confirmation finale explicite ;
- recherche sécurisée par référence de commande sans divulgation de données personnelles avant vérification ;
- lien d’accès unique protégé par HMAC envoyé à l’adresse e-mail associée à la commande ;
- consommation du jeton uniquement après confirmation POST, et non lors de la simple ouverture du lien ;
- parcours pour client connecté et invité vérifié ;
- rétractation de toute la commande ou de certaines lignes/quantités ;
- motif et note facultatifs ;
- snapshot JSON immuable avec empreinte SHA-256 ;
- confirmation à l’écran, par e-mail et dans un PDF privé ;
- liste administrative, détail du dossier, historique, saisie manuelle, preuve de remboursement et export CSV ;
- données du vendeur et isolation des commandes propres à chaque boutique en multiboutique ;
- protection CSRF, idempotence, limitation de débit, contrôle de propriété et réponses neutres contre l’énumération ;
- maintenance via Symfony CLI ou un endpoint HTTP protégé par jeton ;
- la désinstallation normale conserve les enregistrements juridiquement pertinents.

## Portée juridique

Le module est conçu autour du cadre européen du droit de rétractation et des exigences relatives à la fonction de rétractation en ligne pour les contrats à distance concernés conclus au moyen d’une interface en ligne. Il ne rejette pas automatiquement une déclaration sur la seule base d’une date, d’un statut de commande ou d’un type de produit.

Le marchand reste responsable de l’analyse juridique, des informations exactes du vendeur, des textes nationaux, des exceptions, des frais de retour, de la décision de remboursement et des durées de conservation. Voir [docs/LEGAL-COMPLIANCE.md](docs/LEGAL-COMPLIANCE.md).

## Prérequis

- PrestaShop 8.1.x à 9.1.x ;
- PHP 8.1 ou version ultérieure ;
- MySQL/MariaDB pris en charge par la version de PrestaShop ;
- configuration e-mail PrestaShop fonctionnelle ;
- TCPDF disponible via PrestaShop ;
- stockage privé `var/` accessible en écriture.

Le module n’utilise aucun override du cœur de PrestaShop.

## Installation

1. Utilisez le paquet d’installation `jooim_withdrawal-1.0.0.zip`.
2. N’utilisez pas l’archive GitHub automatique **Source code (zip)**.
3. Dans le Back Office, ouvrez **Modules → Gestionnaire de modules → Installer un module**.
4. Téléversez le ZIP et installez le module.
5. En multiboutique, configurez chaque boutique séparément.
6. Renseignez l’identité du vendeur, les coordonnées, l’adresse/les instructions de retour et l’URL de confidentialité.
7. Testez le formulaire direct, le parcours sécurisé de commande, l’e-mail, le PDF et l’administration.
8. Exécutez les [tests d’acceptation](docs/ACCEPTANCE-TESTS.md) sur une copie de préproduction.

La version **1.0.0** est la première version publique stable. Les évolutions fonctionnelles ou de compatibilité ultérieures utiliseront un nouveau numéro de version.

Lors de la mise à niveau d’une installation qui utilisait un paquet de préversion `1.0.0`, remplacez le répertoire complet du module, ouvrez une fois sa configuration pour chaque contexte de boutique et videz le cache PrestaShop. La page de configuration complète de façon idempotente la configuration d’exécution, les hooks et les valeurs de sécurité manquants, sans modifier un secret existant ni supprimer les rétractations reçues. Voir [docs/INSTALLATION.md](docs/INSTALLATION.md#6-replacing-an-earlier-100-test-build).

## Parcours client

### Rétractation directe

Le consommateur peut envoyer une déclaration non équivoque sans afficher préalablement une commande. Le formulaire ne recueille que les informations nécessaires pour identifier le consommateur et le contrat et envoyer l’accusé de réception.

### Parcours sécurisé par commande

La référence de commande sert uniquement d’identifiant. La page publique ne confirme ni l’existence de la commande, ni son propriétaire, ni son adresse e-mail. Si la commande est trouvée dans la boutique active, un lien unique est envoyé à l’adresse enregistrée. Une requête GET ne consomme pas le jeton ; la poursuite exige une requête POST protégée contre le CSRF.

## Administration et maintenance

Le module ajoute **Commandes → Rétractations contractuelles** et un panneau dans le détail de la commande. La déclaration d’origine n’est pas réécrite ; les opérations ultérieures sont ajoutées à l’historique.

```bash
php bin/console jooim:withdrawal:maintenance
```

L’alternative HTTP cron est protégée par un jeton secret. Ne publiez jamais l’URL ni le jeton.

## Support Joobox pour ce module

La distribution GitHub gratuite n’inclut pas de support technique individuel. L’installation, la vérification de configuration, les tests de production, les mises à jour et le dépannage peuvent être proposés comme service payant via Joobox PrestaShop Cloud ou JOO INTERNET MEDIA LTD. Voir [SUPPORT.md](SUPPORT.md).

## Attribution Joobox dans le pied de page

Le module affiche :

```text
Module PrestaShop par joobox.eu
```

Si un autre module JOOIM a déjà affiché cette attribution, le doublon est supprimé. Dans un pied de page PrestaShop standard `ps_linklist`, le lien juridiquement pertinent **Se rétracter du contrat** est ajouté comme élément de liste normal immédiatement après le lien statique **Magasins**. Le module s'appuie sur le préfixe d'identifiant stable `link-static-page-stores-` et reprend les classes CSS du lien voisin, afin que le thème actif gère l'apparence et le repli mobile. `displayFooter` reste une solution de repli accessible sans JavaScript ; `displayFooterPart1` n'affiche plus de contenu, car certains thèmes commerciaux rendent ce hook au-dessus du véritable pied de page du site.

La protection CSRF utilise le petit cookie séparé `jooim_withdrawal_csrf` (`Secure` sous HTTPS, `HttpOnly`, `SameSite=Lax`) et n’agrandit pas le cookie principal de PrestaShop. La configuration affiche les deux URL publiques pour chaque langue active de la boutique et signale tout catalogue manquant comme repli en anglais.

## Vérification des mises à jour

La configuration effectue uniquement une lecture de la dernière version GitHub Release et met le résultat en cache pendant six heures. Le module ne télécharge et ne remplace jamais automatiquement ses fichiers.

## Langues et développement

Source/fallback : anglais. Traductions : français, slovaque, tchèque, allemand et polonais. Les modèles d’e-mail sont fournis dans les mêmes langues.

```bash
php tests/static_validation.php
```

Les contrôles statiques ne remplacent pas un test d’intégration sur une installation PrestaShop réelle.

## Hors périmètre de la version 1.0.0

Remboursement automatique, étiquettes de retour, mouvements de stock automatiques, comptabilité, système complet de réclamation/RMA, API publique et décisions juridiques automatiques.

## Licence

Distribué sous [licence MIT](LICENSE). Copyright © 2026 JOO INTERNET MEDIA LTD.


## Ďalšie jazykové názvy / Other language terms

Pozri [multilingual terminology and search aliases](docs/TERMINOLOGY.md).
