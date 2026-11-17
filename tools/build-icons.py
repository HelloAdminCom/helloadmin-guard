"""Builds assets/img/icon.svg (admin menu and settings page heading) from tools/icon-source.svg, lines thickened for small sizes.

Usage: pip install skia-pathops fonttools
       python tools/build-icons.py tools/icon-source.svg 40 assets/img/icon.svg [gear scale]
Path 1 of the source is the head, paths 2-3 are the gear; gear scale (default 0.85) shrinks the gear around its centre.
"""
import re, sys
import pathops
from fontTools.svgLib.path import parse_path
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen

src, extra, out = sys.argv[1], float(sys.argv[2]), sys.argv[3]
gear_scale = float(sys.argv[4]) if len(sys.argv) > 4 else 0.85
ds = re.findall(r'<path d="([^"]+)"', open(src).read())
def load(d, extra_transform=None):
    p = pathops.Path()
    pen = TransformPen(p.getPen(), (0.1, 0, 0, -0.1, 0, 1024))
    if extra_transform:
        pen = TransformPen(pen, extra_transform)
    parse_path(d.replace('\n', ' '), pen)
    p.fillType = pathops.FillType.EVEN_ODD
    return p

# gear centre from the bounds of the gear paths (in source units, before the potrace transform)
gear = pathops.Path()
for d in ds[1:]:
    gear = pathops.op(gear, load(d), pathops.PathOp.XOR)
x0, y0, x1, y1 = gear.bounds
cx, cy = (x0 + x1) / 2, (y0 + y1) / 2          # in 1024 units
# scale about (cx, cy) in 1024 space = translate/scale in source space (y flipped, *10)
sx, sy = cx * 10, (1024 - cy) * 10
t = (gear_scale, 0, 0, gear_scale, sx * (1 - gear_scale), sy * (1 - gear_scale))

shape = load(ds[0])
for d in ds[1:]:
    shape = pathops.op(shape, load(d, t), pathops.PathOp.XOR)
if extra > 0:
    s = pathops.Path(); shape.draw(s.getPen())
    s.stroke(extra, pathops.LineCap.ROUND_CAP, pathops.LineJoin.ROUND_JOIN, 4)
    s.convertConicsToQuads()
    shape = pathops.op(shape, s, pathops.PathOp.UNION)
# Fit the final outline (after thickening, which grows past the 1024 box) into 20x20 with a
# 0.5 unit margin, centred; otherwise the top of the head and the bottom line get clipped.
x0, y0, x1, y1 = shape.bounds
side = max(x1 - x0, y1 - y0)
k = 19 / side
dx = (20 - (x1 - x0) * k) / 2 - x0 * k
dy = (20 - (y1 - y0) * k) / 2 - y0 * k
pen = SVGPathPen(None, ntos=lambda v: f"{v:.2f}".rstrip("0").rstrip("."))
shape.draw(TransformPen(pen, (k, 0, 0, k, dx, dy)))
open(out, 'w').write(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="{pen.getCommands()}"/></svg>\n')
