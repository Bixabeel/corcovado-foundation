#!/usr/bin/env python3
"""Pixel comparison of the screenshots made by tools/visual-compare.js.
Usage: python3 tools/visual-diff.py SHOTS_DIR [--images]
Prints, per page and width: page heights and % of differing pixels; with --images writes
side-by-side + highlighted diff PNGs (diff-*.png) for pages that differ."""
import glob
import os
import sys

from PIL import Image, ImageChops

d = sys.argv[1]
images = '--images' in sys.argv
rows = []
for o in sorted(glob.glob(os.path.join(d, '*-orig.png'))):
    w = o[:-9] + '-wp.png'
    if not os.path.exists(w):
        continue
    a = Image.open(o).convert('RGB')
    b = Image.open(w).convert('RGB')
    h = max(a.height, b.height)
    A = Image.new('RGB', (a.width, h), 'white'); A.paste(a, (0, 0))
    B = Image.new('RGB', (a.width, h), 'white'); B.paste(b.crop((0, 0, a.width, b.height)), (0, 0))
    diff = ImageChops.difference(A, B).convert('L').point(lambda v: 255 if v > 24 else 0)
    n = sum(1 for v in diff.getdata() if v)
    pct = 100.0 * n / (a.width * h)
    name = os.path.basename(o)[:-9]
    rows.append((name, a.height, b.height, pct))
    if images and pct > 0.01:
        red = Image.new('RGB', A.size, (255, 0, 0))
        hl = Image.composite(red, B, diff)
        sbs = Image.new('RGB', (a.width * 3, h), 'white')
        sbs.paste(A, (0, 0)); sbs.paste(B, (a.width, 0)); sbs.paste(hl, (a.width * 2, 0))
        sbs.save(os.path.join(d, 'diff-' + name + '.png'))
for name, ha, hb, pct in rows:
    print('%-45s orig %5dpx  wp %5dpx  diff %6.2f%%' % (name, ha, hb, pct))
