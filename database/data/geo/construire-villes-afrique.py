#!/usr/bin/env python3
"""Construit villes-afrique.json à partir du paquet npm « cities.json » (GeoNames, licence CC BY 4.0).

Usage : python3 -I construire-villes-afrique.py <dossier du paquet décompressé : cities.json, admin1.json> <sortie.json>

Aucun nom n'est inventé ni corrigé : seuls sont retenus les lieux du jeu source (habitants > 1000 ou chefs-lieux d'unité
administrative) des pays listés, avec le nom de leur unité de niveau 1 comme « zone » quand elle existe. La Côte d'Ivoire est
volontairement absente : sa liste, relue, est celle du chargeur d'origine (TerritorySeeder).
"""
import json
import sys

PAYS = ("DZ AO BJ BW BF BI CV CM CF TD KM CG CD DJ EG GQ ER SZ ET GA GM GH GN GW KE LS LR LY MG MW ML MR MU MA MZ NA NE NG RW "
        "ST SN SC SL SO ZA SS SD TZ TG TN UG ZM ZW").split()

source, sortie = sys.argv[1], sys.argv[2]
villes = json.load(open(source + "/cities.json", encoding="utf-8"))
admin1 = {a["code"]: a["name"] for a in json.load(open(source + "/admin1.json", encoding="utf-8"))}

resultat = {}
for v in villes:
    pays = v["country"]
    if pays not in PAYS:
        continue
    nom = " ".join(v["name"].split())
    zone = admin1.get(pays + "." + v["admin1"], "") if v["admin1"] else ""
    zone = " ".join(zone.split())
    if not nom or len(nom) > 100 or len(zone) > 100:
        continue
    resultat.setdefault(pays, set()).add((zone if zone.lower() != nom.lower() else "", nom))

sortie_json = {pays: sorted([[z, n] for z, n in lieux], key=lambda t: (t[0], t[1])) for pays, lieux in sorted(resultat.items())}
with open(sortie, "w", encoding="utf-8") as f:
    json.dump(sortie_json, f, ensure_ascii=False, separators=(",", ":"))
print(sum(len(v) for v in sortie_json.values()), "lieux,", len(sortie_json), "pays")
