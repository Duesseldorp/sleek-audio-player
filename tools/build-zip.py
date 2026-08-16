#!/usr/bin/env python3
"""Build the distribution ZIP for wordpress.org submission / releases.

Usage:  python tools/build-zip.py
Output: dist/sleek-audio-player-<version>.zip

Uses a whitelist so development files (tools/, docs, dotfiles, .github)
can never leak into the shipped plugin. Run the asset builds first if
sources changed (tools/minify.py, tools/po2mo.py).
"""
import re
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SLUG = "sleek-audio-player"

# Everything the shipped plugin consists of - nothing else goes in
INCLUDE = [
    "sleek-audio-player.php",
    "includes/*.php",
    "uninstall.php",
    "readme.txt",
    "assets/css/*.css",
    "assets/js/*.js",
    "languages/*.pot",
    "languages/*.po",
    "languages/*.mo",
    # JavaScript translations for the block editor. Without this line the file
    # exists in the repository, CI is green, and the block editor is English in
    # every installed copy.
    "languages/*.json",
]

version = re.search(
    r"^ \* Version:\s*([\d.]+)", (ROOT / "sleek-audio-player.php").read_text(encoding="utf-8"), re.M
).group(1)

dist = ROOT / "dist"
dist.mkdir(exist_ok=True)
zip_path = dist / f"{SLUG}-{version}.zip"

files = sorted(f for pattern in INCLUDE for f in ROOT.glob(pattern) if f.is_file())

with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as z:
    for f in files:
        z.write(f, f"{SLUG}/{f.relative_to(ROOT).as_posix()}")

print(f"{zip_path.name} ({zip_path.stat().st_size / 1024:.0f} KB, {len(files)} files):")
for f in files:
    print(f"  {SLUG}/{f.relative_to(ROOT).as_posix()}")
