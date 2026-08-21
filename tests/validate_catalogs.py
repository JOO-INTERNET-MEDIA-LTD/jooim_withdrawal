#!/usr/bin/env python3
"""Validate JOOIM Withdrawal translation catalogues and localized mail templates.

The validation distinguishes two scopes:

* complete customer-facing catalogues: en-US, sk-SK, cs-CZ, de-DE, fr-FR,
  pl-PL (Admin, Shop, Email, Pdf and Validation);
* Back Office settings catalogues: all packaged EU/US/Canada/Ukrainian locales.

This prevents a settings-only catalogue from being misrepresented as a complete
front-office/legal translation while still enforcing the requested Back Office
coverage for every packaged locale.
"""

from __future__ import annotations

import json
from pathlib import Path
import re
import sys
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
TRANSLATIONS = ROOT / "translations"
ADMIN_SOURCES_FILE = ROOT / "tools" / "admin_settings_sources.json"
STATUS_LABELS_FILE = ROOT / "tools" / "status_label_translations.json"
FULL_LOCALES = ["en-US", "sk-SK", "cs-CZ", "de-DE", "fr-FR", "pl-PL"]
ADMIN_LOCALES = [
    "bg-BG", "cs-CZ", "da-DK", "de-DE", "el-GR", "en-CA", "en-GB", "en-US",
    "es-ES", "et-EE", "fi-FI", "fr-CA", "fr-FR", "ga-IE", "hr-HR", "hu-HU",
    "it-IT", "lt-LT", "lv-LV", "mt-MT", "nl-NL", "pl-PL", "pt-PT", "ro-RO",
    "sk-SK", "sl-SI", "sv-SE", "uk-UA",
]
FULL_DOMAINS = ["Admin", "Shop", "Email", "Pdf", "Validation"]
XLIFF_NS = {"x": "urn:oasis:names:tc:xliff:document:1.2"}
PLACEHOLDER_RE = re.compile(r"%(?:[A-Za-z_][A-Za-z0-9_]*|\d+)%|\{[A-Za-z_][A-Za-z0-9_]*\}")
MAIL_VARIABLE_RE = re.compile(r"\{[A-Za-z_][A-Za-z0-9_]*\}")

# Identical source/target values that are natural or technical in the reviewed
# complete catalogues. Settings-only locales are validated by coverage,
# placeholders and a long-string fallback gate instead of a brittle word list.
ALLOWED_IDENTICAL = {
    ("sk-SK", "Admin"): {"Filter", "IP / 15 min", "JOOIM Withdrawal", "UTC"},
    ("cs-CZ", "Admin"): {"IP / 15 min", "JOOIM Withdrawal", "Reference", "UTC"},
    ("cs-CZ", "Pdf"): {"Reference"},
    ("cs-CZ", "Shop"): {"Reference", "Reference:"},
    ("de-DE", "Admin"): {"Filter", "JOOIM Withdrawal", "Shop", "Status", "Status:", "UTC"},
    ("de-DE", "Shop"): {"Name", "Status:"},
    ("fr-FR", "Admin"): {"Date", "IP / 15 min", "JOOIM Withdrawal", "Page", "Transaction", "UTC"},
    ("pl-PL", "Admin"): {"IP / 15 min", "JOOIM Withdrawal", "Status", "Status:", "UTC"},
    ("pl-PL", "Shop"): {"Status:"},
}


def catalogue_path(locale: str, domain: str) -> Path:
    return TRANSLATIONS / locale / f"ModulesJooimwithdrawal{domain}.{locale}.xlf"


def placeholders(value: str) -> list[str]:
    return sorted(PLACEHOLDER_RE.findall(value))


def load_catalogue(path: Path) -> tuple[dict[str, str], str, str]:
    root = ET.parse(path).getroot()
    file_node = root.find("x:file", XLIFF_NS)
    if file_node is None:
        raise ValueError("missing XLIFF file node")

    entries: dict[str, str] = {}
    for unit in root.findall(".//x:trans-unit", XLIFF_NS):
        source_node = unit.find("x:source", XLIFF_NS)
        target_node = unit.find("x:target", XLIFF_NS)
        source = "" if source_node is None or source_node.text is None else source_node.text
        target = "" if target_node is None or target_node.text is None else target_node.text
        if not source:
            raise ValueError("empty source string")
        if source in entries:
            raise ValueError(f"duplicate source string: {source!r}")
        entries[source] = target

    return (
        entries,
        file_node.attrib.get("source-language", ""),
        file_node.attrib.get("target-language", ""),
    )


def validate_all_xliff(errors: list[str]) -> int:
    paths = sorted(TRANSLATIONS.glob("*/*.xlf"))
    expected_locales = set(ADMIN_LOCALES)
    actual_locales = {path.parent.name for path in paths}
    if actual_locales != expected_locales:
        errors.append(
            "translation locale set mismatch; "
            f"missing={sorted(expected_locales - actual_locales)!r}, "
            f"extra={sorted(actual_locales - expected_locales)!r}"
        )

    for path in paths:
        locale = path.parent.name
        try:
            entries, source_language, target_language = load_catalogue(path)
        except Exception as exc:  # noqa: BLE001 - release validation reports every failure.
            errors.append(f"{path.relative_to(ROOT)}: {exc}")
            continue

        if source_language != "en-US":
            errors.append(f"{path.relative_to(ROOT)}: source-language must be en-US")
        if target_language != locale:
            errors.append(
                f"{path.relative_to(ROOT)}: target-language is {target_language!r}, expected {locale!r}"
            )
        if not entries:
            errors.append(f"{path.relative_to(ROOT)}: catalogue contains no translation units")

        for source, target in entries.items():
            if target == "":
                errors.append(f"{path.relative_to(ROOT)}: empty target for {source!r}")
            if placeholders(source) != placeholders(target):
                errors.append(
                    f"{path.relative_to(ROOT)}: placeholder mismatch for {source!r} -> {target!r}"
                )

    return len(paths)


def validate_complete_catalogues(errors: list[str]) -> None:
    for domain in FULL_DOMAINS:
        catalogues: dict[str, dict[str, str]] = {}
        for locale in FULL_LOCALES:
            path = catalogue_path(locale, domain)
            if not path.is_file():
                errors.append(f"missing complete catalogue: {path.relative_to(ROOT)}")
                continue
            try:
                entries, _, _ = load_catalogue(path)
            except Exception:
                # The generic XLIFF pass already records the parse detail.
                continue
            catalogues[locale] = entries

            if locale != "en-US":
                identical = {source for source, target in entries.items() if source == target}
                allowed = ALLOWED_IDENTICAL.get((locale, domain), set())
                unexpected = identical - allowed
                missing_expected = allowed - identical
                if unexpected:
                    errors.append(
                        f"{path.relative_to(ROOT)}: unexpected English fallback(s): {sorted(unexpected)!r}"
                    )
                if missing_expected:
                    errors.append(
                        f"{path.relative_to(ROOT)}: allowed-identical list is stale: {sorted(missing_expected)!r}"
                    )

        source_catalogue = catalogues.get("en-US")
        if source_catalogue is None:
            continue
        source_set = set(source_catalogue)
        for locale in FULL_LOCALES[1:]:
            current_catalogue = catalogues.get(locale)
            if current_catalogue is None:
                continue
            current = set(current_catalogue)
            if current != source_set:
                errors.append(
                    f"{domain}/{locale}: source set mismatch; "
                    f"missing={sorted(source_set - current)!r}, extra={sorted(current - source_set)!r}"
                )


def validate_admin_settings(errors: list[str]) -> int:
    try:
        required_sources = json.loads(ADMIN_SOURCES_FILE.read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        errors.append(f"{ADMIN_SOURCES_FILE.relative_to(ROOT)}: {exc}")
        return 0

    if not isinstance(required_sources, list) or not all(isinstance(value, str) for value in required_sources):
        errors.append("tools/admin_settings_sources.json must contain a list of strings")
        return 0
    if len(required_sources) != len(set(required_sources)):
        errors.append("tools/admin_settings_sources.json contains duplicate source strings")

    english_variants = {"en-US", "en-GB", "en-CA"}
    for locale in ADMIN_LOCALES:
        path = catalogue_path(locale, "Admin")
        if not path.is_file():
            errors.append(f"missing Back Office settings catalogue: {path.relative_to(ROOT)}")
            continue
        try:
            entries, _, _ = load_catalogue(path)
        except Exception:
            continue

        missing = [source for source in required_sources if source not in entries]
        if missing:
            errors.append(
                f"{path.relative_to(ROOT)}: missing {len(missing)} required settings strings: {missing[:5]!r}"
            )

        for source in required_sources:
            if source not in entries:
                continue
            target = entries[source]
            if not target.strip():
                errors.append(f"{path.relative_to(ROOT)}: empty settings target for {source!r}")
            if placeholders(source) != placeholders(target):
                errors.append(
                    f"{path.relative_to(ROOT)}: settings placeholder mismatch for {source!r} -> {target!r}"
                )
            # A long unchanged English sentence is not a credible translation.
            # Short technical terms can legitimately remain identical.
            if locale not in english_variants and len(source) >= 24 and source == target:
                errors.append(
                    f"{path.relative_to(ROOT)}: untranslated long settings string: {source!r}"
                )

    return len(required_sources)



def validate_status_labels(errors: list[str]) -> tuple[int, int]:
    try:
        data = json.loads(STATUS_LABELS_FILE.read_text(encoding="utf-8"))
    except Exception as exc:  # noqa: BLE001
        errors.append(f"{STATUS_LABELS_FILE.relative_to(ROOT)}: {exc}")
        return 0, 0

    status_sources = list((data.get("status_sources") or {}).values())
    event_sources = list((data.get("event_sources") or {}).values())
    translations = data.get("translations") or {}
    admin_locales = data.get("admin_locales") or []
    shop_locales = data.get("shop_locales") or []
    email_locales = data.get("email_locales") or []

    if len(status_sources) != 11 or len(set(status_sources)) != 11:
        errors.append("status_label_translations.json must contain 11 unique status sources")
    if len(event_sources) != 4 or len(set(event_sources)) != 4:
        errors.append("status_label_translations.json must contain 4 unique event sources")
    if set(admin_locales) != set(ADMIN_LOCALES) or set(shop_locales) != set(ADMIN_LOCALES):
        errors.append("status label Admin/Shop locale coverage mismatch")
    if set(email_locales) != set(FULL_LOCALES):
        errors.append("status label Email locale coverage mismatch")

    for locale in ADMIN_LOCALES:
        locale_targets = translations.get(locale) or {}
        for source in status_sources + event_sources:
            if not str(locale_targets.get(source, "")).strip():
                errors.append(f"{locale}: missing deterministic status/event target for {source!r}")

        for domain, required in (("Admin", status_sources + event_sources), ("Shop", status_sources)):
            path = catalogue_path(locale, domain)
            try:
                entries, _, _ = load_catalogue(path)
            except Exception:
                continue
            for source in required:
                expected = str(locale_targets.get(source, ""))
                if entries.get(source) != expected:
                    errors.append(f"{path.relative_to(ROOT)}: wrong status/event translation for {source!r}")

    for locale in FULL_LOCALES:
        path = catalogue_path(locale, "Email")
        try:
            entries, _, _ = load_catalogue(path)
        except Exception:
            continue
        locale_targets = translations.get(locale) or {}
        for source in status_sources:
            if entries.get(source) != str(locale_targets.get(source, "")):
                errors.append(f"{path.relative_to(ROOT)}: wrong email status translation for {source!r}")

    return len(status_sources), len(event_sources)

def validate_mail_templates(errors: list[str]) -> int:
    languages = ["en", "sk", "cs", "de", "fr", "pl"]
    total = 0
    for extension in ("html", "txt"):
        source_files = sorted((ROOT / "mails" / "en").glob(f"*.{extension}"))
        for source_file in source_files:
            source_variables = set(MAIL_VARIABLE_RE.findall(source_file.read_text(encoding="utf-8")))
            for language in languages:
                path = ROOT / "mails" / language / source_file.name
                if not path.is_file():
                    errors.append(f"missing mail template: {path.relative_to(ROOT)}")
                    continue
                total += 1
                variables = set(MAIL_VARIABLE_RE.findall(path.read_text(encoding="utf-8")))
                if variables != source_variables:
                    errors.append(
                        f"{path.relative_to(ROOT)}: mail variable mismatch; "
                        f"missing={sorted(source_variables - variables)!r}, "
                        f"extra={sorted(variables - source_variables)!r}"
                    )
    return total


def main() -> int:
    errors: list[str] = []
    xlf_count = validate_all_xliff(errors)
    validate_complete_catalogues(errors)
    admin_source_count = validate_admin_settings(errors)
    status_count, event_count = validate_status_labels(errors)
    mail_count = validate_mail_templates(errors)

    if errors:
        print(f"FAIL: {len(errors)}")
        for error in errors:
            print(f" - {error}")
        return 1

    print(
        "PASS: "
        f"{xlf_count} XLIFF files, "
        f"{len(ADMIN_LOCALES)} Back Office locales × {admin_source_count} settings strings, "
        f"{status_count} localized statuses + {event_count} localized audit events, "
        f"{mail_count} localized mail files"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
