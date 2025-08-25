# Pendu — Oasis du Désert

Un mini-jeu du Pendu en **PHP sans base de données**, avec un petit thème "Oasis du Désert".  
Trois fichiers principaux : `index.php`, `admin.php` et `mots.txt` (liste des mots).

## Lancer en local
1. Copiez tous les fichiers dans un dossier accessible par votre serveur PHP (MAMP/WAMP/XAMPP, `php -S`, etc.).
2. Ouvrez `index.php` dans le navigateur pour jouer.
3. Ouvrez `admin.php` pour gérer les mots (ajout / suppression).

## Règles & fonctionnalités
- Mot choisi au hasard depuis `mots.txt` au début de la partie (filtré par difficulté si possible).
- Lettres proposées via un clavier virtuel A–Z.
- Historique des lettres déjà proposées.
- Victoire quand toutes les lettres sont trouvées.
- Défaite quand le dessin du pendu est complet.
- **Bouton Abandonner** pour lâcher la partie.
- **Difficultés** : Facile (4–6), Moyen (7–8), Difficile (9+).
- **Admin** : ajout/suppression de mots (a–z uniquement, sans accents, pas de doublons, au moins 1 mot).

## Déploiement PLESK / GitHub
- Init Git puis poussez sur `https://github.com/prenom-nom/pendu` (remplacez par votre prénom-nom).
- Déployez le dossier sur votre hébergement PLESK (PHP activé).

Bonne chance et amusez-vous ! 🐪
