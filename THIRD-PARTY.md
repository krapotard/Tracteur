# Composants et ressources tiers

Tracteur n'embarque aucune bibliothèque de code tierce (pas de Composer, pas de npm). Il utilise ou télécharge les ressources suivantes, qui gardent leur propre licence.

## Polices intégrées (`assets/polices.css`, sous forme WOFF2 en base64)

Obtenues auprès de [Google Fonts](https://fonts.google.com/).

| Police | Licence |
|---|---|
| Atkinson Hyperlegible | SIL Open Font License 1.1 |
| Barlow, Barlow Condensed, Barlow Semi Condensed | SIL Open Font License 1.1 |
| Bebas Neue | SIL Open Font License 1.1 |
| Caveat Brush | SIL Open Font License 1.1 |
| Concert One | SIL Open Font License 1.1 |
| Federo | SIL Open Font License 1.1 |
| Fira Sans | SIL Open Font License 1.1 |
| Fredericka the Great | SIL Open Font License 1.1 |
| Lexend | SIL Open Font License 1.1 |
| Lobster Two | SIL Open Font License 1.1 |
| Lora | SIL Open Font License 1.1 |
| Montserrat | SIL Open Font License 1.1 |
| Open Sans | SIL Open Font License 1.1 |
| Oswald | SIL Open Font License 1.1 |
| Permanent Marker | Apache License 2.0 |
| Roboto | Apache License 2.0 (ou SIL OFL 1.1 selon la version) |

Le texte de la licence OFL 1.1 est disponible sur <https://openfontlicense.org/>, celui de la licence Apache 2.0 sur <https://www.apache.org/licenses/LICENSE-2.0>. Les noms de polices réservés par leurs auteurs ne sont pas modifiés.

## Téléchargés à l'installation sur un serveur Linux

- **chrome-headless-shell** ([Chrome for Testing](https://googlechromelabs.github.io/chrome-for-testing/), Google) : téléchargé depuis le site officiel dans `data/chrome/`. Licence BSD et composants tiers, voir les fichiers fournis avec le binaire. Il sert à produire le PDF.
- **Bibliothèques système** (par exemple `libatk-bridge`, `libgbm`, `libasound`, `libatspi`) : extraites de paquets RPM de la distribution **AlmaLinux 8** par `src/extraire_rpm.py`, sans installation système, dans `data/chrome/libs/`. Elles restent sous leurs licences d'origine (LGPL, MIT…).

Rien de tout cela n'est distribué avec le dépôt : l'assistant d'installation le télécharge depuis les sources officielles.

## Outils de développement (non distribués)

- [veraPDF](https://verapdf.org/) (GPL-3.0 ou MPL-2.0) : validation PDF/UA des PDF produits.
- [Ghostscript](https://ghostscript.com/) (AGPL-3.0) : comparaison visuelle de PDF lors des essais.
- Pictogrammes de la banque par défaut, logo et icônes de l'application : créés pour ce projet, couverts par la licence du projet.
