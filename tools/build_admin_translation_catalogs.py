#!/usr/bin/env python3
"""Build complete Back Office settings XLIFF catalogues for JOOIM Withdrawal.

The English source catalogue is authoritative. Existing reviewed catalogue units are
preserved, while the 120 settings strings are merged from deterministic locale
files under tools/admin-settings-targets/.
"""
from __future__ import annotations

import hashlib
import html
import json
import re
import sys
from pathlib import Path
from xml.etree import ElementTree as ET
from xliff_helpers import indent

ROOT = Path(__file__).resolve().parents[1]
TRANSLATIONS = ROOT / "translations"
SOURCES_FILE = ROOT / "tools" / "admin_settings_sources.json"
TARGET_DIR = ROOT / "tools" / "admin-settings-targets"
DOMAIN = "ModulesJooimwithdrawalAdmin"
NS = "urn:oasis:names:tc:xliff:document:1.2"
ET.register_namespace("", NS)

# New configuration strings introduced by the localized-route work. Existing
# reviewed catalogues already contain the remaining settings strings.
REVIEWED_OVERRIDES: dict[str, dict[str, str]] = {
    "sk-SK": {
        "Back Office settings only": "Iba nastavenia Back Office",
        "Each active shop language receives its own localized friendly path. The language-neutral entry URLs remain available for external link-list modules. Test both flows in every active language after deployment.": "Každý aktívny jazyk obchodu dostane vlastnú lokalizovanú čitateľnú cestu. Jazykovo neutrálne vstupné URL zostávajú dostupné pre externé moduly zoznamov odkazov. Po nasadení otestujte oba procesy v každom aktívnom jazyku.",
        "Language-neutral URLs for external link lists:": "Jazykovo neutrálne URL pre externé zoznamy odkazov:",
        "Use these URLs in modules that provide only one URL field for all languages. The entry controller selects the current or preferred shop language and redirects to the localized friendly path.": "Tieto URL použite v moduloch, ktoré poskytujú iba jedno pole URL pre všetky jazyky. Vstupný controller vyberie aktuálny alebo preferovaný jazyk obchodu a presmeruje na lokalizovanú čitateľnú cestu.",
        "GitHub checks are performed at most once every six hours unless manually refreshed. The module never downloads or installs code automatically.": "Kontroly GitHubu sa vykonávajú najviac raz za šesť hodín, pokiaľ sa stav neobnoví manuálne. Modul nikdy automaticky nesťahuje ani neinštaluje kód.",
        "Update check failed: %error%": "Kontrola aktualizácie zlyhala: %error%",
    },
    "cs-CZ": {
        "Back Office settings only": "Pouze nastavení Back Office",
        "Each active shop language receives its own localized friendly path. The language-neutral entry URLs remain available for external link-list modules. Test both flows in every active language after deployment.": "Každý aktivní jazyk obchodu získá vlastní lokalizovanou čitelnou cestu. Jazykově neutrální vstupní URL zůstávají dostupné pro externí moduly seznamů odkazů. Po nasazení otestujte oba procesy v každém aktivním jazyce.",
        "Language-neutral URLs for external link lists:": "Jazykově neutrální URL pro externí seznamy odkazů:",
        "Use these URLs in modules that provide only one URL field for all languages. The entry controller selects the current or preferred shop language and redirects to the localized friendly path.": "Tyto URL použijte v modulech, které poskytují pouze jedno pole URL pro všechny jazyky. Vstupní controller vybere aktuální nebo preferovaný jazyk obchodu a přesměruje na lokalizovanou čitelnou cestu.",
        "GitHub checks are performed at most once every six hours unless manually refreshed. The module never downloads or installs code automatically.": "Kontroly GitHubu se provádějí nejvýše jednou za šest hodin, pokud stav neobnovíte ručně. Modul nikdy automaticky nestahuje ani neinstaluje kód.",
        "Update check failed: %error%": "Kontrola aktualizace selhala: %error%",
    },
    "de-DE": {
        "Back Office settings only": "Nur Back-Office-Einstellungen",
        "Each active shop language receives its own localized friendly path. The language-neutral entry URLs remain available for external link-list modules. Test both flows in every active language after deployment.": "Jede aktive Shopsprache erhält einen eigenen lokalisierten, lesbaren Pfad. Sprachneutrale Einstiegs-URLs bleiben für externe Linklisten-Module verfügbar. Testen Sie nach der Bereitstellung beide Abläufe in jeder aktiven Sprache.",
        "Language-neutral URLs for external link lists:": "Sprachneutrale URLs für externe Linklisten:",
        "Use these URLs in modules that provide only one URL field for all languages. The entry controller selects the current or preferred shop language and redirects to the localized friendly path.": "Verwenden Sie diese URLs in Modulen, die nur ein URL-Feld für alle Sprachen bereitstellen. Der Einstiegscontroller wählt die aktuelle oder bevorzugte Shopsprache und leitet auf den lokalisierten, lesbaren Pfad weiter.",
        "GitHub checks are performed at most once every six hours unless manually refreshed. The module never downloads or installs code automatically.": "GitHub-Prüfungen werden höchstens alle sechs Stunden durchgeführt, sofern sie nicht manuell aktualisiert werden. Das Modul lädt oder installiert niemals automatisch Code.",
        "Update check failed: %error%": "Die Aktualisierungsprüfung ist fehlgeschlagen: %error%",
    },
    "fr-FR": {
        "Back Office settings only": "Paramètres du back-office uniquement",
        "Each active shop language receives its own localized friendly path. The language-neutral entry URLs remain available for external link-list modules. Test both flows in every active language after deployment.": "Chaque langue active de la boutique dispose de son propre chemin convivial localisé. Les URL d’entrée indépendantes de la langue restent disponibles pour les modules externes de listes de liens. Après le déploiement, testez les deux parcours dans chaque langue active.",
        "Language-neutral URLs for external link lists:": "URL indépendantes de la langue pour les listes de liens externes :",
        "Use these URLs in modules that provide only one URL field for all languages. The entry controller selects the current or preferred shop language and redirects to the localized friendly path.": "Utilisez ces URL dans les modules qui ne proposent qu’un seul champ URL pour toutes les langues. Le contrôleur d’entrée sélectionne la langue actuelle ou préférée de la boutique et redirige vers le chemin convivial localisé.",
        "GitHub checks are performed at most once every six hours unless manually refreshed. The module never downloads or installs code automatically.": "Les vérifications GitHub sont effectuées au maximum une fois toutes les six heures, sauf actualisation manuelle. Le module ne télécharge ni n’installe jamais de code automatiquement.",
        "Update check failed: %error%": "La vérification de mise à jour a échoué : %error%",
    },
    "pl-PL": {
        "Back Office settings only": "Tylko ustawienia Back Office",
        "Each active shop language receives its own localized friendly path. The language-neutral entry URLs remain available for external link-list modules. Test both flows in every active language after deployment.": "Każdy aktywny język sklepu otrzymuje własną zlokalizowaną, czytelną ścieżkę. Niezależne od języka adresy wejściowe URL pozostają dostępne dla zewnętrznych modułów list odsyłaczy. Po wdrożeniu przetestuj oba procesy w każdym aktywnym języku.",
        "Language-neutral URLs for external link lists:": "Niezależne od języka adresy URL dla zewnętrznych list odsyłaczy:",
        "Use these URLs in modules that provide only one URL field for all languages. The entry controller selects the current or preferred shop language and redirects to the localized friendly path.": "Użyj tych adresów URL w modułach, które udostępniają tylko jedno pole URL dla wszystkich języków. Kontroler wejściowy wybiera bieżący lub preferowany język sklepu i przekierowuje do zlokalizowanej, czytelnej ścieżki.",
        "GitHub checks are performed at most once every six hours unless manually refreshed. The module never downloads or installs code automatically.": "Kontrole GitHub są wykonywane najwyżej raz na sześć godzin, chyba że zostaną odświeżone ręcznie. Moduł nigdy nie pobiera ani nie instaluje kodu automatycznie.",
        "Update check failed: %error%": "Sprawdzanie aktualizacji nie powiodło się: %error%",
    },
}

ENGLISH_VARIANTS = {"en-US", "en-GB", "en-CA"}
COPY_CATALOGUE = {"fr-CA": "fr-FR"}


def parse_xlf(path: Path) -> dict[str, str]:
    if not path.exists():
        return {}
    root = ET.parse(path).getroot()
    ns = {"x": NS}
    result: dict[str, str] = {}
    for unit in root.findall(".//x:trans-unit", ns):
        source = unit.find("x:source", ns)
        target = unit.find("x:target", ns)
        if source is None or not (source.text or ""):
            continue
        result[source.text or ""] = (target.text or "") if target is not None else ""
    return result


def load_targets(path: Path, sources: list[str]) -> dict[str, str]:
    lines = path.read_text(encoding="utf-8").splitlines()
    if len(lines) != len(sources):
        raise ValueError(f"{path}: expected {len(sources)} lines, got {len(lines)}")
    result: dict[str, str] = {}
    for expected, (line, source) in enumerate(zip(lines, sources), 1):
        match = re.fullmatch(r"(\d{3})\t(.+)", line)
        if not match:
            raise ValueError(f"{path}:{expected}: expected NNN<TAB>translation")
        index = int(match.group(1))
        if index != expected:
            raise ValueError(f"{path}:{expected}: found index {index:03d}")
        target = match.group(2).strip()
        if not target:
            raise ValueError(f"{path}:{expected}: empty translation")
        result[source] = target
    return result


def placeholders(value: str) -> tuple[str, ...]:
    return tuple(sorted(re.findall(r"%[A-Za-z0-9_]+%|\{[A-Za-z0-9_]+\}", value)))


def write_xlf(locale: str, entries: dict[str, str]) -> Path:
    directory = TRANSLATIONS / locale
    directory.mkdir(parents=True, exist_ok=True)
    path = directory / f"{DOMAIN}.{locale}.xlf"

    xliff = ET.Element(f"{{{NS}}}xliff", {"version": "1.2"})
    file_node = ET.SubElement(
        xliff,
        f"{{{NS}}}file",
        {
            "source-language": "en-US",
            "target-language": locale,
            "datatype": "plaintext",
            "original": "file.ext",
        },
    )
    body = ET.SubElement(file_node, f"{{{NS}}}body")
    for source in sorted(entries, key=lambda value: value.casefold()):
        target = entries[source]
        unit = ET.SubElement(
            body,
            f"{{{NS}}}trans-unit",
            {"id": hashlib.md5(source.encode("utf-8")).hexdigest(), "resname": source},
        )
        source_node = ET.SubElement(unit, f"{{{NS}}}source")
        source_node.text = source
        target_node = ET.SubElement(unit, f"{{{NS}}}target")
        target_node.text = target

    indent(xliff)
    tree = ET.ElementTree(xliff)
    tree.write(path, encoding="UTF-8", xml_declaration=True)
    # ElementTree emits a compact declaration compatible with PrestaShop.
    return path


def main() -> int:
    sources = json.loads(SOURCES_FILE.read_text(encoding="utf-8"))
    if not isinstance(sources, list) or not all(isinstance(value, str) for value in sources):
        raise ValueError("admin_settings_sources.json must contain a string list")

    locales = sorted(path.name for path in TRANSLATIONS.iterdir() if path.is_dir())
    reports: list[str] = []
    for locale in locales:
        path = TRANSLATIONS / locale / f"{DOMAIN}.{locale}.xlf"
        entries = parse_xlf(path)

        if locale in COPY_CATALOGUE:
            source_locale = COPY_CATALOGUE[locale]
            source_path = TRANSLATIONS / source_locale / f"{DOMAIN}.{source_locale}.xlf"
            entries = parse_xlf(source_path)

        target_file = TARGET_DIR / f"{locale}.txt"
        if target_file.exists():
            entries.update(load_targets(target_file, sources))
        elif locale in ENGLISH_VARIANTS:
            entries.update({source: source for source in sources})
        elif locale in REVIEWED_OVERRIDES:
            # Preserve the reviewed full catalogue and add newly introduced
            # settings strings. Fail if any requested setting is still absent.
            entries.update(REVIEWED_OVERRIDES[locale])
        elif locale == "fr-CA":
            # Canadian French reuses the reviewed French catalogue. Locale-
            # specific wording can be adjusted independently later.
            entries.update(REVIEWED_OVERRIDES["fr-FR"])
        else:
            raise ValueError(f"Missing target file for {locale}")

        missing = [source for source in sources if not entries.get(source, "").strip()]
        if missing:
            raise ValueError(f"{locale}: {len(missing)} settings translations missing: {missing[:3]}")

        for source in sources:
            target = entries[source]
            if placeholders(source) != placeholders(target):
                raise ValueError(
                    f"{locale}: placeholder mismatch for {source!r}: "
                    f"{placeholders(source)} != {placeholders(target)}"
                )

        output = write_xlf(locale, entries)
        reports.append(f"{locale}: {len(entries)} units -> {output.relative_to(ROOT)}")

    print("\n".join(reports))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        raise SystemExit(1)
