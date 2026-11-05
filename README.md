# Teater Scenario – hjemmeside med redigeringsværktøj

Hjemmesiden ligger i mappen `site/` og er almindelig PHP uden database. Alt indhold
(tekster, forestillinger, sponsorer, kontakt) rettes i et redigeringsværktøj på
**/admin**, så ingen behøver at røre koden.

## Mapper (inde i `site/`)

| Sti | Indhold |
| --- | --- |
| `index.php`, `om.php`, `forestillinger.php`, `forestilling.php` | Siderne. `forestilling.php?id=…` er hver forestillings egen side. |
| `style.css`, `script.js` | Design og effekter (tæppe, nedtælling, galleri, mobilmenu). |
| `billeder/` | Faste billeder (logo, tæppe, papir-tekstur, de oprindelige sponsorlogoer). |
| `admin/` | Redigeringsværktøjet (`index.php` + `admin.js` + `admin.css`) og dets API (`api.php`). |
| `inc/` | Fælles PHP-kode og `standard-indhold.json` (indholdet, før nogen har gemt). |
| `data/` | **Oprettes på serveren:** indhold, brugere og historik. |
| `uploads/` | **Oprettes på serveren:** billeder uploadet i editoren. |

## Sæt den op

1. Upload indholdet af `site/` til webhotellet (kræver PHP 7.4 eller nyere – GD anbefales til billeder).
2. Sørg for, at PHP kan skrive i `data/` og `uploads/`.
3. Gå til `https://din-adresse/admin/` og opret den første administrator.
   Den første, der åbner siden, bliver administrator – så gør det med det samme.
4. Opret logins til redaktørerne under **Brugere**. Editoren laver en besked med
   brugernavn og kodeord, som kan sendes direkte til personen.

## Vigtigt ved opdateringer

`data/` og `uploads/` indeholder alt, hvad redaktørerne har lavet. Når du uploader en
ny version af koden, så **overskriv eller slet aldrig de to mapper** (pas på med
"spejl"/"synkronisér" i FTP-programmer). De er derfor også udeladt fra git.

## Sikkerhed

- Kodeord gemmes med `password_hash`. Login spærres i 15 minutter efter 8 forkerte forsøg.
- Datafilerne er `.php`-filer, der starter med `<?php exit; ?>`, så de aldrig kan hentes
  udefra – heller ikke på servere, der ignorerer `.htaccess`.
- Alle ændringer kræver et CSRF-token. Alt indhold renses på serveren (kun `http(s)`,
  `mailto:` og `tel:` links, kun billeder fra `billeder/` og `uploads/`).
- Uploadede billeder bliver tjekket, drejet rigtigt, gjort mindre og gemt som WebP.

## Fortryd

Hver gang nogen gemmer, lægges den forrige version i `data/backups/` (de seneste 100).
Under **Historik** i editoren kan enhver version gendannes med ét klik.

## Lageret (/lager)

Udstyrslageret ligger i `site/lager/` og erstatter det gamle WordPress-plugin. Det bruger
samme slags filer som resten af siden – ingen database.

- Første gang man åbner `/lager/`, opretter man en administrator. Flere personer tilføjes under **Brugere**.
- Enheder, reservationer og sektioner gemmes i `data/lager.php`, logins i `data/lager-brugere.php`.
  Hver gang noget ændres, gemmes den forrige version i `data/lager-backups/` (de seneste 50).
- Under **Indstillinger** kan man importere fra Excel/CSV (kolonner: ID, Navn, Placering, Beskrivelse)
  og hente hele lageret som en Excel-venlig fil.

## Prøv lokalt

```sh
cd site   # mappen med selve hjemmesiden
php -S localhost:8000
# åbn http://localhost:8000 og http://localhost:8000/admin/
```
