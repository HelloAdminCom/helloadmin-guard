# Menu icon: head outline (tools/icon-source.svg) with a shield inside (outline | outline-check | filled = filled shield, check cut out).
# Usage: python tools/build-shield-icon.py out.svg filled 510 110 340 58   (centre x, top y, width, line; 1024 head space). Needs skia-pathops + fonttools.
import sys, re
import pathops
from fontTools.svgLib.path import parse_path
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.svgPathPen import SVGPathPen
T=__import__('os').path.dirname(__import__('os').path.abspath(__file__))+'/'
out, style, cx, top, w, line = sys.argv[1], sys.argv[2], float(sys.argv[3]), float(sys.argv[4]), float(sys.argv[5]), float(sys.argv[6])
def stroke(p,wd,cap=pathops.LineCap.ROUND_CAP):
    s=pathops.Path(); p.draw(s.getPen()); s.stroke(wd,cap,pathops.LineJoin.ROUND_JOIN,4); s.convertConicsToQuads(); return s
text=open(T+'icon-source.svg').read(); h=float(re.search(r'viewBox="0 0 [\d.]+ ([\d.]+)"',text).group(1))
head=pathops.Path(); parse_path(re.findall(r'<path d="([^"]+)"',text)[0].replace('\n',' '),TransformPen(head.getPen(),(0.1,0,0,-0.1,0,h)))
head.fillType=pathops.FillType.EVEN_ODD; head=pathops.simplify(head); head=pathops.op(head,stroke(head,40),pathops.PathOp.UNION)
X=lambda u: cx+(u-0.5)*w; Y=lambda v: top+v*w
sh=pathops.Path(); p=sh.getPen()
p.moveTo((X(.5),Y(0))); p.curveTo((X(.66),Y(.1)),(X(.84),Y(.13)),(X(1),Y(.13))); p.lineTo((X(1),Y(.55)))
p.curveTo((X(1),Y(.88)),(X(.76),Y(1.07)),(X(.5),Y(1.2))); p.curveTo((X(.24),Y(1.07)),(X(0),Y(.88)),(X(0),Y(.55)))
p.lineTo((X(0),Y(.13))); p.curveTo((X(.16),Y(.13)),(X(.34),Y(.1)),(X(.5),Y(0))); p.closePath()
ck=pathops.Path(); q=ck.getPen(); q.moveTo((X(.27),Y(.6))); q.lineTo((X(.44),Y(.78))); q.lineTo((X(.74),Y(.4))); q.endPath()
shape=head
if style in ('outline','outline-check'):
    shape=pathops.op(shape,stroke(sh,line),pathops.PathOp.UNION)
    if style=='outline-check': shape=pathops.op(shape,stroke(ck,line),pathops.PathOp.UNION)
else:  # filled, check knocked out
    filled=pathops.op(sh,stroke(sh,line),pathops.PathOp.UNION)
    filled=pathops.op(filled,stroke(ck,line*1.1),pathops.PathOp.DIFFERENCE)
    shape=pathops.op(shape,filled,pathops.PathOp.UNION)
x0,y0,x1,y1=shape.bounds; side=max(x1-x0,y1-y0); k=19/side
dx=(20-(x1-x0)*k)/2-x0*k; dy=(20-(y1-y0)*k)/2-y0*k
pen=SVGPathPen(None,ntos=lambda v:f"{v:.2f}".rstrip("0").rstrip("."))
shape.draw(TransformPen(pen,(k,0,0,k,dx,dy)))
open(out,'w').write(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="{pen.getCommands()}"/></svg>\n')
