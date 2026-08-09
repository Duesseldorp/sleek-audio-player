#!/usr/bin/env python3
"""Build minified frontend assets for Sleek Audio Player.

Usage:  python tools/minify.py
Needs:  pip install rjsmin rcssmin

The .min files are committed to the repo (WordPress installs have no build
step). Run this after every change to player.js / player.css - the plugin
serves the .min variants unless SCRIPT_DEBUG is enabled.
"""
from pathlib import Path

import rcssmin
import rjsmin

ROOT = Path(__file__).resolve().parent.parent

TARGETS = {
    "assets/js/player.js": "assets/js/player.min.js",
    "assets/css/player.css": "assets/css/player.min.css",
}

for src, dst in TARGETS.items():
    source = (ROOT / src).read_text(encoding="utf-8")
    minified = rjsmin.jsmin(source) if src.endswith(".js") else rcssmin.cssmin(source)
    (ROOT / dst).write_text(minified, encoding="utf-8", newline="\n")
    print(f"{src}: {len(source) / 1024:.0f} KB -> {dst}: {len(minified) / 1024:.0f} KB")
