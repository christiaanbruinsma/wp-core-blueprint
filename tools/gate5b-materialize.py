#!/usr/bin/env python3
"""One-time Gate 5B catalog materializer.

Rebuilds the reviewed WordPress-native PHP catalogs from the current runtime
gettext source, the previously shipped MO catalogs, and the compact reviewed
repair manifest. The script is intentionally deterministic and is removed from
the branch after the one-time materialization commit is complete.
"""
from __future__ import annotations

import base64
import gettext
import lzma
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LANG = ROOT / "languages"
REPAIR_FILE = "gate5b-repairs.xz.b64"
LOCALES = ("nl_NL", "de_DE", "fr_FR", "es_ES", "it_IT", "pt_PT")
PLURALS = {
    "nl_NL": "nplurals=2; plural=(n != 1);",
    "de_DE": "nplurals=2; plural=(n != 1);",
    "fr_FR": "nplurals=2; plural=(n > 1);",
    "es_ES": "nplurals=2; plural=(n != 1);",
    "it_IT": "nplurals=2; plural=(n != 1);",
    "pt_PT": "nplurals=2; plural=(n != 1);",
}


def fail(message: str) -> "NoReturn":
    raise SystemExit(f"[gate5b] ERROR: {message}")


def php_single(value: str) -> str:
    # PHP single-quoted strings may contain literal newlines. Escaping only the
    # two special characters preserves source/runtime bytes exactly.
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def load_old_mo(locale: str) -> dict[str, str]:
    path = LANG / f"core-blueprint-{locale}.mo"
    if not path.is_file():
        fail(f"Missing previous {locale} MO catalog: {path}")
    with path.open("rb") as handle:
        catalog = gettext.GNUTranslations(handle)._catalog

    messages: dict[str, str] = {}
    plural_forms: dict[str, dict[int, str]] = {}
    for key, value in catalog.items():
        if key == "":
            continue
        if isinstance(key, tuple):
            msgid, index = key
            plural_forms.setdefault(str(msgid), {})[int(index)] = str(value)
        else:
            messages[str(key)] = str(value)
    for key, forms in plural_forms.items():
        messages[key] = "\0".join(forms[index] for index in sorted(forms))
    return messages


def render_catalog(locale: str, messages: dict[str, str]) -> str:
    lines = [
        "<?php",
        "return [",
        "    'project-id-version' => 'Core Blueprint 1.0.0-rc1',",
        f"    'language' => '{locale}',",
        f"    'plural-forms' => '{PLURALS[locale]}',",
        "    'content-type' => 'text/plain; charset=UTF-8',",
        "    'x-generator' => 'Core Blueprint Gate 5B localization workflow',",
        "    'messages' => [",
    ]
    for key in sorted(messages):
        value = messages[key]
        if "\0" in value:
            rhs = ' . "\\0" . '.join(php_single(part) for part in value.split("\0"))
        else:
            rhs = php_single(value)
        if "\4" in key:
            context, msgid = key.split("\4", 1)
            lhs = php_single(context) + ' . "\\4" . ' + php_single(msgid)
        else:
            lhs = php_single(key)
        lines.append(f"        {lhs} => {rhs},")
    lines.extend(("    ],", "];", ""))
    return "\n".join(lines)


def main() -> int:
    source_raw = subprocess.check_output(
        ["php", str(ROOT / "tools" / "check-translations.php"), "--export-source"],
        text=True,
        encoding="utf-8",
    )
    source = json.loads(source_raw)
    if len(source) != 3216:
        fail(f"Expected 3216 source keys, got {len(source)}")

    repair_file = ROOT / "tools" / REPAIR_FILE
    if not repair_file.is_file():
        fail("Gate 5B repair manifest is missing.")
    encoded_repairs = repair_file.read_text(encoding="ascii").strip()
    repairs = json.loads(lzma.decompress(base64.b64decode(encoded_repairs)).decode("utf-8"))
    if sorted(repairs) != sorted(LOCALES):
        fail("Repair manifest locale set does not match shipped locales.")

    for locale in LOCALES:
        previous = load_old_mo(locale)
        reviewed = repairs[locale]
        messages: dict[str, str] = {}
        for key in source:
            if key in reviewed:
                value = reviewed[key]
            elif key in previous:
                value = previous[key]
            else:
                fail(f"{locale}: no reviewed or previous translation for {key!r}")
            if not isinstance(value, str) or value == "":
                fail(f"{locale}: empty translation for {key!r}")
            messages[str(key)] = value

        extra_repairs = set(reviewed) - set(source)
        if extra_repairs:
            fail(f"{locale}: repair manifest contains {len(extra_repairs)} stale keys")

        output = LANG / f"core-blueprint-{locale}.l10n.php"
        output.write_text(render_catalog(locale, messages), encoding="utf-8")
        print(f"[gate5b] {locale}: {len(messages)} messages -> {output.name}")

    for pattern in ("*.po", "*.mo", "*.pot"):
        for legacy in LANG.glob(pattern):
            legacy.unlink()

    # This is a one-time branch materializer. Remove all temporary bootstrap
    # files in the same generated commit so main receives only permanent
    # localization tooling, tests and runtime catalogs.
    for temporary in (
        ROOT / "tools" / "gate5b-materialize.py",
        ROOT / "tools" / REPAIR_FILE,
        ROOT / ".github" / "workflows" / "gate5b-materialize.yml",
    ):
        if temporary.exists():
            temporary.unlink()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
