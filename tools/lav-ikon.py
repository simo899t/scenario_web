"""Laver sidens ikon (favicon) ud fra de to anførselstegn/masker i logoet: sorte på creme baggrund.
Kør: python3 tools/lav-ikon.py site/billeder/logo.png <mappe>  – læg så favicon.ico og apple-touch-icon.png i site/,
ikon-192.png i site/billeder/, og filerne i admin/ og lager/ i site/admin/ og site/lager/ (rød og blå udgave)."""
import sys
import numpy as np
from PIL import Image, ImageDraw

LOGO, OUT = sys.argv[1], sys.argv[2]
logo = Image.open(LOGO).convert("RGBA")
a = np.array(logo)[:, :, 3] > 40
cols = a.any(axis=0)
groups, start = [], None
for x in range(a.shape[1]):
    if cols[x] and start is None: start = x
    if not cols[x] and start is not None: groups.append((start, x)); start = None
if start is not None: groups.append((start, a.shape[1]))

def mark(x0, x1):
    rows = np.where(a[:, x0:x1].any(axis=1))[0]
    return logo.crop((x0, rows.min(), x1, rows.max() + 1)).getchannel("A")

left, right = mark(*groups[0]), mark(*groups[-1])
S = 8  # tegn stort og skaler ned bagefter, så kanterne bliver bløde
left = left.resize((left.width * S, left.height * S), Image.LANCZOS)
right = right.resize((right.width * S, right.height * S), Image.LANCZOS)
gap = int(left.width * 0.12)
drop = int(left.height * 0.5)        # højre maske står lavere, som på plakaten
w = left.width + gap + right.width
h = max(left.height, drop + right.height)
art = Image.new("L", (w, h), 0)
art.paste(left, (0, 0))
art.paste(right, (left.width + gap, drop))

VARIANTS = {
    "": ((251, 243, 233, 255), (29, 27, 27, 255)),         # hjemmesiden: sort på creme
    "admin/": ((163, 18, 28, 255), (251, 243, 233, 255)),  # editoren: creme på rød
    "lager/": ((26, 86, 196, 255), (251, 243, 233, 255)),  # lageret: creme på blå
}

def icon(size, bgc, ink, fill=0.80, radius=0.0):
    big = size * S
    canvas = Image.new("RGBA", (big, big), (0, 0, 0, 0))
    bg = Image.new("L", (big, big), 0)
    ImageDraw.Draw(bg).rounded_rectangle((0, 0, big - 1, big - 1), radius=int(big * radius), fill=255)
    canvas.paste(Image.new("RGBA", (big, big), bgc), (0, 0), bg)
    scale = fill * big / max(w, h)
    m = art.resize((max(1, int(w * scale)), max(1, int(h * scale))), Image.LANCZOS)
    pos = ((big - m.width) // 2, (big - m.height) // 2)
    canvas.paste(Image.new("RGBA", m.size, ink), pos, m)
    return canvas.resize((size, size), Image.LANCZOS)

import os
for sub, (bgc, ink) in VARIANTS.items():
    d = os.path.join(OUT, sub)
    os.makedirs(d, exist_ok=True)
    # Fanebladet i browseren: lidt afrundede hjørner
    ico = [icon(s, bgc, ink, fill=0.86, radius=0.18) for s in (16, 32, 48)]
    ico[-1].save(os.path.join(d, "favicon.ico"), sizes=[(16, 16), (32, 32), (48, 48)], append_images=ico[:-1])
    # iPhone/iPad (afrundes automatisk af telefonen)
    icon(180, bgc, ink, fill=0.74).save(os.path.join(d, "apple-touch-icon.png"))
    if sub == "":
        icon(192, bgc, ink, fill=0.78, radius=0.18).save(os.path.join(d, "ikon-192.png"))
print("ok", w // S, h // S)
