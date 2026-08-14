#!/usr/bin/env python3
"""Build minified frontend assets for Sleek Audio Player.

Usage:  python tools/minify.py
Needs:  pip install rjsmin rcssmin

The .min files are committed to the repo (WordPress installs have no build
step). Run this after every change to player.js / player.css - the plugin
serves the .min variants unless SCRIPT_DEBUG is enabled.
"""
import re
from pathlib import Path

import rcssmin
import rjsmin

ROOT = Path(__file__).resolve().parent.parent

TARGETS = {
    "assets/js/player.js": "assets/js/player.min.js",
    "assets/css/player.css": "assets/css/player.min.css",
}

# Minifiers strip all comments, so the shipped files would otherwise carry no
# attribution at all - even though these are the files visitors actually load.
BANNER = (
    "/*! Sleek Audio Player v{version} | (c) 2025-2026 Martin Gräbing | "
    "GPL-2.0-or-later | https://github.com/Duesseldorp/sleek-audio-player */\n"
)

version = re.search(
    r"^ \* Version:\s*([\d.]+)",
    (ROOT / "sleek-audio-player.php").read_text(encoding="utf-8"),
    re.M,
).group(1)

for src, dst in TARGETS.items():
    source = (ROOT / src).read_text(encoding="utf-8")
    minified = rjsmin.jsmin(source) if src.endswith(".js") else rcssmin.cssmin(source)
    output = BANNER.format(version=version) + minified
    (ROOT / dst).write_text(output, encoding="utf-8", newline="\n")
    print(f"{src}: {len(source) / 1024:.0f} KB -> {dst}: {len(output) / 1024:.0f} KB")
