# Données géographiques (D-217)

`villes-afrique.json` : lieux de 53 pays africains (la Côte d'Ivoire est chargée par `TerritorySeeder`, liste relue), construits par `construire-villes-afrique.py` à partir du paquet npm [cities.json](https://www.npmjs.com/package/cities.json) version 1.1.65, lui-même tiré du gazetier [GeoNames](https://www.geonames.org) (« tous les lieux de plus de 1 000 habitants ou chefs-lieux d'unité administrative »).

**Licence : Creative Commons Attribution 4.0 (CC BY 4.0).** Attribution à GeoNames exigée ; elle est affichée dans la page Aide, rubrique « Crédits ». Les données de GeoNames sont collaboratives : leur qualité est inégale, et aucun nom n'y est corrigé ni inventé par GAMAD. Un nom erroné se corrige dans Paramètres › Territoires.

Pour reconstruire le fichier : décompresser le paquet `cities.json`, puis `python3 -I construire-villes-afrique.py <dossier du paquet> villes-afrique.json`.

Les noms de pays viennent des données de langue du système (ICU / CLDR) au moment de l'import.
