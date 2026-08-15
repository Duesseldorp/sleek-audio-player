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
    """Read a .po into {original: translation}.

    Plural entries are stored the way the MO format and WordPress's reader
    (wp-includes/pomo/mo.php) expect them: the singular and plural forms are
    joined by a NUL byte on both sides, e.g. "%s Track\\0%s Tracks" mapped to
    "%s Titel\\0%s Titel". Without this the entry is dropped and the string
    silently stays English.
    """
    entries = {}
    msgid = plural = None
    msgstrs = {}
    section = None

    def flush():
        nonlocal msgid, plural, msgstrs, section
        if msgid is not None and msgstrs:
            if plural is not None:
                key = msgid + "\x00" + plural
                value = "\x00".join(msgstrs[i] for i in sorted(msgstrs))
            else:
                key = msgid
                value = msgstrs.get(0, "")
            entries[key] = value
        msgid = plural = section = None
        msgstrs = {}

    with open(path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line.startswith("#") or not line:
                flush()
                continue
            if line.startswith("msgid_plural "):
                plural = eval(line[13:], {}, {})  # noqa: S307 - PO string literal
                section = "plural"
            elif line.startswith("msgid "):
                flush()
                msgid = eval(line[6:], {}, {})  # noqa: S307
                section = "msgid"
            elif line.startswith("msgstr["):
                index = int(line[7:line.index("]")])
                msgstrs[index] = eval(line[line.index("]") + 2:], {}, {})  # noqa: S307
                section = ("msgstr", index)
            elif line.startswith("msgstr "):
                msgstrs[0] = eval(line[7:], {}, {})  # noqa: S307
                section = ("msgstr", 0)
            elif line.startswith('"'):
                s = eval(line, {}, {})  # noqa: S307
                if section == "msgid":
                    msgid += s
                elif section == "plural":
                    plural += s
                elif isinstance(section, tuple):
                    msgstrs[section[1]] += s
    flush()
    return {k: v for k, v in entries.items() if v.strip("\x00")}


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
