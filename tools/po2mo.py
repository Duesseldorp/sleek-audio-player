#!/usr/bin/env python3
"""Compile all .po translation files in languages/ to binary .mo files.

Usage:  python tools/po2mo.py
Needs:  nothing beyond the Python standard library

WordPress only loads compiled .mo files - editing a .po without running
this script means the translation change never reaches the site.
"""
import struct
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
LANG_DIR = ROOT / "languages"


def parse_po(path):
    entries = {}
    msgid = msgstr = None
    section = None
    with open(path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line.startswith("#") or not line:
                if msgid is not None and section == "msgstr":
                    entries[msgid] = msgstr
                    msgid = msgstr = section = None
                continue
            if line.startswith("msgid "):
                if msgid is not None and section == "msgstr":
                    entries[msgid] = msgstr
                msgid = eval(line[6:], {}, {})  # noqa: S307 - PO string literal
                section = "msgid"
            elif line.startswith("msgstr "):
                msgstr = eval(line[7:], {}, {})  # noqa: S307
                section = "msgstr"
            elif line.startswith('"'):
                s = eval(line, {}, {})  # noqa: S307
                if section == "msgid":
                    msgid += s
                elif section == "msgstr":
                    msgstr += s
    if msgid is not None and section == "msgstr":
        entries[msgid] = msgstr
    return {k: v for k, v in entries.items() if v}


def write_mo(entries, path):
    keys = sorted(entries.keys())
    offsets = []
    ids = strs = b""
    for k in keys:
        kb, vb = k.encode("utf-8"), entries[k].encode("utf-8")
        offsets.append((len(ids), len(kb), len(strs), len(vb)))
        ids += kb + b"\x00"
        strs += vb + b"\x00"
    n = len(keys)
    keystart = 7 * 4 + 16 * n
    valuestart = keystart + len(ids)
    koffsets, voffsets = [], []
    for o1, l1, o2, l2 in offsets:
        koffsets += [l1, o1 + keystart]
        voffsets += [l2, o2 + valuestart]
    output = struct.pack("Iiiiiii", 0x950412DE, 0, n, 7 * 4, 7 * 4 + n * 8, 0, 0)
    output += struct.pack("i" * n * 2, *koffsets)
    output += struct.pack("i" * n * 2, *voffsets)
    output += ids + strs
    path.write_bytes(output)


if __name__ == "__main__":
    for po in sorted(LANG_DIR.glob("*.po")):
        mo = po.with_suffix(".mo")
        entries = parse_po(po)
        write_mo(entries, mo)
        print(f"{po.name}: {len(entries)} translated entries -> {mo.name}")
