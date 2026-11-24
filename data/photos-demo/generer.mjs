// Génère les illustrations des 14 produits de démonstration (SVG dessinés à la main).
// Usage (Node + Playwright installés) :
//   node data/photos-demo/generer.mjs      → écrit sources/*.svg puis les rendus *.png (800×600)
//   cd data/photos-demo && php -r 'foreach(glob("*.png") as $p){imagewebp(imagecreatefrompng($p), substr($p,0,-4).".webp", 88); unlink($p);}'
// Seuls les .webp et les .svg sont versionnés. La commande Symfony app:produits:photos les associe aux produits.
import { writeFileSync, mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ici = dirname(fileURLToPath(import.meta.url));

// Fond commun : dégradé doux + ombre au sol. couleurs = [haut, bas]
const cadre = (couleurs, contenu, ombre = { cx: 400, cy: 500, rx: 230, ry: 26 }) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" width="800" height="600">
<defs>
  <linearGradient id="fond" x1="0" y1="0" x2="0.3" y2="1"><stop offset="0" stop-color="${couleurs[0]}"/><stop offset="1" stop-color="${couleurs[1]}"/></linearGradient>
  <radialGradient id="ombre"><stop offset="0" stop-color="#000" stop-opacity=".28"/><stop offset="1" stop-color="#000" stop-opacity="0"/></radialGradient>
  <linearGradient id="noir" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b3f46"/><stop offset="1" stop-color="#16181c"/></linearGradient>
  <linearGradient id="noirH" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#16181c"/><stop offset=".5" stop-color="#3b3f46"/><stop offset="1" stop-color="#16181c"/></linearGradient>
  <linearGradient id="blanc" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#d9dde3"/></linearGradient>
  <linearGradient id="alu" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#eef0f3"/><stop offset=".5" stop-color="#b8bec7"/><stop offset="1" stop-color="#8d949e"/></linearGradient>
  <linearGradient id="ecran" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4f7cff"/><stop offset=".55" stop-color="#7b5cff"/><stop offset="1" stop-color="#ff6fb1"/></linearGradient>
  <pattern id="tissu" width="8" height="8" patternUnits="userSpaceOnUse"><rect width="8" height="8" fill="#2a2e35"/><circle cx="4" cy="4" r="1.4" fill="#1a1c21"/></pattern>
</defs>
<rect width="800" height="600" fill="url(#fond)"/>
<ellipse cx="${ombre.cx}" cy="${ombre.cy}" rx="${ombre.rx}" ry="${ombre.ry}" fill="url(#ombre)"/>
${contenu}
</svg>`;

// Casque arceau : couleur de la coque, du coussin, de l'accent
const casque = (coque, coussin, accent, arceau) => `
<path d="M250 330 C250 150 550 150 550 330" fill="none" stroke="${arceau}" stroke-width="30" stroke-linecap="round"/>
<path d="M262 300 C270 180 530 180 538 300" fill="none" stroke="#ffffff" stroke-opacity=".18" stroke-width="6" stroke-linecap="round"/>
<rect x="236" y="290" width="26" height="60" rx="10" fill="${accent}"/>
<rect x="538" y="290" width="26" height="60" rx="10" fill="${accent}"/>
<ellipse cx="240" cy="390" rx="72" ry="96" fill="${coussin}"/>
<ellipse cx="226" cy="390" rx="62" ry="88" fill="${coque}"/>
<ellipse cx="214" cy="372" rx="22" ry="40" fill="#fff" fill-opacity=".14"/>
<ellipse cx="560" cy="390" rx="72" ry="96" fill="${coussin}"/>
<ellipse cx="574" cy="390" rx="62" ry="88" fill="${coque}"/>
<ellipse cx="562" cy="372" rx="22" ry="40" fill="#fff" fill-opacity=".14"/>
<circle cx="574" cy="390" r="14" fill="${accent}"/>`;

// Clavier vu de dessus : x, y, largeur, colonnes, rangées, taille touche, couleurs
const clavier = ({ x, y, cols, rangs, t, ecart, corps, touche, toucheAccent, bord }) => {
  const largeur = cols * (t + ecart) + ecart, hauteur = rangs * (t + ecart) + ecart;
  let touches = '';
  for (let r = 0; r < rangs; r++) {
    for (let c = 0; c < cols; c++) {
      let tx = x + ecart + c * (t + ecart), w = t;
      const derniereRangee = r === rangs - 1;
      if (derniereRangee && c === 3) { w = t * 6 + ecart * 5; }           // barre d'espace
      if (derniereRangee && c > 3 && c < 9) continue;
      if (derniereRangee && c >= 9) { tx = x + ecart + c * (t + ecart); }
      const accent = (r === 0 && c === 0) || (r === rangs - 2 && c === cols - 1);
      touches += `<rect x="${tx}" y="${y + ecart + r * (t + ecart)}" width="${w}" height="${t}" rx="${t * 0.18}" fill="${accent ? toucheAccent : touche}"/>
<rect x="${tx + 2}" y="${y + ecart + r * (t + ecart) + 2}" width="${w - 4}" height="${t * 0.55}" rx="${t * 0.14}" fill="#fff" fill-opacity=".07"/>`;
    }
  }
  return `<rect x="${x - 6}" y="${y - 6}" width="${largeur + 12}" height="${hauteur + 18}" rx="18" fill="${bord}"/>
<rect x="${x}" y="${y}" width="${largeur}" height="${hauteur}" rx="12" fill="${corps}"/>${touches}`;
};

const ecranMoniteur = (x, y, w, h, pied) => `
<rect x="${400 - pied / 2}" y="${y + h + 6}" width="${pied}" height="${470 - (y + h + 6)}" rx="6" fill="url(#alu)"/>
<path d="M${400 - pied * 1.6} 482 Q400 462 ${400 + pied * 1.6} 482 L${400 + pied * 1.8} 494 L${400 - pied * 1.8} 494 Z" fill="url(#alu)"/>
<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="12" fill="#121418"/>
<rect x="${x + 10}" y="${y + 10}" width="${w - 20}" height="${h - 22}" rx="4" fill="url(#ecran)"/>
<path d="M${x + 10} ${y + h - 12} L${x + w * 0.55} ${y + 10} L${x + w * 0.75} ${y + 10} L${x + w * 0.3} ${y + h - 12} Z" fill="#fff" fill-opacity=".10"/>
<circle cx="${x + w / 2}" cy="${y + h - 5}" r="2" fill="#5c6370"/>`;

const illustrations = {
  'casque-studio-s1': cadre(['#e9edf3', '#c9d1dc'], casque('url(#noir)', '#2b2e34', '#a9b0ba', '#22252a'), { cx: 400, cy: 500, rx: 220, ry: 24 }),

  'casque-sans-fil-air': cadre(['#fdf1e6', '#f2d6c0'], casque('url(#blanc)', '#c9b8a8', '#e7a46b', '#efe9e2'), { cx: 400, cy: 500, rx: 220, ry: 24 }),

  'ecouteurs-mini': cadre(['#e6f4f1', '#bfe0d9'], `
<rect x="230" y="290" width="230" height="160" rx="64" fill="url(#noir)"/>
<path d="M232 352 H458" stroke="#0e0f12" stroke-width="3"/>
<path d="M260 312 C300 300 380 300 430 312" fill="none" stroke="#fff" stroke-opacity=".12" stroke-width="8" stroke-linecap="round"/>
<circle cx="345" cy="400" r="6" fill="#3ccf91"/>
<g transform="translate(530 280)">
  <circle cx="0" cy="0" r="52" fill="url(#noir)"/>
  <circle cx="0" cy="0" r="30" fill="#2b2e34"/>
  <circle cx="0" cy="0" r="12" fill="#3ccf91"/>
  <ellipse cx="-38" cy="18" rx="20" ry="16" fill="#5d6570"/>
</g>
<g transform="translate(610 360)">
  <circle cx="0" cy="0" r="52" fill="url(#noir)"/>
  <circle cx="0" cy="0" r="30" fill="#2b2e34"/>
  <circle cx="0" cy="0" r="12" fill="#3ccf91"/>
  <ellipse cx="-38" cy="18" rx="20" ry="16" fill="#5d6570"/>
</g>`, { cx: 430, cy: 470, rx: 250, ry: 22 }),

  'enceinte-nomade-20w': cadre(['#fff4d6', '#f6d98c'], `
<rect x="290" y="150" width="220" height="330" rx="100" fill="url(#tissu)"/>
<rect x="290" y="150" width="220" height="330" rx="100" fill="url(#noirH)" fill-opacity=".45"/>
<rect x="290" y="200" width="220" height="12" fill="#ff8a3d"/>
<rect x="290" y="420" width="220" height="12" fill="#ff8a3d"/>
<rect x="365" y="160" width="70" height="26" rx="13" fill="#1a1c21"/>
<circle cx="385" cy="173" r="5" fill="#ff8a3d"/><circle cx="415" cy="173" r="5" fill="#ff8a3d"/>
<text x="400" y="330" text-anchor="middle" font-family="Arial, sans-serif" font-weight="700" font-size="30" fill="#ffffff" fill-opacity=".75">20W</text>
<path d="M300 250 Q300 160 360 155" fill="none" stroke="#fff" stroke-opacity=".12" stroke-width="10"/>`, { cx: 400, cy: 490, rx: 150, ry: 20 }),

  'barre-de-son-salon': cadre(['#ece8f6', '#cdc4e6'], `
<rect x="110" y="300" width="580" height="96" rx="40" fill="url(#tissu)"/>
<rect x="110" y="300" width="580" height="96" rx="40" fill="url(#noir)" fill-opacity=".45"/>
<rect x="110" y="300" width="580" height="22" rx="11" fill="#3b3f46"/>
<circle cx="400" cy="372" r="4" fill="#7b5cff"/>
<rect x="160" y="396" width="40" height="14" rx="4" fill="#16181c"/><rect x="600" y="396" width="40" height="14" rx="4" fill="#16181c"/>
<path d="M150 312 H650" stroke="#fff" stroke-opacity=".15" stroke-width="3"/>
<g fill="none" stroke="#7b5cff" stroke-opacity=".5" stroke-width="5" stroke-linecap="round">
  <path d="M360 240 Q400 220 440 240"/><path d="M335 210 Q400 180 465 210"/>
</g>`, { cx: 400, cy: 430, rx: 310, ry: 22 }),

  'clavier-mecanique-k75': cadre(['#e8eef7', '#c3cfe2'],
    clavier({ x: 150, y: 210, cols: 13, rangs: 5, t: 34, ecart: 5, corps: '#1e2126', touche: '#3a3f47', toucheAccent: '#ff7a59', bord: '#2a2e35' }), { cx: 400, cy: 450, rx: 300, ry: 22 }),

  'clavier-compact-sans-fil': cadre(['#eef6ea', '#cfe3c4'],
    clavier({ x: 175, y: 230, cols: 13, rangs: 4, t: 30, ecart: 5, corps: '#e4e7eb', touche: '#ffffff', toucheAccent: '#8bc34a', bord: '#cfd4da' }) +
    `<circle cx="625" cy="226" r="4" fill="#8bc34a"/>`, { cx: 400, cy: 420, rx: 280, ry: 20 }),

  'souris-ergonomique-verti': cadre(['#e5f1fb', '#bcd8f0'], `
<path d="M280 470 C276 390 300 300 360 245 C405 205 455 190 490 205 C530 225 540 320 535 470 Z" fill="url(#noir)"/>
<path d="M490 205 C530 225 540 320 535 470 L498 470 C503 340 492 262 465 212 Z" fill="#4a505a"/>
<path d="M472 300 C500 300 520 304 538 312" fill="none" stroke="#16181c" stroke-width="4"/>
<rect x="476" y="232" width="14" height="40" rx="7" fill="#9aa3ae" transform="rotate(-28 483 252)"/>
<rect x="352" y="318" width="44" height="14" rx="7" fill="#5d6570" transform="rotate(-38 374 325)"/>
<rect x="336" y="345" width="44" height="14" rx="7" fill="#5d6570" transform="rotate(-38 358 352)"/>
<path d="M300 440 C330 420 380 415 430 425" fill="none" stroke="#2f80ed" stroke-width="7" stroke-linecap="round"/>
<path d="M320 300 C340 265 370 240 405 222" fill="none" stroke="#fff" stroke-opacity=".16" stroke-width="10" stroke-linecap="round"/>
<path d="M280 470 L535 470 L540 480 L276 480 Z" fill="#0e0f12"/>`, { cx: 408, cy: 488, rx: 160, ry: 18 }),

  'souris-legere-gaming': cadre(['#1f2330', '#0f1117'], `
<path d="M400 150 C480 150 520 220 520 320 C520 420 470 480 400 480 C330 480 280 420 280 320 C280 220 320 150 400 150 Z" fill="url(#noir)"/>
<path d="M400 150 L400 290" stroke="#0b0c0f" stroke-width="4"/>
<rect x="390" y="200" width="20" height="50" rx="10" fill="#22252a" stroke="#00e5ff" stroke-width="3"/>
<path d="M300 300 C300 380 330 450 400 470 C470 450 500 380 500 300" fill="none" stroke="#00e5ff" stroke-width="5" stroke-opacity=".85"/>
<path d="M300 300 C300 380 330 450 400 470 C470 450 500 380 500 300" fill="none" stroke="#00e5ff" stroke-width="16" stroke-opacity=".18"/>
<g fill="#16181c"><circle cx="360" cy="420" r="8"/><circle cx="400" cy="430" r="8"/><circle cx="440" cy="420" r="8"/></g>
<path d="M320 230 C335 190 360 170 390 165" fill="none" stroke="#fff" stroke-opacity=".16" stroke-width="10" stroke-linecap="round"/>`, { cx: 400, cy: 498, rx: 150, ry: 16 }),

  'ecran-27-qhd': cadre(['#e9ecf2', '#c6ccd8'], ecranMoniteur(140, 110, 520, 300, 40), { cx: 400, cy: 498, rx: 160, ry: 16 }),

  'ecran-24-full-hd': cadre(['#f3ece4', '#ddcdbb'], ecranMoniteur(185, 150, 430, 250, 34) +
    `<rect x="255" y="455" width="110" height="34" rx="6" fill="#ffffff" fill-opacity=".0"/>`, { cx: 400, cy: 498, rx: 140, ry: 16 }),

  'hub-usb-c-7-ports': cadre(['#eaf0f4', '#c8d4dc'], `
<path d="M240 330 C170 330 150 260 110 240" fill="none" stroke="#2a2e35" stroke-width="14" stroke-linecap="round"/>
<rect x="88" y="222" width="38" height="24" rx="8" fill="url(#alu)" transform="rotate(25 107 234)"/>
<rect x="240" y="270" width="380" height="120" rx="26" fill="url(#alu)"/>
<rect x="240" y="270" width="380" height="40" rx="20" fill="#fff" fill-opacity=".25"/>
<g fill="#1c1f24">
  <rect x="280" y="318" width="40" height="16" rx="3"/><rect x="335" y="318" width="40" height="16" rx="3"/><rect x="390" y="318" width="40" height="16" rx="3"/>
  <rect x="448" y="320" width="28" height="12" rx="6"/><rect x="490" y="320" width="28" height="12" rx="6"/>
  <rect x="535" y="314" width="30" height="22" rx="3"/><rect x="578" y="318" width="20" height="14" rx="3"/>
</g>
<circle cx="600" cy="292" r="4" fill="#3ccf91"/>`, { cx: 400, cy: 410, rx: 240, ry: 18 }),

  'support-ordinateur-portable': cadre(['#f1efe9', '#d8d2c4'], `
<path d="M250 470 L330 300" stroke="url(#alu)" stroke-width="18" stroke-linecap="round"/>
<path d="M550 470 L470 300" stroke="url(#alu)" stroke-width="18" stroke-linecap="round"/>
<path d="M230 470 H330" stroke="url(#alu)" stroke-width="12" stroke-linecap="round"/>
<path d="M470 470 H570" stroke="url(#alu)" stroke-width="12" stroke-linecap="round"/>
<path d="M200 300 L600 300 L640 340 L240 340 Z" fill="url(#alu)"/>
<path d="M240 340 L640 340 L640 352 L240 352 Z" fill="#7f8690"/>
<path d="M235 298 L250 140 L590 140 L600 298 Z" fill="#1b1d22"/>
<path d="M250 290 L262 152 L578 152 L588 290 Z" fill="url(#ecran)"/>
<path d="M262 152 L420 152 L330 290 L250 290 Z" fill="#fff" fill-opacity=".10"/>`, { cx: 400, cy: 486, rx: 230, ry: 18 }),

  'cable-hdmi-2-m': cadre(['#f6e9ef', '#e6c3d3'], `
<g fill="none" stroke="#1f2227" stroke-width="16" stroke-linecap="round">
  <path d="M190 330 C190 230 330 220 360 300 C390 380 530 380 540 290 C550 210 440 190 420 260 C400 330 520 420 610 360"/>
</g>
<g fill="none" stroke="#fff" stroke-opacity=".14" stroke-width="5" stroke-linecap="round">
  <path d="M195 320 C198 245 320 232 352 296"/>
</g>
<g transform="translate(190 345) rotate(90)">
  <rect x="-10" y="-22" width="44" height="44" rx="8" fill="url(#noir)"/>
  <path d="M34 -16 H70 L64 16 H34 Z" fill="url(#alu)"/>
</g>
<g transform="translate(612 356) rotate(-35)">
  <rect x="-10" y="-22" width="44" height="44" rx="8" fill="url(#noir)"/>
  <path d="M34 -16 H70 L64 16 H34 Z" fill="url(#alu)"/>
</g>`, { cx: 400, cy: 470, rx: 260, ry: 20 }),
};

mkdirSync(join(ici, 'sources'), { recursive: true });
for (const [nom, svg] of Object.entries(illustrations)) {
  writeFileSync(join(ici, 'sources', `${nom}.svg`), svg);
}

// Rendu PNG avec Chromium (Playwright)
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const navigateur = await chromium.launch();
const page = await navigateur.newPage({ viewport: { width: 800, height: 600 } });
for (const [nom, svg] of Object.entries(illustrations)) {
  await page.setContent(`<html><body style="margin:0">${svg}</body></html>`);
  await page.screenshot({ path: join(ici, `${nom}.png`), clip: { x: 0, y: 0, width: 800, height: 600 } });
}
await navigateur.close();
console.log(`${Object.keys(illustrations).length} illustrations générées.`);
