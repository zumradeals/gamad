# Photo du héros de l’accueil

`gamad-accueil-hero.webp` est la photo illustrative reprise de la maquette d’accueil approuvée le 23 octobre 2026 (D-156). Elle ne représente pas des membres identifiés du réseau.

Le fichier est conservé dans Git, servi localement et encodé en WebP sans perte. Aucune nouvelle image n’a été générée. La zone photographique a été isolée de la maquette pour que le texte et les boutons restent du HTML accessible.

Source : maquette PNG de 1086 × 1448 px, SHA-256 `b29d4961b7256492a729962beae2a4960dcdcf445898917fc2fdee01f8b77b3c`.
Zone reprise : origine (520, 107), dimensions 510 × 280 px ; cette zone exclut la légende de la maquette, recréée en HTML. Les quatre personnes et leurs visages sont conservés.

Préparation à partir de la source :

```sh
convert maquette.png -crop 510x280+520+107 +repage -define webp:lossless=true gamad-accueil-hero.webp
```

ImageMagick n’est pas une dépendance de l’application ; l’asset final suffit au déploiement.
