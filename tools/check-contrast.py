#!/usr/bin/env python3
"""Check the default theme's colours against WCAG 2.1 contrast minimums.

Usage:  python tools/check-contrast.py
Needs:  nothing beyond the Python standard library

Run by CI, so "the default theme meets AA" is a checked fact rather than a
claim in a readme. It reads the :root variables out of assets/css/player.css,
composites any alpha over the surface behind it and computes the ratio.

What it cannot check, and what no automated tool can:
  - custom themes. The Theme Manager lets anyone pick their own colours, and
    nothing stops them from picking an unreadable pair. Only the shipped
    default is verified here.
  - text over cover art. The player draws no text on the cover, so this does
    not currently arise.
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CSS = ROOT / "assets" / "css" / "player.css"

# (label, foreground variable, background variable, minimum ratio)
# 4.5:1 is WCAG 2.1 AA for normal text, 3:1 for large text and for the visual
# boundary of user interface components.
CHECKS = [
    ("Track title", "sap-white", "sap-card", 4.5),
    ("Track title in playlist", "sap-gray-100", "sap-card", 4.5),
    ("Button icons and subtitle", "sap-gray-200", "sap-card", 4.5),
    ("Artist and time display", "sap-gray-300", "sap-card", 4.5),
    ("Active toggles (accent)", "sap-accent", "sap-card", 4.5),
    ("Progress bar and visualizer", "sap-visualizer", "sap-card", 3.0),
]

# Reported but not enforced. Raising these to 4.5:1 is a visible design change
# and belongs to whoever owns the look, not to this script.
KNOWN = [
    ("Track numbers", "sap-gray-400", "sap-card", 4.5),
]


def parse_colour(value):
    value = value.strip()
    if value.startswith("#"):
        v = value[1:]
        return (int(v[0:2], 16), int(v[2:4], 16), int(v[4:6], 16), 1.0)
    m = re.match(r"rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)", value)
    if m:
        return (int(m[1]), int(m[2]), int(m[3]), float(m[4] or 1))
    return None


def composite(fg, bg):
    """Flatten a translucent colour onto the surface behind it."""
    a = fg[3]
    return tuple(fg[i] * a + bg[i] * (1 - a) for i in range(3)) + (1.0,)


def luminance(c):
    def channel(v):
        v = v / 255
        return v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4

    return 0.2126 * channel(c[0]) + 0.7152 * channel(c[1]) + 0.0722 * channel(c[2])


def contrast(a, b):
    high, low = sorted([luminance(a), luminance(b)], reverse=True)
    return (high + 0.05) / (low + 0.05)


def main():
    css = CSS.read_text(encoding="utf-8")
    root = re.search(r":root\s*\{(.*?)\n\}", css, re.S)
    if not root:
        sys.exit("check-contrast: no :root block found in player.css")
    variables = dict(re.findall(r"--([\w-]+):\s*([^;]+);", root.group(1)))

    failures = []
    print(f"{'Element':30} {'ratio':>9}  {'needs':>6}")
    for label, fg_var, bg_var, minimum in CHECKS + KNOWN:
        fg, bg = parse_colour(variables[fg_var]), parse_colour(variables[bg_var])
        if fg is None or bg is None:
            sys.exit(f"check-contrast: cannot parse {fg_var} or {bg_var}")
        if fg[3] < 1:
            fg = composite(fg, bg)
        ratio = contrast(fg, bg)
        enforced = (label, fg_var, bg_var, minimum) in CHECKS
        mark = "ok" if ratio >= minimum else ("FAIL" if enforced else "known")
        print(f"{label:30} {ratio:7.2f}:1  {minimum:5.1f}  {mark}")
        if ratio < minimum and enforced:
            failures.append((label, ratio, minimum))

    if failures:
        for label, ratio, minimum in failures:
            print(f"::error::{label}: {ratio:.2f}:1 is below the required {minimum}:1")
        sys.exit(1)
    print("\ndefault theme meets the enforced contrast minimums")


if __name__ == "__main__":
    main()
