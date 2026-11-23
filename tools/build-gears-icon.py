import re, sys, math
import pathops
from fontTools.svgLib.path import parse_path
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
src, out = sys.argv[1], sys.argv[2]
# gears: cx,cy,outer radius,teeth,dot[,ring] (1024 space; dot/ring = radius share of the centre disk / inner circle); then line width, head extra
G = [tuple(map(float, g.split(','))) for g in sys.argv[3].split(';')]
LINE = float(sys.argv[4]) if len(sys.argv) > 4 else 34
HEAD_EXTRA = float(sys.argv[5]) if len(sys.argv) > 5 else 40
ds = re.findall(r'<path d="([^"]+)"', open(src).read())
def load(d):
    p = pathops.Path(); pen = TransformPen(p.getPen(), (0.1, 0, 0, -0.1, 0, 1024))
    parse_path(d.replace('\n', ' '), pen); p.fillType = pathops.FillType.EVEN_ODD; return p
def stroke(p, w):
    s = pathops.Path(); p.draw(s.getPen()); s.stroke(w, pathops.LineCap.ROUND_CAP, pathops.LineJoin.ROUND_JOIN, 4); s.convertConicsToQuads(); return s
head = load(ds[0])
if '--bounds' in sys.argv:
    g = pathops.Path()
    for d in ds[1:]: g = pathops.op(g, load(d), pathops.PathOp.UNION)
    print('head', [round(v) for v in head.bounds], 'gear', [round(v) for v in g.bounds]); sys.exit()
head = pathops.op(head, stroke(head, HEAD_EXTRA), pathops.PathOp.UNION)
shape = head
for g in G:
    cx, cy, r, n, dot = g[:5]; ring = g[5] if len(g) > 5 else 0
    n = int(n); pts = []
    rin, rout = r * 0.80, r
    for i in range(n * 4):
        a = 2 * math.pi * i / (n * 4) - math.pi / 2
        rr = rout if (i % 4) in (1, 2) else rin
        pts.append((cx + rr * math.cos(a), cy + rr * math.sin(a)))
    gp = pathops.Path(); pen = gp.getPen(); pen.moveTo(pts[0])
    for q in pts[1:]: pen.lineTo(q)
    pen.closePath()
    shape = pathops.op(shape, stroke(gp, LINE), pathops.PathOp.UNION)
    c = pathops.Path(); pen = c.getPen(); k = 0.5523
    rd = r * dot
    pen.moveTo((cx + rd, cy)); pen.curveTo((cx + rd, cy + rd*k), (cx + rd*k, cy + rd), (cx, cy + rd))
    pen.curveTo((cx - rd*k, cy + rd), (cx - rd, cy + rd*k), (cx - rd, cy)); pen.curveTo((cx - rd, cy - rd*k), (cx - rd*k, cy - rd), (cx, cy - rd))
    pen.curveTo((cx + rd*k, cy - rd), (cx + rd, cy - rd*k), (cx + rd, cy)); pen.closePath()
    shape = pathops.op(shape, c, pathops.PathOp.UNION)
    if ring:
        rr = r * ring; o = pathops.Path(); pen = o.getPen()
        pen.moveTo((cx + rr, cy)); pen.curveTo((cx + rr, cy + rr*k), (cx + rr*k, cy + rr), (cx, cy + rr))
        pen.curveTo((cx - rr*k, cy + rr), (cx - rr, cy + rr*k), (cx - rr, cy)); pen.curveTo((cx - rr, cy - rr*k), (cx - rr*k, cy - rr), (cx, cy - rr))
        pen.curveTo((cx + rr*k, cy - rr), (cx + rr, cy - rr*k), (cx + rr, cy)); pen.closePath()
        shape = pathops.op(shape, stroke(o, LINE * 0.7), pathops.PathOp.UNION)
x0, y0, x1, y1 = shape.bounds
side = max(x1 - x0, y1 - y0); k = 19 / side
dx = (20 - (x1 - x0) * k) / 2 - x0 * k; dy = (20 - (y1 - y0) * k) / 2 - y0 * k
pen = SVGPathPen(None, ntos=lambda v: f"{v:.2f}".rstrip("0").rstrip("."))
shape.draw(TransformPen(pen, (k, 0, 0, k, dx, dy)))
open(out, 'w').write(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="{pen.getCommands()}"/></svg>\n')
