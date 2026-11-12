# Contact sheet of stills, for checking a dry run or a render.
import sys, glob
from PIL import Image
out, h, *pats = sys.argv[1:]
h = int(h)
files = [f for p in pats for f in sorted(glob.glob(p))]
ims = [Image.open(f) for f in files]
ims = [im.resize((int(im.size[0] * h / im.size[1]), h)) for im in ims]
row, rows, x = [], [], 0
for im in ims:
    if x + im.size[0] > 2000 and row:
        rows.append(row); row, x = [], 0
    row.append(im); x += im.size[0] + 8
rows.append(row)
sheet = Image.new('RGB', (2000, (h + 8) * len(rows)), '#888')
for r, row in enumerate(rows):
    x = 0
    for im in row:
        sheet.paste(im, (x, r * (h + 8))); x += im.size[0] + 8
sheet.save(out, quality=72)
print(out, len(files), 'stills')
