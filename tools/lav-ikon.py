"""Laver sidens ikon (favicon) ud fra de to anførselstegn/masker i logoet: sorte på creme baggrund.
Kør: python3 tools/lav-ikon.py site/billeder/logo.png <mappe>  – læg så favicon.ico og apple-touch-icon.png i site/ og ikon-*.png i site/billeder/."""
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

CREAM, INK = (251, 243, 233, 255), (29, 27, 27, 255)

def icon(size, fill=0.80, radius=0.0):
    big = size * S
    canvas = Image.new("RGBA", (big, big), (0, 0, 0, 0))
    bg = Image.new("L", (big, big), 0)
    ImageDraw.Draw(bg).rounded_rectangle((0, 0, big - 1, big - 1), radius=int(big * radius), fill=255)
    canvas.paste(Image.new("RGBA", (big, big), CREAM), (0, 0), bg)
    scale = fill * big / max(w, h)
    m = art.resize((max(1, int(w * scale)), max(1, int(h * scale))), Image.LANCZOS)
    pos = ((big - m.width) // 2, (big - m.height) // 2)
    canvas.paste(Image.new("RGBA", m.size, INK), pos, m)
    return canvas.resize((size, size), Image.LANCZOS)

# Fanebladet i browseren: lidt afrundede hjørner
ico = [icon(s, fill=0.86, radius=0.18) for s in (16, 32, 48)]
ico[-1].save(f"{OUT}/favicon.ico", sizes=[(16, 16), (32, 32), (48, 48)], append_images=ico[:-1])
icon(32, fill=0.86, radius=0.18).save(f"{OUT}/ikon-32.png")
icon(192, fill=0.78, radius=0.18).save(f"{OUT}/ikon-192.png")
# iPhone/iPad (afrundes automatisk af telefonen)
icon(180, fill=0.74).save(f"{OUT}/apple-touch-icon.png")
print("ok", w // S, h // S)
