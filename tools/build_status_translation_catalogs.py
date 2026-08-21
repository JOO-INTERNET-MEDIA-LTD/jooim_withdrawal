#!/usr/bin/env python3
"""Merge deterministic localized withdrawal status labels into XLIFF files."""
from __future__ import annotations

import hashlib
import json
from pathlib import Path
from xml.etree import ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
TRANSLATIONS = ROOT / "translations"
DATA = json.loads((ROOT / "tools" / "status_label_translations.json").read_text(encoding="utf-8"))
NS = "urn:oasis:names:tc:xliff:document:1.2"
ET.register_namespace("", NS)


def parse_xlf(path: Path) -> dict[str, str]:
    root = ET.parse(path).getroot()
    ns = {"x": NS}
    entries: dict[str, str] = {}
    for unit in root.findall(".//x:trans-unit", ns):
        source = unit.find("x:source", ns)
        target = unit.find("x:target", ns)
        if source is not None and (source.text or ""):
            entries[source.text or ""] = (target.text or "") if target is not None else ""
    return entries


def write_xlf(path: Path, locale: str, entries: dict[str, str]) -> None:
    xliff = ET.Element(f"{{{NS}}}xliff", {"version": "1.2"})
    file_node = ET.SubElement(xliff, f"{{{NS}}}file", {
        "source-language": "en-US",
        "target-language": locale,
        "datatype": "plaintext",
        "original": "file.ext",
    })
    body = ET.SubElement(file_node, f"{{{NS}}}body")
    for source in sorted(entries, key=str.casefold):
        unit = ET.SubElement(body, f"{{{NS}}}trans-unit", {
            "id": hashlib.md5(source.encode("utf-8")).hexdigest(),
            "resname": source,
        })
        ET.SubElement(unit, f"{{{NS}}}source").text = source
        ET.SubElement(unit, f"{{{NS}}}target").text = entries[source]
    ET.indent(xliff, space="  ")
    ET.ElementTree(xliff).write(path, encoding="UTF-8", xml_declaration=True)


def merge(locale: str, domain: str, sources: list[str]) -> None:
    path = TRANSLATIONS / locale / f"ModulesJooimwithdrawal{domain}.{locale}.xlf"
    if not path.exists():
        raise RuntimeError(f"Missing catalogue: {path.relative_to(ROOT)}")
    entries = parse_xlf(path)
    targets = DATA["translations"][locale]
    for source in sources:
        target = targets.get(source, "")
        if not target:
            raise RuntimeError(f"Missing {locale} target for {source!r}")
        entries[source] = target
    write_xlf(path, locale, entries)


def main() -> int:
    status_sources = list(DATA["status_sources"].values())
    event_sources = list(DATA["event_sources"].values())
    for locale in DATA["admin_locales"]:
        merge(locale, "Admin", status_sources + event_sources)
    for locale in DATA["shop_locales"]:
        merge(locale, "Shop", status_sources)
    for locale in DATA["email_locales"]:
        merge(locale, "Email", status_sources)
    print(
        f"PASS: {len(DATA['admin_locales'])} Admin locales, "
        f"{len(DATA['shop_locales'])} Shop locales, "
        f"{len(DATA['email_locales'])} Email locales"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
