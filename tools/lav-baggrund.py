"""Laver de slørede røde/sorte baggrunde (site/billeder/baggrund-*.webp). Kør: python3 tools/lav-baggrund.py site/billeder"""
import sys
import numpy as np
from PIL import Image, ImageFilter

OUT = sys.argv[1]
W, H = 1600, 900
y, x = np.mgrid[0:H, 0:W].astype(np.float32)
u, v = x / W, y / H
# blød "silke"-forvrængning, så striberne bugter sig
u2 = u + 0.045 * np.sin(v * 6.0 + 1.3) + 0.025 * np.sin(v * 13.0 + 4.0)
v2 = v + 0.05 * np.sin(u * 5.0 + 0.4) + 0.02 * np.sin(u * 11.0 + 2.2)

def streak(cx, cy, length, width, ang, w):
    a = np.deg2rad(ang)
    dx = (u2 - cx) * (W / H); dy = (v2 - cy)
    rx = dx * np.cos(a) + dy * np.sin(a)
    ry = -dx * np.sin(a) + dy * np.cos(a)
    return w * np.exp(-(rx ** 2) / (2 * length ** 2) - (ry ** 2) / (2 * width ** 2))

def field(kind):
    f = np.full((H, W), 0.42, np.float32)
    # klare røde felter i hjørnerne og langs højre kant
    f += streak(0.06, 0.92, 0.30, 0.16, -35, 0.62)
    f += streak(0.96, 0.06, 0.25, 0.14, -30, 0.62)
    f += streak(0.99, 0.42, 0.10, 0.18, 0, 0.35)
    # skyagtige mellemrøde former i midten
    f += streak(0.40, 0.42, 0.16, 0.14, -20, 0.30)
    f += streak(0.26, 0.60, 0.22, 0.06, -45, 0.32)   # rød stribe skråt op
    f += streak(0.55, 0.30, 0.14, 0.10, 30, 0.22)
    f += streak(0.74, 0.55, 0.12, 0.16, -10, 0.24)
    f += streak(0.20, 0.20, 0.18, 0.10, 20, 0.18)
    # sorte hvirvler
    f -= streak(0.07, 0.45, 0.55, 0.06, -84, 0.62)   # lodret bånd til venstre
    f -= streak(0.60, 0.06, 0.30, 0.055, -12, 0.50)   # øverst i midten
    f -= streak(0.90, 0.34, 0.22, 0.045, -65, 0.50)   # til højre
    f -= streak(0.75, 0.92, 0.30, 0.09, -15, 0.45)   # nederst til højre
    f -= streak(0.50, 0.62, 0.20, 0.035, 25, 0.26)    # tynd hvirvel i midten
    f -= streak(0.33, 0.30, 0.10, 0.04, -60, 0.25)
    f -= streak(0.18, 0.82, 0.16, 0.035, -40, 0.40)  # skiller de to røde striber nederst
    if kind == "moerk":
        f = f * 0.82 - 0.06
    return np.clip(f, 0, 1)

# farveskala: sort -> mørkerød -> rød -> klar rød
stops = [(0.00, (5, 3, 3)), (0.28, (30, 4, 6)), (0.50, (92, 9, 14)), (0.72, (150, 13, 21)), (0.88, (200, 20, 28)), (1.00, (228, 32, 38))]
def colorize(f):
    out = np.zeros((H, W, 3), np.float32)
    for (p0, c0), (p1, c1) in zip(stops, stops[1:]):
        m = (f >= p0) & (f <= p1)
        t = ((f - p0) / (p1 - p0))[m][:, None]
        out[m] = np.array(c0) * (1 - t) + np.array(c1) * t
    return out

for kind in ("roed",):
    img = Image.fromarray(colorize(field(kind)).clip(0, 255).astype(np.uint8))
    img = img.filter(ImageFilter.GaussianBlur(14))
    img = img.resize((1280, 720), Image.LANCZOS)
    img.save(f"{OUT}/baggrund-{kind}.webp", "WEBP", quality=88, method=6)
print("ok")
