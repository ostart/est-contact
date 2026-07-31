const fs = require('fs');
const sharp = require('sharp');
const toIco = require('to-ico');

(async () => {
  const src = 'C:/Users/yodmin/.cursor/projects/d-Github-est-contact/assets/favicon-tree-base.png';
  const size = 1024;

  const mask = Buffer.from(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}"><circle cx="${size / 2}" cy="${size / 2}" r="${size / 2 - 2}" fill="white"/></svg>`
  );

  let base = await sharp(src)
    .resize(size, size, { fit: 'cover' })
    .modulate({ saturation: 1.15, brightness: 0.82 })
    .composite([{ input: mask, blend: 'dest-in' }])
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  // Keep tree as-is; push background slightly redder
  const { data, info } = base;
  for (let i = 0; i < data.length; i += 4) {
    const r = data[i];
    const g = data[i + 1];
    const b = data[i + 2];
    const isTree = g > r + 15 && g > b + 15 && g > 60;
    if (isTree) {
      data[i] = Math.min(255, Math.round(r * 0.55 + 40));
      data[i + 1] = Math.min(255, Math.round(g * 1.35 + 35));
      data[i + 2] = Math.min(255, Math.round(b * 0.55 + 20));
    } else if (data[i + 3] > 20) {
      data[i] = Math.min(255, Math.round(r * 1.1 + 18));
      data[i + 1] = Math.max(0, Math.round(g * 0.88));
      data[i + 2] = Math.max(0, Math.round(b * 0.82));
    }
  }

  base = await sharp(data, { raw: { width: info.width, height: info.height, channels: 4 } })
    .png()
    .toBuffer();

  // Narrow trunk slightly
  const slimTrunk = await sharp(
    Buffer.from(`<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}">
  <rect x="280" y="500" width="208" height="530" fill="#8e2a38"/>
  <rect x="536" y="500" width="208" height="530" fill="#8e2a38"/>
</svg>`)
  )
    .png()
    .toBuffer();

  const withTrunk = await sharp(base)
    .composite([{ input: slimTrunk, blend: 'over' }])
    .png()
    .toBuffer();

  // Vertically centered letters (dominant-baseline middle)
  const overlay = await sharp(
    Buffer.from(`<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}">
  <defs>
    <linearGradient id="gold" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#ffe566"/>
      <stop offset="40%" stop-color="#ffd700"/>
      <stop offset="100%" stop-color="#f0c010"/>
    </linearGradient>
  </defs>
  <circle cx="512" cy="512" r="500" fill="none" stroke="#7a1e2c" stroke-width="28"/>
  <text x="235" y="512" text-anchor="middle" dominant-baseline="middle"
        font-family="Georgia, 'Times New Roman', Times, serif"
        font-size="600" font-weight="700"
        fill="url(#gold)">&#1041;</text>
  <text x="789" y="512" text-anchor="middle" dominant-baseline="middle"
        font-family="Georgia, 'Times New Roman', Times, serif"
        font-size="600" font-weight="700"
        fill="url(#gold)">&#1042;</text>
</svg>`)
  )
    .png()
    .toBuffer();

  const composed = await sharp(withTrunk)
    .composite([{ input: overlay, blend: 'over' }])
    .png()
    .toBuffer();

  const finalMask = Buffer.from(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}"><circle cx="${size / 2}" cy="${size / 2}" r="${size / 2}" fill="white"/></svg>`
  );

  const finalImg = await sharp(composed)
    .composite([{ input: finalMask, blend: 'dest-in' }])
    .png()
    .toBuffer();

  await sharp(finalImg).resize(512, 512).png().toFile('public/favicon.png');
  await sharp(finalImg).resize(180, 180).png().toFile('public/apple-touch-icon.png');

  const pngs = [];
  for (const s of [16, 32, 48]) {
    pngs.push(await sharp(finalImg).resize(s, s).png().toBuffer());
  }
  fs.writeFileSync('public/favicon.ico', await toIco(pngs));

  fs.writeFileSync(
    'public/favicon.svg',
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
  <image href="favicon.png" width="64" height="64"/>
</svg>
`
  );

  await sharp(finalImg).resize(220, 220).png().toFile('public/_favicon-preview.png');

  // verify letter vertical center
  const preview = await sharp('public/_favicon-preview.png').raw().ensureAlpha().toBuffer({ resolveWithObject: true });
  const w = preview.info.width;
  const h = preview.info.height;
  const d = preview.data;
  let top = -1;
  let bot = -1;
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const i = (y * w + x) * 4;
      if (d[i] > 170 && d[i + 1] > 120 && d[i + 2] < 100) {
        if (top < 0) top = y;
        bot = y;
      }
    }
  }
  console.log('ok letter mid%', ((((top + bot) / 2) / h) * 100).toFixed(0));
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
