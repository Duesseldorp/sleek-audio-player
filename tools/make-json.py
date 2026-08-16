#!/usr/bin/env python3
"""Compile the JavaScript half of each translation into the .json files
WordPress loads for scripts.

Usage:  python tools/make-json.py
Needs:  nothing beyond the Python standard library

WordPress delivers translations to PHP through the .mo, but to JavaScript
through a separate JSON file per script. Without it the block editor's labels
stay English no matter how complete the .po is - the strings are marked up with
wp.i18n.__() and simply never receive anything.

The filename is not free: load_script_textdomain() looks for

    {text-domain}-{locale}-{md5 of the script path relative to the plugin}.json

so the md5 below must match the path used in wp_register_script(). Change one
and the file is silently ignored - it does not warn, the labels just stay
English. That is also what tools/build-zip.py must ship, and what
translate.wordpress.org would generate automatically if the plugin were listed.
"""
import hashlib
import json
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
LANG_DIR = ROOT / "languages"
DOMAIN = "sleek-audio-player"

# Scripts that call wp.i18n themselves, with their path as registered
SCRIPTS = ["assets/js/block.js"]

CALL = re.compile(r"\b__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'" + re.escape(DOMAIN) + r"'\s*\)")


def parse_po(path):
    """msgid -> msgstr for simple (non-plural) entries."""
    entries = {}
    msgid = msgstr = None
    section = None
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            if msgid is not None and section == "msgstr":
                entries[msgid] = msgstr
            msgid = msgstr = section = None
            continue
        if line.startswith("msgid_plural "):
            section = "skip"
        elif line.startswith("msgid "):
            if msgid is not None and section == "msgstr":
                entries[msgid] = msgstr
            msgid = json.loads(line[6:])
            section = "msgid"
        elif line.startswith("msgstr "):
            if section != "skip":
                msgstr = json.loads(line[7:])
                section = "msgstr"
        elif line.startswith('"') and section in ("msgid", "msgstr"):
            piece = json.loads(line)
            if section == "msgid":
                msgid += piece
            else:
                msgstr += piece
    if msgid is not None and section == "msgstr":
        entries[msgid] = msgstr
    return entries


def main():
    written = 0
    for po_path in sorted(LANG_DIR.glob(f"{DOMAIN}-*.po")):
        locale = po_path.stem[len(DOMAIN) + 1:]
        translations = parse_po(po_path)

        for script in SCRIPTS:
            source = (ROOT / script).read_text(encoding="utf-8")
            used = [m.group(1).replace("\\'", "'") for m in CALL.finditer(source)]

            messages = {}
            missing = []
            for text in dict.fromkeys(used):  # unique, order preserved
                translated = translations.get(text)
                if translated:
                    messages[text] = [translated]
                else:
                    missing.append(text)

            if missing:
                print(f"  {locale}: no translation yet for {len(missing)} string(s) in {script}")
                for text in missing:
                    print(f"    - {text}")

            payload = {
                "translation-revision-date": datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S+0000"),
                "generator": "tools/make-json.py",
                "source": script,
                "domain": "messages",
                "locale_data": {
                    "messages": {
                        "": {
                            "domain": "messages",
                            "lang": locale,
                            "plural-forms": "nplurals=2; plural=(n != 1);",
                        },
                        **messages,
                    }
                },
            }

            digest = hashlib.md5(script.encode("utf-8")).hexdigest()
            out = LANG_DIR / f"{DOMAIN}-{locale}-{digest}.json"
            out.write_text(json.dumps(payload, ensure_ascii=False, indent=1), encoding="utf-8")
            print(f"{po_path.name} + {script} -> {out.name} ({len(messages)} strings)")
            written += 1

    if not written:
        sys.exit("make-json: no .po files found in languages/")


if __name__ == "__main__":
    main()
