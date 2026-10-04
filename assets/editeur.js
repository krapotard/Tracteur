/* SPDX-License-Identifier: AGPL-3.0-or-later
   Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com> */
"use strict";
/* =====================================================================
   CHARTE du compte (fournie par le serveur : coordonn\u00e9es, couleurs, logo)
   ===================================================================== */
const CHARTE = window.__CHARTE__;
const CONFIG = {
  orgNom: CHARTE.orgNom, orgNomMaj: CHARTE.orgNomMaj,
  siteUrl: CHARTE.site.url, siteTexte: CHARTE.site.texte, siteLibelle: CHARTE.site.libelle,
  fbUrl: CHARTE.facebook.url, fbTexte: CHARTE.facebook.texte, fbLibelle: CHARTE.facebook.libelle,
  mail: CHARTE.mail, adresses: CHARTE.adresses, appel: CHARTE.appel,
  rouge: CHARTE.couleurs.rouge, jaune: CHARTE.couleurs.jaune, texte: CHARTE.couleurs.texte, lien: CHARTE.couleurs.lien,
  bloquerSiErreurs: true,     // true = l'export est refus\u00e9 tant qu'il reste une erreur
  largeurMail: CHARTE.largeurMail || 680
};
const FB = { sans: "Arial,Helvetica,sans-serif", serif: "Georgia,'Times New Roman',serif", cond: "'Arial Narrow',Arial,Helvetica,sans-serif" };
const POLICES = {
  barlow: { nom: "Barlow", f: "Barlow", fb: "sans" },
  "barlow-condensed": { nom: "Barlow Condensed", f: "Barlow Condensed", fb: "cond" },
  atkinson: { nom: "Atkinson Hyperlegible (tr\u00e8s lisible)", f: "Atkinson Hyperlegible", fb: "sans" },
  lexend: { nom: "Lexend (lecture facilit\u00e9e)", f: "Lexend", fb: "sans" },
  "open-sans": { nom: "Open Sans", f: "Open Sans", fb: "sans" },
  roboto: { nom: "Roboto", f: "Roboto", fb: "sans" },
  montserrat: { nom: "Montserrat", f: "Montserrat", fb: "sans" },
  oswald: { nom: "Oswald", f: "Oswald", fb: "cond" },
  lora: { nom: "Lora (avec empattements)", f: "Lora", fb: "serif" },
  bebas: { nom: "Bebas Neue (titres courts, capitales)", f: "Bebas Neue", fb: "cond", affichage: true },
  "permanent-marker": { nom: "Permanent Marker (marqueur, titres courts)", f: "Permanent Marker", fb: "sans", affichage: true },
  fredericka: { nom: "Fredericka the Great (titres courts)", f: "Fredericka the Great", fb: "sans", affichage: true },
  "concert-one": { nom: "Concert One (arrondie, titres)", f: "Concert One", fb: "sans", affichage: true, manque: "\u0153\u0152" },
  "lobster-two": { nom: "Lobster Two (script, titres courts)", f: "Lobster Two", fb: "serif", affichage: true },
  "barlow-semi-condensed": { nom: "Barlow Semi Condensed", f: "Barlow Semi Condensed", fb: "cond" },
  "fira-sans": { nom: "Fira Sans", f: "Fira Sans", fb: "sans" },
  federo: { nom: "Federo (titres courts)", f: "Federo", fb: "sans", affichage: true },
  "caveat-brush": { nom: "Caveat Brush (manuscrit, titres courts)", f: "Caveat Brush", fb: "sans", affichage: true },
  arial: { nom: "Arial (police du syst\u00e8me)", f: null, fb: "sans" }
};
const BARRE_DEFAUT = CHARTE.intertitres !== "aucun";
const POLICE_DEFAUT = POLICES[CHARTE.police] ? CHARTE.police : "barlow";
const pile = id => { const p = POLICES[id] || POLICES[POLICE_DEFAUT]; return (p.f ? `'${p.f}',` : "") + FB[p.fb]; };
const policeBloc = b => b.police && POLICES[b.police] ? b.police : (POLICES[S.police] ? S.police : POLICE_DEFAUT);
const MODELES = {
  greve:  { nom: "1 colonne", aide: "Une seule colonne, texte plus grand, intertitres soulign\u00e9s en rouge. Id\u00e9al pour un texte long." },
  blocus: { nom: "2 colonnes", aide: "Texte sur deux colonnes, encadr\u00e9s color\u00e9s. L'ordre de lecture reste : colonne de gauche, puis colonne de droite." }
};
const LOGO = CHARTE.logo;
const BANQUE_VIDE = { nom: "Banque", version: 1, elements: [] };
const TAILLES = { pleine: 640, grande: 480, moyenne: 320, petite: 160, sixieme: 107, huitieme: 80 };
const CLE_AUTO = "editeur-tracts-brouillon-v2-" + CHARTE.compte;

/* =====================================================================
   Outils
   ===================================================================== */
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const el = (t, c, txt) => { const e = document.createElement(t); if (c) e.className = c; if (txt != null) e.textContent = txt; return e; };
const esc = s => String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
const escA = s => esc(s).replace(/"/g, "&quot;");
const uid = () => "b" + Math.random().toString(36).slice(2, 9);
const debounce = (f, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => f(...a), ms); }; };

function toast(msg, duree) {
  const t = $("#toast"); t.textContent = msg; t.classList.add("vu");
  clearTimeout(toast.t); toast.t = setTimeout(() => t.classList.remove("vu"), duree || 3500);
}
const masquerToast = () => { clearTimeout(toast.t); $("#toast").classList.remove("vu"); };
/* Bandeau d'attente sous les boutons (export PDF) : texte + roue qui tourne */
function montrerAttente(msg) { $("#attenteTexte").textContent = msg; $("#attente").hidden = false; }
function masquerAttente() { $("#attente").hidden = true; $("#attenteTexte").textContent = ""; }

/* Typographie fran\u00e7aise : espaces ins\u00e9cables avant ; : ! ? % \u00bb et apr\u00e8s \u00ab */
function typo(h) {
  return h.split(/(<[^>]+>)/).map((s, i) => i % 2 ? s :
    s.replace(/ +([;:!?%\u00bb])/g, "\u00A0$1")
     .replace(/(\u00ab) +/g, "$1\u00A0")
     .replace(/(?<!\d)(\d{1,3}) (?=\d{3}(?!\d))/g, "$1\u00A0")).join("");
}
const typoTxt = s => typo(esc(s));

/* Nettoyage du HTML saisi : seuls strong, em, a[href], br sont conserv\u00e9s */
function san(n) {
  if (n.nodeType === 3) return esc(n.nodeValue.replace(/\u00A0/g, " "));
  if (n.nodeType !== 1) return "";
  const t = n.tagName.toLowerCase();
  const inner = [...n.childNodes].map(san).join("");
  const st = (n.getAttribute("style") || "").toLowerCase();
  let r = inner;
  if (t === "a") {
    const h = (n.getAttribute("href") || "").trim();
    r = /^(https?:\/\/|mailto:)/i.test(h) && inner ? `<a href="${escA(h)}">${inner}</a>` : inner;
  } else if (t === "br") return "<br>";
  if (!r) return "";
  if (t === "strong" || t === "b" || /font-weight:\s*(bold|[6-9]00)/.test(st)) r = `<strong>${r}</strong>`;
  if (t === "em" || t === "i" || /font-style:\s*italic/.test(st)) r = `<em>${r}</em>`;
  if (n.classList.contains("acc") || couleurDe(n, st) === String(CONFIG.rouge).toLowerCase()) r = `<span class="acc">${r}</span>`;     // seule la couleur d'accentuation de la charte est conservee
  return r;
}
function couleurDe(n, st) {
  let c = n.tagName.toLowerCase() === "font" ? (n.getAttribute("color") || "") : ((st.match(/(?:^|;)\s*color:\s*([^;]+)/) || [])[1] || "");
  c = c.trim().toLowerCase();
  const m = c.match(/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
  return m ? "#" + [m[1], m[2], m[3]].map(x => (+x).toString(16).padStart(2, "0")).join("") : c;
}
const accHtml = h => h.replace(/<span class="acc">/g, `<span class="acc" style="color:${CONFIG.rouge}">`);
function nettoyerHtml(str) {
  const d = new DOMParser().parseFromString("<body>" + String(str) + "</body>", "text/html");
  return [...d.body.childNodes].map(san).join("").trim();
}
const texteDe = h => { const d = new DOMParser().parseFromString("<body>" + h + "</body>", "text/html"); return d.body.textContent.replace(/\u00A0/g, " "); };

function extraireParas(ed) {
  const out = []; let cur = "";
  const flush = () => { const t = cur.replace(/(<br>)+$/, "").replace(/^(<br>)+/, "").trim(); if (texteDe(t).trim()) out.push(t); cur = ""; };
  ed.childNodes.forEach(n => {
    if (n.nodeType === 1 && /^(P|DIV|LI|H[1-6]|BLOCKQUOTE)$/.test(n.tagName)) { flush(); cur = [...n.childNodes].map(san).join(""); flush(); }
    else cur += san(n);
  });
  flush();
  return out;
}

const slugBrut = s => (s || "tract").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/\u0153/g, "oe").replace(/\u00e6/g, "ae").replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "");
const slug = s => { let r = slugBrut(s); if (r.length > 45) { r = r.slice(0, 45); const i = r.lastIndexOf("_"); if (i > 15) r = r.slice(0, i); } return r || "tract"; };
function nomFichier(ext) {
  const d = new Date(), p = n => String(n).padStart(2, "0");
  return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}_${slug(S.titre)}${ext}`;
}
function telecharger(blob, nom) {
  const a = document.createElement("a"); a.href = URL.createObjectURL(blob); a.download = nom;
  document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 4000);
}

/* =====================================================================
   \u00c9tat
   ===================================================================== */
function rotEff(b) { if (b.type !== "image") return 0; const v = parseFloat(b.rot) || 0; return Math.max(-180, Math.min(180, Math.round(v * 2) / 2)); }
const PTN = { 1: 20, 2: 16, 3: 14, 4: 13, 5: 12, 6: 12 };
const stNiv = n => (n >= 5 ? "font-style:italic;" : "") + (n === 6 ? "font-weight:normal;" : "");
/* Titre sur plusieurs lignes : une touche Entree ou le signe | marque la coupure */
const titreSimple = () => S.titre.split(/\s*(?:\||\r?\n)\s*/).map(x => x.trim()).filter(Boolean).join(" ");
const titreLignes = () => S.titre.split(/\s*(?:\||\r?\n)\s*/).map(x => x.trim()).filter(Boolean).map(x => S.titreMaj ? x.toLocaleUpperCase("fr") : x);
const titreAff = () => titreLignes().join(" ");
const titreHtml = lignes => lignes.length > 1 ? lignes.map(l => `<span class="tl">${typoTxt(l)}</span>`).join(" ") : typoTxt(lignes[0] || "");
const sansCache = (k, v) => k.charAt(0) === "_" ? undefined : v;
function nouveauBloc(type) {
  const b = { id: uid(), type, pleine: false, rot: 0, police: "" };
  if (type === "intertitre") Object.assign(b, { niveau: 2, texte: "", orn: null });
  if (type === "texte") Object.assign(b, { paras: [] });
  if (type === "liste") Object.assign(b, { ordonnee: false, items: "" });
  if (type === "image") Object.assign(b, { src: "", svg: "", mime: "", w: 0, h: 0, alt: "", deco: false, credit: "", taille: "pleine", banqueId: "", cote: "", dispo: "cote", paras: [] });
  if (type === "encadre") Object.assign(b, { style: "jaune", paras: [], niveau: 0, portee: "premier", align: "gauche" });
  return b;
}
function exemple() {
  const t = (...p) => Object.assign(nouveauBloc("texte"), { paras: p });
  const h = x => Object.assign(nouveauBloc("intertitre"), { texte: x });
  return {
    version: 1, modele: "greve", align: "justifie", barre: BARRE_DEFAUT, titreMaj: false, police: POLICE_DEFAUT, policeTitre: "", objet: "",
    titre: "Lorem ipsum dolor sit amet, consectetur adipiscing elit",
    blocs: [
      h("Sed ut perspiciatis unde omnis"),
      t("Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
        "Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.",
        "Excepteur sint occaecat cupidatat non proident, <strong>sunt in culpa qui officia deserunt mollit anim id est laborum.</strong> Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium."),
      h("Nemo enim ipsam voluptatem"),
      Object.assign(nouveauBloc("encadre"), { paras: ["<strong>Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse.</strong>"] }),
      Object.assign(nouveauBloc("liste"), { items: "Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet.\nUt enim ad minima veniam, quis nostrum exercitationem ullam corporis.\nAt vero eos et accusamus et iusto odio dignissimos ducimus." }),
      h("Et harum quidem rerum facilis est et expedita distinctio"),
      t("<strong>Nam libero tempore, cum soluta nobis est eligendi optio :</strong> cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est.",
        "Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet, <strong>ut et voluptates repudiandae sint et molestiae non recusandae.</strong>")
    ]
  };
}
const tractVide = () => ({ version: 1, modele: "greve", align: "justifie", barre: BARRE_DEFAUT, titreMaj: false, police: POLICE_DEFAUT, policeTitre: "", objet: "", titre: "", blocs: [nouveauBloc("texte")] });
const departTract = () => CHARTE.depart === "exemple" ? exemple() : tractVide();
let S = departTract();
let blocActif = null;

/* =====================================================================
   Rendu du tract (aper\u00e7u + PDF)
   ===================================================================== */
/* Icones du pied de page (decoratives), aux couleurs de la charte */
const icoPied = nom => {
  const R = CONFIG.rouge, J = CONFIG.jaune;
  const corps = nom === "globe"
    ? `<g fill="none" stroke="${J}" stroke-width="1.5"><circle cx="12" cy="12" r="6.5"/><ellipse cx="12" cy="12" rx="2.8" ry="6.5"/><path d="M5.5 12h13M7 8h10M7 16h10"/></g>`
    : `<g fill="none" stroke="${J}" stroke-width="1.6" stroke-linejoin="round"><rect x="5.5" y="7.5" width="13" height="9" rx="1.2"/><path d="M5.8 8.3l6.2 5 6.2-5"/></g>`;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><circle cx="12" cy="12" r="11.5" fill="${R}"/>${corps}</svg>`;
  return `<img class="ico" src="data:image/svg+xml;base64,${btoa(svg)}" alt="">`;
};
const imgDecor = (d, cls) => d ? `<img${cls ? ` class="${cls}"` : ""} src="${d.svg || d.png}" alt="${escA(d.alt || "")}"${d.w ? ` width="${d.w}" height="${d.h}"` : ""}>` : "";
/* Pied de page compose : decor (image) + vrais textes positionnes d'apres leur ligne de base dans le SVG d'origine */
const _base = {};
function ligneBase(police, graisse) {      // position de la ligne de base (en em) dans une ligne de hauteur 1em, pour cette police
  const k = police + "|" + graisse, fam = pile(police);
  if (k in _base) return _base[k];
  const p = document.createElement("div");
  p.style.cssText = `position:absolute;left:-9999px;top:0;font-family:${fam};font-weight:${graisse};font-size:100px;line-height:1;white-space:nowrap`;
  p.innerHTML = 'Hxg<span style="display:inline-block;width:1px;height:0"></span>';
  document.body.appendChild(p);
  const v = (p.querySelector("span").getBoundingClientRect().top - p.getBoundingClientRect().top) / 100;
  p.remove();
  if (document.fonts && document.fonts.check(`${graisse} 100px ${fam}`)) _base[k] = v;     // on ne retient que les mesures faites avec la vraie police
  return v;
}
/* Titre du tract place DANS un en-tete dessine : zone definie par l'emplacement {titre} du SVG ; reduit automatiquement s'il est trop long */
let titreDebord = false, titreRatio = 1;
const _cvx = document.createElement("canvas").getContext("2d");
function nbLignesTitre(segments, police, graisse, tailleMm, largeurMm, ls) {       // segments : lignes imposees par l'auteur, chacune pouvant se replier
  const px = 96 / 25.4; _cvx.font = `${graisse} ${tailleMm * px}px ${pile(police)}`;
  const mesure = s => _cvx.measureText(s).width + ls * px * s.length;
  let n = 0, trop = false;
  for (const seg of [].concat(segments)) {
    let k = 1, cur = "";
    for (const mot of seg.split(/\s+/).filter(Boolean)) {
      const essai = cur ? cur + " " + mot : mot;
      if (cur && mesure(essai) > largeurMm * px) { k++; cur = mot; } else cur = essai;
      if (mesure(cur) > largeurMm * px) trop = true;
    }
    n += k;
  }
  return { n, trop };
}
function titreEnteteHtml(t, P) {
  const police = S.policeTitre && POLICES[S.policeTitre] ? S.policeTitre : t.police;
  const lignes = titreSimple() ? titreLignes() : ["Titre du tract"], L = t.interligne || 1.15, ls = t.interlettrage || 0;
  let largeur = t.largeur;
  if (!largeur) largeur = t.ancre === "milieu" ? Math.max(20, 2 * Math.min(t.x, P.w - t.x - 8)) : t.ancre === "fin" ? Math.max(20, t.x - 4) : Math.max(20, Math.min(130, P.w - t.x - 8));
  const decal = taille => (ligneBase(police, t.graisse) + (L - 1) / 2) * taille;       // du haut de la 1re ligne a sa ligne de base
  // le titre ne doit pas recouvrir les autres textes de l'en-tete situes en dessous de lui (sous-titre, adresses...)
  const px = 96 / 25.4, gaucheZone = t.ancre === "milieu" ? t.x - largeur / 2 : t.ancre === "fin" ? t.x - largeur : t.x;
  let basMax = P.h;
  P.textes.forEach(o => {
    if (o === t || o.role === "titre" || o.actif === false) return;
    _cvx.font = `${o.graisse} ${o.taille * px}px ${pile(o.police)}`;
    const w = _cvx.measureText(o.texte).width / px + (o.interlettrage || 0) * o.texte.length, hautO = o.y - 0.85 * o.taille;
    if (hautO > t.y && o.x < gaucheZone + largeur && o.x + w > gaucheZone) basMax = Math.min(basMax, hautO - 0.3);
  });  const mini = t.taille * 0.55; let taille = t.taille, tient = false;
  for (; taille >= mini - 1e-6; taille = Math.round((taille - 0.2) * 100) / 100) {
    const r = nbLignesTitre(lignes, police, t.graisse, taille, largeur, ls), haut = t.y - decal(taille), bas = haut + r.n * L * taille;
    if (!r.trop && haut >= -0.2 && bas <= basMax + 0.2) { tient = true; break; }
  }
  if (!tient) taille = Math.round(mini * 100) / 100;
  titreDebord = !tient; titreRatio = taille / t.taille;
  const top = Math.round((t.y - decal(taille)) * 100) / 100, gauche = t.ancre === "milieu" ? t.x - largeur / 2 : t.ancre === "fin" ? t.x - largeur : t.x;
  const al = t.ancre === "milieu" ? "center" : t.ancre === "fin" ? "right" : "left";
  return `<h1 class="pc-titre${titreSimple() ? "" : " vide"}" style="left:${Math.round(gauche * 100) / 100}mm;top:${top}mm;width:${largeur}mm;font-family:${pile(police)};font-weight:${t.graisse};font-size:${taille}mm;line-height:${L};color:${t.couleur};text-align:${al};letter-spacing:${ls}mm">${titreHtml(lignes)}</h1>`;
}
function composeHtml(P, balise) {          // balise : "header" (en-tete) ou "footer" (pied de page)
  const C = CONFIG;
  const items = P.textes.filter(t => t.actif !== false).map(t => {
    if (t.role === "titre") return titreEnteteHtml(t, P);
    const top = Math.round((t.y - ligneBase(t.police, t.graisse) * t.taille) * 100) / 100;
    const st = `left:${t.x}mm;top:${top}mm;font-family:${pile(t.police)};font-weight:${t.graisse};font-size:${t.taille}mm;color:${t.couleur};letter-spacing:${t.interlettrage}mm`;
    const txt = esc(t.texte), tn = t.texte.trim().toLowerCase();
    let contenu = txt;
    if (C.siteUrl && tn === (C.siteTexte || "").toLowerCase()) contenu = `<a href="${C.siteUrl}">${txt}</a>`;
    else if (C.mail && tn === C.mail.trim().toLowerCase()) contenu = `<a href="mailto:${C.mail}">${txt}</a>`;
    else if (C.fbUrl && tn === (C.fbTexte || "").toLowerCase()) contenu = `<a href="${C.fbUrl}">${txt}</a>`;
    return `<p style="${st}">${contenu}</p>`;
  }).join("");
  const classe = balise === "header" ? "tete-compose" : "pied-compose", sty = balise === "header" ? "" : ' style="break-inside:avoid;break-before:avoid"';
  return `<${balise} class="${classe}"${sty}><div class="tr-pc" style="width:${P.w}mm;height:${P.h}mm${P.cote === "droite" ? ";margin-left:auto" : ""}"><img class="pc-fond" src="${P.svg}" alt="" width="${P.w}" height="${P.h}">${items}</div></${balise}>`;
}
const piedComposeHtml = () => composeHtml(CHARTE.piedCompose, "footer");
function tractHtml() {
  titreDebord = false;
  const titreEnTete = !!(CHARTE.enteteCompose && CHARTE.enteteCompose.textes.some(t => t.role === "titre" && t.actif !== false));
  const C = CONFIG;
  const contenu = b => {
    if (b.type === "intertitre") {
      const t = b.texte.trim(); if (!t) return "";
      const txt = typoTxt(t), n = b.niveau;
      if (!b.orn) return `<h${n}>${txt}</h${n}>`;
      const o = b.orn, im = `<img class="orn orn-${o.taille}" src="${o.svg || o.src}" alt="${o.decoratif ? "" : escA(o.alt)}">`;
      return `<h${n} class="ornee">${o.cote === "droite" ? `<span class="orn-t">${txt}</span>${im}` : `${im}<span class="orn-t">${txt}</span>`}</h${n}>`;
    }
    if (b.type === "texte") return b.paras.map(p => `<p>${typo(p)}</p>`).join("");
    if (b.type === "liste") {
      const it = b.items.split("\n").map(x => x.trim()).filter(Boolean);
      return it.length ? `<${b.ordonnee ? "ol" : "ul"}>${it.map(x => `<li>${typoTxt(x)}</li>`).join("")}</${b.ordonnee ? "ol" : "ul"}>` : "";
    }
    if (b.type === "image") {
      if (!b.src) return "";
      const duo = b.cote && b.paras.length;
      const r = rotEff(b), rotAttr = r ? ` class="rot" data-rot="${r}" style="transform:rotate(${r}deg);margin:${margesRot[b.id] || 0}px 0"` : "";
      const fig = `<figure class="img t-${b.taille || "pleine"}"><img${rotAttr} src="${b.svg || b.src}" alt="${b.deco ? "" : escA(b.alt)}">${b.credit.trim() ? `<figcaption>${typoTxt(b.credit.trim())}</figcaption>` : ""}</figure>`;
      if (!duo) return fig;
      const txt = `<div class="duo-txt">${b.paras.map(p => `<p>${typo(p)}</p>`).join("")}</div>`;
      const hab = b.dispo === "habillage";
      return `<div class="duo duo-${b.cote}${hab ? " habille" : ""}">${b.cote === "droite" && !hab ? txt + fig : fig + txt}</div>`;
    }
    if (b.type === "encadre") {
      if (!b.paras.length) return "";
      const L = b.niveau | 0, tg = i => L && (b.portee === "tout" || i === 0) ? "h" + L : "p";
      return `<div class="enc enc-${b.style}${b.align === "centre" ? " centre" : ""}">${b.paras.map((p, i) => `<${tg(i)}>${typo(p)}</${tg(i)}>`).join("")}</div>`;
    }
    return "";
  };
  const corps = S.blocs.map(b => {
    let h = contenu(b); if (!h) return "";
    return `<div class="bl${b.pleine ? " pleine" : ""}" data-b="${b.id}"${b.police && POLICES[b.police] ? ` style="font-family:${pile(b.police)}"` : ""}>${h}</div>`;
  }).join("\n");
  const coords = C.adresses.map(a => `<p class="coord">${a.map(esc).join("<br>")}</p>`).join("");
  return `<div class="tract t-${S.modele} al-${S.align === "gauche" ? "gauche" : "justifie"}${S.barre ? (CHARTE.intertitres === "souligne" ? " souligne-on" : " barre-on") : ""}${CHARTE.logoCote === "gauche" ? " logo-gauche" : ""}${CHARTE.enteteStyle === "filet" ? " tete-filet" : ""}${CHARTE.encadres === "arrondi" ? " enc-ronds" : ""}${CHARTE.enteteImage || CHARTE.enteteCompose ? " avec-masthead" : ""}${CHARTE.logoSvg || LOGO ? "" : " sans-logo"}${titreEnTete ? " titre-en-tete" : ""}" style="font-family:${pile(S.police)};--mmin:${mainMinPx}px;--esp-tete:${CHARTE.espaceEntete ?? 6}mm">
${CHARTE.enteteCompose ? composeHtml(CHARTE.enteteCompose, "header") : CHARTE.enteteImage ? `<header class="tr-masthead">${imgDecor(CHARTE.enteteImage)}</header>` : `<header class="tr-head${CHARTE.enteteFond ? " avec-fond" : ""}">${imgDecor(CHARTE.enteteFond, "fond")}<div><p class="org">${esc(C.orgNomMaj)}</p>${C.siteUrl ? `<p><a href="${C.siteUrl}">${esc(C.siteTexte)}</a></p>` : ""}${C.fbUrl ? `<p><a href="${C.fbUrl}">${esc(C.fbTexte)}</a></p>` : ""}</div>${coords}${CHARTE.logoSvg || LOGO ? `<img class="tr-logo" src="${CHARTE.logoSvg || LOGO}" alt="">` : ""}</header>`}
<main class="tr-main">${titreEnTete ? "" : `<div class="tr-title"><h1${titreSimple() ? "" : ' class="vide"'}${S.policeTitre && POLICES[S.policeTitre] ? ` style="font-family:${pile(S.policeTitre)}"` : ""}>${titreSimple() ? titreHtml(titreLignes()) : "Titre du tract"}</h1></div>`}<div class="tr-corps">${accHtml(corps)}</div></main>
${CHARTE.piedCompose ? piedComposeHtml() : `<footer style="break-inside:avoid;break-before:avoid">${CHARTE.piedImage ? `<div class="tr-pied-img"><div style="width:${Math.min(186, Math.round(piedMm * 10 * CHARTE.piedImage.w / CHARTE.piedImage.h) / 10)}mm">${imgDecor(CHARTE.piedImage)}</div></div>` : ""}<div class="tr-foot${CHARTE.piedFond ? " avec-fond" + (CHARTE.piedTexte === "clair" ? " clair" : "") : ""}">${imgDecor(CHARTE.piedFond, "fond")}${C.appel ? `<p class="appel">${esc(C.appel).replace(", ", ",<br>")}</p>` : ""}<div>${C.siteUrl ? `<p>${CHARTE.iconesPied ? icoPied("globe") : ""}<a href="${C.siteUrl}">${esc(C.siteTexte)}</a></p>` : ""}${C.mail ? `<p>${CHARTE.iconesPied ? icoPied("mail") : ""}<a href="mailto:${C.mail}">${esc(C.mail)}</a></p>` : ""}</div></div><div class="tr-bar"></div></footer>`}
</div>`;
}

/* =====================================================================
   Rendu du mail (HTML en ASCII, styles en ligne, mise en page en tableaux)
   ===================================================================== */
const F = "font-family:Arial,Helvetica,sans-serif;";
const LOGO_CID = "logo@tract.local";
const ffam = id => "font-family:" + pile(id) + ";";
function inlineMail(h, couleurLien, ff) {
  ff = ff || F;
  return typo(accHtml(h)).replace(/<a href="([^"]*)">/g, `<a href="$1" style="${ff}color:${couleurLien};text-decoration:underline">`);
}
function mailHtml(apercu) {
  const C = CONFIG, images = []; let n = 0;
  const bt = CONFIG.texte;
  const pSr = `font-size:12pt;line-height:1.5;margin:0 0 12px 0;color:${bt}`;
  const blocs = S.blocs.map(b => {
    const Fb = ffam(policeBloc(b));
    if (b.type === "intertitre") {
      if (!b.texte.trim()) return "";
      const sz = PTN[b.niveau] + "pt", txt = typoTxt(b.texte.trim()), sn = stNiv(b.niveau);
      if (!b.orn) return `<h${b.niveau} style="${Fb}font-size:${sz};line-height:1.25;margin:18px 0 10px 0;color:${bt};${sn}">${txt}</h${b.niveau}>`;
      const o = b.orn, droite = o.cote === "droite";
      n++; const cid = `img${n}@tract.local`, ext = o.mime === "image/png" ? "png" : "jpg";
      images.push({ cid, mime: o.mime, nom: `image${n}.${ext}`, b64: o.src.split(",")[1] });
      const hPx = Math.round((PTN[b.niveau] * 96 / 72) * ({ petite: 1.2, moyenne: 1.8, grande: 2.6 }[o.taille] || 1.8)), wPx = Math.round(hPx * o.w / o.h);
      const im = `<img src="${apercu ? o.src : "cid:" + cid}" alt="${o.decoratif ? "" : escA(o.alt)}" width="${wPx}" height="${hPx}" style="width:${wPx}px;height:${hPx}px;border:0">`;
      const cI = `<td valign="middle" style="padding:18px ${droite ? 0 : 12}px 10px ${droite ? 12 : 0}px">${im}</td>`;
      const cT = `<td valign="middle" style="padding:18px 0 10px 0"><h${b.niveau} style="${Fb}font-size:${sz};line-height:1.25;margin:0;color:${bt};${sn}">${txt}</h${b.niveau}></td>`;
      return `<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>${droite ? cT + cI : cI + cT}</tr></table>`;
    }
    if (b.type === "texte") return b.paras.map(p => `<p style="${Fb}${pSr}">${inlineMail(p, C.lien, Fb)}</p>`).join("\n");
    if (b.type === "liste") {
      const it = b.items.split("\n").map(x => x.trim()).filter(Boolean); if (!it.length) return "";
      const tag = b.ordonnee ? "ol" : "ul";
      return `<${tag} style="${Fb}font-size:12pt;line-height:1.5;margin:0 0 12px 24px;padding:0;color:${bt}">` + it.map(x => `<li style="margin-bottom:6px">${typoTxt(x)}</li>`).join("\n") + `</${tag}>`;
    }
    if (b.type === "image") {
      if (!b.src) return "";
      n++; const cid = `img${n}@tract.local`;
      const duo = b.cote && b.paras.length, r = rotEff(b);
      const rot = r && b._rot && b._rot.cle === r + "|" + b.src.length ? b._rot : null;
      const mime = rot ? rot.mime : b.mime, srcImg = rot ? rot.src : b.src, ext = mime === "image/png" ? "png" : "jpg";
      images.push({ cid, mime, nom: `image${n}.${ext}`, b64: srcImg.split(",")[1] });
      const px = duo ? ({ huitieme: 80, sixieme: 100, petite: 130, moyenne: 220, grande: 320, pleine: 320 }[b.taille] || 220) : (TAILLES[b.taille] || 640);
      let w = Math.min(b.w || px, px), h = Math.round(w * (b.h || w) / (b.w || w));
      if (rot) { const k = rot.w / (b.w || rot.w); w = Math.min(Math.round(w * k), 640); h = Math.round(w * rot.h / rot.w); }
      const alt = b.deco ? "" : escA(b.alt);
      if (duo) {
        const pic = `<img src="${apercu ? srcImg : "cid:" + cid}" alt="${alt}" width="${w}" height="${h}" style="width:${w}px;max-width:100%;height:auto;border:0">` +
          (b.credit.trim() ? `<p style="${Fb}font-size:9pt;line-height:1.4;margin:4px 0 0 0;color:#595959">${typoTxt(b.credit.trim())}</p>` : "");
        const cI = `<td valign="top" width="${w + 16}" style="width:${w + 16}px;padding:0 ${b.cote === "gauche" ? 16 : 0}px 12px ${b.cote === "droite" ? 16 : 0}px">${pic}</td>`;
        const cT = `<td valign="top" style="padding:0 0 12px 0">` + b.paras.map((p, i) => `<p style="${Fb}font-size:12pt;line-height:1.5;margin:0 0 ${i === b.paras.length - 1 ? 0 : 12}px 0;color:${bt}">${inlineMail(p, C.lien, Fb)}</p>`).join("\n") + `</td>`;
        return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>${b.cote === "gauche" ? cI + cT : cT + cI}</tr></table>`;
      }
      return `<p style="margin:0 0 12px 0"><img src="${apercu ? srcImg : "cid:" + cid}" alt="${alt}" width="${w}" height="${h}" style="width:${w}px;max-width:100%;height:auto;border:0"></p>` +
        (b.credit.trim() ? `<p style="${Fb}font-size:9pt;line-height:1.4;margin:-6px 0 12px 0;color:#595959">${typoTxt(b.credit.trim())}</p>` : "");
    }
    if (b.type === "encadre") {
      if (!b.paras.length) return "";
      const st = { jaune: [C.jaune, bt, C.lien], rouge: [C.rouge, "#ffffff", "#ffffff"], gris: ["#EDEDED", bt, C.lien] }[b.style];
      const L = b.niveau | 0, al = b.align === "centre" ? "center" : "left", pt = PTN;
      return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:0 0 12px 0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="${al}" style="${Fb}background:${st[0]};padding:14px 16px;font-size:12pt;line-height:1.5;color:${st[1]};text-align:${al}">` +
        b.paras.map((p, i) => { const mg = i === b.paras.length - 1 ? 0 : 8, hd = L && (b.portee === "tout" || i === 0);
          return hd ? `<h${L} style="${Fb}font-size:${pt[L]}pt;line-height:1.25;font-weight:bold;margin:0 0 ${mg}px 0;color:${st[1]};text-align:${al}">${inlineMail(p, st[2], Fb)}</h${L}>`
                    : `<p style="${Fb}font-size:12pt;line-height:1.5;margin:0 0 ${mg}px 0;color:${st[1]};text-align:${al}">${inlineMail(p, st[2], Fb)}</p>`; }).join("\n") + `</td></tr></table></td></tr></table>`;
    }
    return "";
  }).filter(Boolean).join("\n");
  const titre = titreSimple() || "Tract", lignesT = titreSimple() ? titreLignes() : ["Tract"], titreMail = lignesT.map(typoTxt).join(" <br>");
  const titreEnEntete = !!titreSimple() && !(CHARTE.enteteImage && CHARTE.enteteImage.png);       // en-tete standard : le titre remplace le nom de l'organisation
  const logoSrc = apercu ? LOGO : "cid:" + LOGO_CID;
  const adr = C.adresses.map((a, i) => `<p style="${ffam(S.police)}font-size:11pt;line-height:1.4;margin:0 0 ${i ? 0 : 2}px 0;color:${bt}">${esc(a.join(", ")).replace(/ ([;:!?%])/g, "&nbsp;$1")}</p>`).join("\n");
  const mimeDe = u => u.slice(5, u.indexOf(";"));
  const imgMail = (d, cid, nom) => { images.push({ cid, mime: mimeDe(d.png), nom, b64: d.png.split(",")[1] }); return `<img src="${apercu ? d.png : "cid:" + cid}" alt="${escA(d.alt)}" width="${C.largeurMail}" style="display:block;width:100%;max-width:${C.largeurMail}px;height:auto;border:0">`; };
  const Fd = ffam(S.police), Ft = ffam(S.policeTitre && POLICES[S.policeTitre] ? S.policeTitre : S.police), coulEnt = CHARTE.enteteStyle === "filet" ? C.rouge : "#ffffff";
  const html = `<!DOCTYPE html>
<html lang="fr"><head><title>${esc(titre)}</title></head>
<body style="margin:0">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center">
<table role="presentation" width="${C.largeurMail}" style="width:${C.largeurMail}px;max-width:100%" cellpadding="0" cellspacing="0" border="0">
${CHARTE.enteteImage && CHARTE.enteteImage.png ? `<tr><td lang="fr" style="padding:0;line-height:0;font-size:0">${imgMail(CHARTE.enteteImage, "entete@tract.local", "entete.png")}</td></tr>` : `<tr><td lang="fr" style="${Fd}${CHARTE.enteteStyle === "filet" ? `border-bottom:4px solid ${C.rouge};` : `background:${C.rouge};`}padding:12px 16px"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
${LOGO ? `<td style="padding-right:14px"><img src="${logoSrc}" alt="" width="64" height="${Math.round(64 * (CHARTE.logoRatio || 1.0469))}" style="width:64px;height:auto;border:0"></td>` : ""}
<td style="${Fd}font-size:16pt;line-height:1.3;font-weight:bold;color:${coulEnt}">${titreEnEntete ? `<h1 style="${Ft}font-size:18pt;line-height:1.25;font-weight:bold;margin:0;color:${coulEnt}">${titreMail}</h1>` : esc(C.orgNom)}</td></tr></table></td></tr>`}
<tr><td lang="fr" style="padding:16px;${Fd}font-size:12pt;line-height:1.5;color:${bt}">
${titreEnEntete ? "" : `<h1 style="${Ft}font-size:20pt;line-height:1.25;margin:0 0 12px 0;color:${bt}">${titreMail}</h1>`}
${blocs}
</td></tr>
<tr><td lang="fr" style="${Fd}background:#f2f2f2;padding:16px;font-size:11pt;line-height:1.4;color:${bt}">${CHARTE.piedImage && CHARTE.piedImage.png ? `<div style="margin:0 0 14px 0;line-height:0;font-size:0;background:#ffffff">${imgMail(CHARTE.piedImage, "pied@tract.local", "pied.png")}</div>` : ""}
${C.appel ? `<p style="${Fd}font-size:11pt;line-height:1.4;margin:0 0 10px 0;font-weight:bold;color:${bt}">${esc(C.appel)}</p>` : ""}
${C.siteUrl ? `<p style="${Fd}font-size:11pt;line-height:1.4;margin:0 0 6px 0;color:${bt}"><a href="${C.siteUrl}" style="${Fd}color:${C.lien};text-decoration:underline">${esc(C.siteLibelle)}</a> (${esc(C.siteTexte)})</p>` : ""}
${C.fbUrl ? `<p style="${Fd}font-size:11pt;line-height:1.4;margin:0 0 6px 0;color:${bt}"><a href="${C.fbUrl}" style="${Fd}color:${C.lien};text-decoration:underline">${esc(C.fbLibelle)}</a></p>` : ""}
${C.mail ? `<p style="${Fd}font-size:11pt;line-height:1.4;margin:0 0 10px 0;color:${bt}">\u00c9crire au syndicat : <a href="mailto:${C.mail}" style="${Fd}color:${C.lien};text-decoration:underline">${esc(C.mail)}</a></p>` : ""}
${adr}
</td></tr>
</table>
</td></tr></table>
</body></html>`;
  return { html, images, logo: LOGO ? { cid: LOGO_CID, mime: "image/png", nom: "logo.png", b64: LOGO.split(",")[1] } : null };
}

/* =====================================================================
   Export .eml
   ===================================================================== */
function toAscii(s) { let o = ""; for (const ch of s) { const c = ch.codePointAt(0); o += c > 127 ? "&#" + c + ";" : ch; } return o; }
function wrapText(s) {
  if (s.length <= 200) return s;
  const lead = s.match(/^ */)[0], trail = s.match(/ *$/)[0], mid = s.trim(); let out = "", line = "";
  for (const w of mid.split(" ")) { if (line && line.length + w.length + 1 > 200) { out += line + "\n"; line = w; } else line = line ? line + " " + w : w; }
  return lead + out + line + trail;
}
const wrapHtml = h => h.split(/(<[^>]+>)/).map((s, i) => i % 2 ? s : wrapText(s)).join("");
function b64Lignes(b64) { return b64.replace(/(.{76})/g, "$1\r\n").replace(/\r\n$/, ""); }
function utf8b64(str) { return btoa(String.fromCharCode(...new TextEncoder().encode(str))); }
function utf8b64Long(str) { const by = new TextEncoder().encode(str); let bin = ""; for (let i = 0; i < by.length; i += 8192) bin += String.fromCharCode(...by.subarray(i, i + 8192)); return btoa(bin); }
function encodeMot(s) {
  if (/^[\x20-\x7E]*$/.test(s)) return s;
  const ch = []; let cur = "", len = 0; const te = new TextEncoder();
  for (const c of s) { const l = te.encode(c).length; if (len + l > 45) { ch.push(cur); cur = ""; len = 0; } cur += c; len += l; }
  if (cur) ch.push(cur);
  return ch.map(x => "=?UTF-8?B?" + utf8b64(x) + "?=").join("\r\n ");
}
function texteBrut() {
  const l = [titreSimple(), ""];
  const plain = h => { const d = new DOMParser().parseFromString("<body>" + h + "</body>", "text/html"); d.querySelectorAll("a").forEach(a => { a.textContent = a.textContent + " (" + a.getAttribute("href") + ")"; }); return d.body.textContent.replace(/\u00A0/g, " "); };
  S.blocs.forEach(b => {
    if (b.type === "intertitre" && b.texte.trim()) l.push("", b.texte.trim(), "");
    if (b.type === "texte" || b.type === "encadre") b.paras.forEach(p => l.push(plain(p), ""));
    if (b.type === "liste") b.items.split("\n").filter(x => x.trim()).forEach(x => l.push("- " + x.trim()));
    if (b.type === "image" && b.src && !b.deco && b.alt.trim()) l.push("[Image : " + b.alt.trim() + "]", "");
    if (b.type === "image" && b.src && b.cote) b.paras.forEach(p => l.push(plain(p), ""));
  });
  l.push("", ...[CONFIG.appel, CONFIG.siteUrl, CONFIG.fbUrl, CONFIG.mail].filter(Boolean));
  return l.join("\r\n");
}
function construireEml(pdf) {
  const m = mailHtml(false);
  const html = wrapHtml(toAscii(m.html)).replace(/\r?\n/g, "\r\n");
  const rnd = () => Math.random().toString(36).slice(2, 10);
  const B0 = "=_mix_" + rnd(), B1 = "=_alt_" + rnd(), B2 = "=_rel_" + rnd();
  const longue = html.split("\r\n").some(x => x.length > 900);
  const o = [];
  o.push("MIME-Version: 1.0", "Subject: " + encodeMot((S.objet || titreSimple() || "Tract").trim()), "X-Unsent: 1",
    pdf ? 'Content-Type: multipart/mixed; boundary="' + B0 + '"' : 'Content-Type: multipart/alternative; boundary="' + B1 + '"', "");
  if (pdf) o.push("--" + B0, 'Content-Type: multipart/alternative; boundary="' + B1 + '"', "");
  o.push("--" + B1, 'Content-Type: text/plain; charset="utf-8"', "Content-Transfer-Encoding: base64", "", b64Lignes(utf8b64Long(texteBrut())));
  o.push("--" + B1, 'Content-Type: multipart/related; type="text/html"; boundary="' + B2 + '"', "");
  o.push("--" + B2, 'Content-Type: text/html; charset="utf-8"');
  if (longue) o.push("Content-Transfer-Encoding: base64", "", b64Lignes(utf8b64Long(html)));
  else o.push("Content-Transfer-Encoding: 7bit", "", html);
  [m.logo, ...m.images].filter(Boolean).forEach(im => {
    o.push("--" + B2, `Content-Type: ${im.mime}; name="${im.nom}"`, "Content-Transfer-Encoding: base64",
      `Content-ID: <${im.cid}>`, `Content-Disposition: inline; filename="${im.nom}"`, "", b64Lignes(im.b64));
  });
  o.push("--" + B2 + "--", "--" + B1 + "--");
  if (pdf) o.push("--" + B0, `Content-Type: application/pdf; name="${pdf.nom}"`, "Content-Transfer-Encoding: base64",
    `Content-Disposition: attachment; filename="${pdf.nom}"`, "", b64Lignes(pdf.b64), "--" + B0 + "--");
  o.push("");
  return o.join("\r\n");
}
function telechargerEml(pdf) { telecharger(new Blob([construireEml(pdf)], { type: "message/rfc822" }), nomFichier(".eml")); }

/* =====================================================================
   V\u00e9rifications d'accessibilit\u00e9
   ===================================================================== */
let pagesTract = 1, alertesGeo = [], margesRot = {}, mainMinPx = 0, risquePageBlanche = false;
const PIED_MAX_MM = 42, PIED_MIN_MM = 14;     // hauteur de l'image de pied : elle r\u00e9tr\u00e9cit d'elle-m\u00eame pour \u00e9viter une page de plus
let piedMm = PIED_MAX_MM, natMm = 0, piedSeul = false;
const LIENS_VAGUES = /^((voir|cliquez|clique|cliquer|lire)( ici| l\u00e0)|ici|l\u00e0|lien|ce lien|en savoir plus|plus d['\u2019]infos?|voir|suite|lire la suite|par ici)$/i;
function verifier() {
  const pb = [];
  const add = (niv, msg, id) => pb.push({ niv, msg, id });
  if (!titreSimple()) add("erreur", "Le tract n\u2019a pas de titre.", null);
  else if (titreLignes().length > 2) add("avert", "Le titre est coup\u00e9 en plus de deux lignes : un titre court est plus lisible.", null);
  else if (S.titreMaj && S.titre.replace(/[^A-Za-z\u00c0-\u00ff]/g, "").length > 30) add("avert", "Le titre est long et affich\u00e9 en majuscules : il sera plus difficile \u00e0 lire.", null);
  let longSansTitre = 0, nTitres = 0, dernierNiv = 1;
  S.blocs.forEach((b, i) => {
    const nom = `Bloc ${i + 1}`;
    if (POLICES[policeBloc(b)].affichage && (b.type === "texte" || b.type === "encadre" || b.type === "liste")) {
      const lg = b.type === "liste" ? b.items.length : b.paras.reduce((n, p) => n + texteDe(p).length, 0);
      if (lg > 150) add("avert", `${nom} : cette police d\u00e9corative convient mal \u00e0 un long texte (r\u00e9servez-la aux titres courts).`, b.id);
    }
    if (b.type === "intertitre") {
      if (!b.texte.trim()) { add("avert", `${nom} (intertitre) : vide, il sera ignor\u00e9.`, b.id); return; }
      nTitres++; longSansTitre = 0;
      if (b.orn && !b.orn.decoratif && !b.orn.alt.trim()) add("erreur", `${nom} : la d\u00e9coration de l\u2019intertitre n\u2019a pas de texte alternatif (ou cochez \u00ab D\u00e9corative \u00bb).`, b.id);
      if (b.niveau === 1) add("avert", `${nom} : un seul titre de niveau 1 est recommand\u00e9 (le titre du tract en est d\u00e9j\u00e0 un). Utilisez un niveau 2 ou plus.`, b.id);
      else if (b.niveau > dernierNiv + 1) add("erreur", `${nom} : saut de niveau de titre (de H${dernierNiv} \u00e0 H${b.niveau}). Les niveaux doivent se suivre sans trou.`, b.id);
      dernierNiv = b.niveau;
      if (b.texte.length > 120) add("avert", `${nom} : intertitre tr\u00e8s long (plus de 120 caract\u00e8res).`, b.id);
      const lettres = b.texte.replace(/[^A-Za-z\u00c0-\u00ff]/g, "");
      if (lettres.length > 30 && b.texte === b.texte.toUpperCase()) add("avert", `${nom} : intertitre tout en majuscules, plus difficile \u00e0 lire.`, b.id);
    }
    const controleParas = () => b.paras.forEach(p => {
      const t = texteDe(p);
      longSansTitre += t.length;
      const lettres = t.replace(/[^A-Za-z\u00c0-\u00ff]/g, "");
      if (lettres.length > 30 && t === t.toUpperCase()) add("avert", `${nom} : paragraphe tout en majuscules, plus difficile \u00e0 lire.`, b.id);
      for (const m of p.matchAll(/<a href="([^"]*)">([\s\S]*?)<\/a>/g)) {
        const lt = texteDe(m[2]).trim();
        if (LIENS_VAGUES.test(lt) || /^(https?:\/\/|www\.)/i.test(lt)) add("avert", `${nom} : le lien \u00ab ${lt.slice(0, 40)} \u00bb n\u2019est pas explicite. Utilisez des mots qui se comprennent seuls.`, b.id);
      }
    });
    if (b.type === "texte" || b.type === "encadre") {
      if (!b.paras.length) { add("avert", `${nom} (${b.type === "texte" ? "texte" : "encadr\u00e9"}) : vide, il sera ignor\u00e9.`, b.id); return; }
      if (b.type === "encadre" && b.niveau) {
        if (b.niveau === 1) add("avert", `${nom} : un seul titre de niveau 1 est recommand\u00e9 (le titre du tract en est d\u00e9j\u00e0 un). Utilisez un niveau 2 ou plus.`, b.id);
        else if (b.niveau > dernierNiv + 1) add("avert", `${nom} : saut de niveau de titre (de H${dernierNiv} \u00e0 H${b.niveau}). Les niveaux doivent se suivre sans trou.`, b.id);
        dernierNiv = b.niveau;
      }
      controleParas();
    }
    if (b.type === "liste" && !b.items.trim()) add("avert", `${nom} (liste) : vide, elle sera ignor\u00e9e.`, b.id);
    if (b.type === "image") {
      if (!b.src) { add("erreur", `${nom} (image) : aucune image choisie.`, b.id); return; }
      if (b.cote) { if (b.paras.length) controleParas(); else add("avert", `${nom} (image) : le texte \u00e0 c\u00f4t\u00e9 de l\u2019image est vide, il sera ignor\u00e9.`, b.id); }
      if (!b.deco) {
        const a = b.alt.trim();
        if (!a) add("erreur", `${nom} (image) : le texte alternatif est obligatoire (ou cochez \u00ab image d\u00e9corative \u00bb).`, b.id);
        else {
          if (/\.(png|jpe?g|gif|webp)$/i.test(a) || /^(img|dsc|image)[_\-\s]?\d+/i.test(a)) add("erreur", `${nom} (image) : le texte alternatif ressemble \u00e0 un nom de fichier.`, b.id);
          if (a.length > 150) add("avert", `${nom} (image) : texte alternatif de plus de 150 caract\u00e8res. Allez \u00e0 l\u2019essentiel.`, b.id);
          if (/^(image|photo|photographie|capture)\s+(de|d['\u2019])/i.test(a)) add("avert", `${nom} (image) : inutile de commencer par \u00ab image de \u00bb ou \u00ab photo de \u00bb.`, b.id);
        }
      }
    }
  });
  if (nTitres === 0 && longSansTitre > 1200) add("avert", "Le texte est long et n\u2019a aucun intertitre : ajoutez-en pour faciliter la lecture.", null);
  const manque = (id, texte) => { const p = POLICES[id]; return p && p.manque ? [...new Set([...texte].filter(c => p.manque.includes(c)))] : []; };
  { const m = manque(S.policeTitre && POLICES[S.policeTitre] ? S.policeTitre : S.police, titreSimple()); if (m.length) add("avert", `Le titre contient \u00ab ${m.join(" ")} \u00bb, absent de la police choisie : ce caract\u00e8re s\u2019affichera dans une autre police.`, null); }
  S.blocs.forEach((b, i) => {
    const t = b.type === "intertitre" ? b.texte : b.type === "liste" ? b.items : (b.paras || []).map(texteDe).join(" ");
    const m = manque(policeBloc(b), t); if (m.length) add("avert", `Bloc ${i + 1} : \u00ab ${m.join(" ")} \u00bb est absent de la police choisie et s\u2019affichera dans une autre police.`, b.id);
  });
  alertesGeo.forEach(a => add("avert", a.msg, a.id));
  if (titreDebord) add("avert", "Le titre est trop long pour la zone pr\u00e9vue dans l\u2019en-t\u00eate, m\u00eame r\u00e9duit : raccourcissez-le.", null);
  else if (titreRatio < 0.8) add("avert", "Le titre a \u00e9t\u00e9 r\u00e9duit automatiquement pour tenir dans l\u2019en-t\u00eate. Un titre plus court sera plus lisible.", null);
  if (risquePageBlanche) add("avert", "Le texte se termine tout pr\u00e8s du bas de la derni\u00e8re page : le PDF risque de contenir une page blanche. Raccourcissez ou allongez de quelques lignes, puis v\u00e9rifiez le PDF.", null);
  if (pagesTract > 1 && piedSeul) add("avert", `Le texte tient sur une page, mais pas le pied de page : il passerait seul sur la page 2. Il manque environ ${Math.ceil(natMm - 295.5)} mm : raccourcissez le texte d\u2019environ ${Math.max(1, Math.ceil((natMm - 295.5) / 5.5))} ligne(s)${CHARTE.piedImage ? ", ou retirez l\u2019image de pied de page dans votre charte" : ""}.`, null);
  else if (pagesTract > 2) add("avert", `Le tract fait environ ${pagesTract} pages \u00e0 l\u2019impression. Un tract de une \u00e0 deux pages se lit mieux : pensez \u00e0 raccourcir le texte, puis v\u00e9rifiez le PDF.`, null);
  return pb;
}

/* =====================================================================
   Aper\u00e7u
   ===================================================================== */
function mesurer() {
  piedMm = PIED_MAX_MM; mesurerPasse();
  if (CHARTE.piedImage && natMm > 295.5) {
    const exces = natMm - 295.5 + 1.5, dispo = PIED_MAX_MM - PIED_MIN_MM;
    if (exces <= dispo) { piedMm = Math.round((PIED_MAX_MM - exces) * 10) / 10; mesurerPasse(); }
  }
}
function mesurerPasse() {
  const m = $("#mesure"); m.innerHTML = tractHtml();
  const nv = {}; let diff = false;
  $$("#mesure .rot").forEach(e => {
    const id = e.closest(".bl").dataset.b, r = +e.dataset.rot * Math.PI / 180, w = e.offsetWidth, h = e.offsetHeight;
    const mg = Math.max(0, Math.round((w * Math.abs(Math.sin(r)) + h * (Math.abs(Math.cos(r)) - 1)) / 2));
    nv[id] = mg; if ((margesRot[id] || 0) !== mg) diff = true;
  });
  if (diff || Object.keys(margesRot).some(id => !(id in nv))) { margesRot = nv; m.innerHTML = tractHtml(); }
  const t = $("#mesure .tract"), mmPx = 96 / 25.4;
  t.style.setProperty("--mmin", "0px");
  const hh = t.querySelector(".tr-head, .tr-masthead, .tete-compose").offsetHeight, hf = t.querySelector("footer").offsetHeight, hm = t.querySelector(".tr-main").offsetHeight;
  const nat = (hh + hm + hf) / mmPx; natMm = nat; piedSeul = nat > 295.5 && nat - hf / mmPx <= 295.5;                      // hauteur naturelle en mm (continue, sans coupure de page)
  // 8 mm perdus en bas de chaque page + 8 mm en haut de la suivante ; en pratique une dizaine de mm de plus (lignes, veuves/orphelines)
  const nMin = nat <= 295.5 ? 1 : Math.max(2, Math.ceil((nat - 16) / 281));   // jamais trop haut : sert \u00e0 caler le pied de page
  pagesTract = nat <= 295.5 ? 1 : Math.max(2, Math.ceil((nat - 24) / 273));    // estimation prudente affich\u00e9e \u00e0 l\u2019utilisateur
  mainMinPx = Math.max(0, Math.round((((nMin - 1) * 297 + 296) * mmPx) - hh - hf));
  risquePageBlanche = false;
  for (let k = 2; k <= 12; k++) { const cap = 297 * k - 16 * (k - 1); if (nat > cap - 14 && nat <= cap + 3) risquePageBlanche = true; }
  alertesGeo = [];
  const tr = t.getBoundingClientRect(), tol = 3;
  const items = S.blocs.map((b, i) => { const bl = $(`#mesure .bl[data-b="${b.id}"]`); if (!bl) return null; const r = bl.querySelector(".rot"); return { b, i, rects: [...bl.getClientRects()], rot: r ? [...r.getClientRects()] : null }; }).filter(Boolean);
  const inter = (a, c) => a.left < c.right - tol && a.right > c.left + tol && a.top < c.bottom - tol && a.bottom > c.top + tol;
  items.filter(x => x.rot).forEach(x => {
    const nom = `Bloc ${x.i + 1}`;
    if (x.rot.some(r => r.left < tr.left - tol || r.right > tr.right + tol || r.top < tr.top - tol)) alertesGeo.push({ id: x.b.id, msg: `${nom} : l\u2019image inclin\u00e9e d\u00e9passe de la page.` });
    const ov = items.find(y => y !== x && x.rot.some(r => y.rects.some(q => inter(r, q))));
    if (ov) alertesGeo.push({ id: x.b.id, msg: `${nom} : l\u2019image inclin\u00e9e risque de chevaucher le bloc ${ov.i + 1}. R\u00e9duisez l\u2019angle ou changez-la de place.` });
  });
  m.innerHTML = "";
}
function majApercu() {
  majResumes();
  mesurer();
  $("#apTract").innerHTML = tractHtml();
  const t = $("#apTract .tract"), wrap = $("#apWrap"), sc = $("#apScale");
  const dispo = $("#pTract").clientWidth - 28, w = t.offsetWidth || 794;
  const k = Math.min(1, Math.max(0.3, dispo / w));
  sc.style.transform = `scale(${k})`;
  wrap.style.width = Math.round(w * k) + "px";
  wrap.style.height = Math.round(t.offsetHeight * k) + "px";
  const pagePx = 297 / 25.4 * 96;
  $$(".coupure", wrap).forEach(x => x.remove());
  for (let p = 1; p < pagesTract; p++) { const c = el("div", "coupure"); c.style.top = Math.round(p * pagePx * k) + "px"; c.appendChild(el("span", "", "Fin de la page " + p)); wrap.appendChild(c); }
  if (!$("#pMail").hidden) rafraichirMail();
  const pb = verifier(), ne = pb.filter(p => p.niv === "erreur").length, na = pb.length - ne;
  const bd = $("#badge"); bd.textContent = pb.length; bd.className = "badge" + (ne ? " err" : na ? " warn" : "");
  const liste = $("#accListe"); liste.innerHTML = "";
  if (!pb.length) { liste.appendChild(el("p", "ok", "Aucun probl\u00e8me d\u00e9tect\u00e9. Ces contr\u00f4les sont automatiques et ne remplacent pas une relecture : le texte est-il clair, les couleurs et les images sont-elles utiles ?")); }
  else {
    const ul = el("ul", "pb");
    pb.forEach(p => {
      const li = el("li"); const niv = el("span", "niv" + (p.niv === "erreur" ? "" : " warn"), p.niv === "erreur" ? "Erreur" : "\u00c0 v\u00e9rifier");
      li.append(niv, el("span", "msg", p.msg));
      if (p.id) { const b = el("button", "btn", "Aller au bloc"); b.type = "button"; b.onclick = () => allerAuBloc(p.id); li.appendChild(b); }
      ul.appendChild(li);
    });
    liste.appendChild(ul);
  }
  sauvegardeAuto();
}
const majApercuD = debounce(majApercu, 120);
function changed() { majApercuD(); }

function montrerOnglet(id) {
  [["tabTract", "pTract"], ["tabMail", "pMail"], ["tabAcc", "pAcc"]].forEach(([t, p]) => {
    const on = t === id; $("#" + t).setAttribute("aria-selected", on); $("#" + p).hidden = !on;
  });
  if (id === "tabMail") rafraichirMail();
  if (id === "tabTract") majApercu();
}
$$(".tab").forEach(t => t.addEventListener("click", () => montrerOnglet(t.id)));

/* =====================================================================
   \u00c9diteur de blocs
   ===================================================================== */
const NOMS = { intertitre: "Intertitre", texte: "Texte", liste: "Liste", image: "Image", encadre: "Encadr\u00e9" };
let rangeSauvee = null, edSauve = null;

function bouton(txt, aria, f, cls) { const b = el("button", "btn" + (cls ? " " + cls : ""), txt); b.type = "button"; b.setAttribute("aria-label", aria); b.addEventListener("click", f); return b; }

function riche(b) {
  const w = el("div"), bar = el("div", "riche-bar"), ed = el("div", "riche-zone");
  const cmd = c => () => { ed.focus(); document.execCommand(c); ed.dispatchEvent(new Event("input")); };
  const bg = bouton("G", "Gras", cmd("bold")), bi = bouton("I", "Italique", cmd("italic"));
  bg.style.fontWeight = "bold"; bi.style.fontStyle = "italic";
  const bl = bouton("Lien", "Ajouter un lien au texte s\u00e9lectionn\u00e9", () => {
    const sel = getSelection();
    if (!sel.rangeCount || sel.isCollapsed || !ed.contains(sel.anchorNode)) { toast("S\u00e9lectionnez d\u2019abord, dans le texte, les mots qui serviront de lien."); return; }
    rangeSauvee = sel.getRangeAt(0).cloneRange(); edSauve = ed; $("#lienUrl").value = ""; $("#dlgLien").showModal(); $("#lienUrl").focus();
  });
  const bu = bouton("Retirer le lien", "Retirer le lien du texte s\u00e9lectionn\u00e9", cmd("unlink"));
  bar.append(bg, bi, bl, bu);
  if (b.type !== "encadre") {
    const sel = () => { const x = getSelection(); return x.rangeCount && !x.isCollapsed && ed.contains(x.anchorNode); };
    const bc = bouton("Couleur", "Mettre les mots s\u00e9lectionn\u00e9s en couleur (rouge de la charte)", () => {
      if (!sel()) { toast("S\u00e9lectionnez d\u2019abord, dans le texte, les mots \u00e0 mettre en couleur."); return; }
      ed.focus(); document.execCommand("foreColor", false, CONFIG.rouge); ed.dispatchEvent(new Event("input"));
    });
    bc.style.color = CONFIG.rouge; bc.style.fontWeight = "bold";
    const bn = bouton("Couleur normale", "Remettre les mots s\u00e9lectionn\u00e9s en couleur normale", () => {
      if (!sel()) { toast("S\u00e9lectionnez d\u2019abord, dans le texte, les mots \u00e0 remettre en couleur normale."); return; }
      ed.focus(); document.execCommand("foreColor", false, CONFIG.texte); ed.dispatchEvent(new Event("input"));
    });
    bar.append(bc, bn);
  }
  ed.contentEditable = "true"; ed.setAttribute("role", "textbox"); ed.setAttribute("aria-multiline", "true"); ed.setAttribute("aria-label", "Texte du bloc");
  ed.innerHTML = b.paras.length ? b.paras.map(p => `<p>${accHtml(p)}</p>`).join("") : "<p><br></p>";
  ed.addEventListener("input", () => { b.paras = extraireParas(ed); changed(); });
  ed.addEventListener("paste", e => {
    e.preventDefault();
    const t = (e.clipboardData.getData("text/plain") || "").replace(/\r/g, "");
    const lignes = t.split(/\n+/).filter(x => x.trim());
    if (lignes.length <= 1) document.execCommand("insertText", false, lignes[0] || "");
    else document.execCommand("insertHTML", false, lignes.map(l => "<p>" + esc(l) + "</p>").join(""));
  });
  w.append(bar, ed); return w;
}

function uiOrn(b, corps) {
  const bloc = el("div"); bloc.style.cssText = "margin-top:12px;padding-top:8px;border-top:1px dashed #c9c9c9";
  const tt = el("p", "", "D\u00e9coration de l\u2019intertitre (facultative)"); tt.style.cssText = "font-weight:bold;margin:0 0 6px"; bloc.appendChild(tt);
  if (!b.orn) {
    bloc.append(bouton("Choisir dans la banque\u2026", "Choisir une d\u00e9coration dans la banque pour cet intertitre", () => ouvrirBanque({ orn: b.id })),
      el("p", "aide", "Un pictogramme ou un num\u00e9ro plac\u00e9 \u00e0 gauche ou \u00e0 droite du titre."));
  } else {
    const o = b.orn, im = el("img", "vignette"); im.src = o.src; im.alt = ""; im.style.maxHeight = "60px"; bloc.appendChild(im);
    const sel = (id, lib, opts, val, f) => { const l = el("label", "", lib); l.htmlFor = id + b.id; const s2 = el("select"); s2.id = id + b.id; opts.forEach(([v, t]) => { const op = el("option", "", t); op.value = v; if (v === val) op.selected = true; s2.appendChild(op); }); s2.onchange = () => f(s2.value); bloc.append(l, s2); };
    sel("oc", "Position", [["gauche", "\u00c0 gauche du titre"], ["droite", "\u00c0 droite du titre"]], o.cote, v => { o.cote = v; changed(); });
    sel("ot", "Taille", [["petite", "M\u00eame hauteur que le titre"], ["moyenne", "Plus grande (\u00d71,5)"], ["grande", "Grande (\u00d72)"]], o.taille, v => { o.taille = v; changed(); });
    const d = el("label", "inline"), cd = el("input"); cd.type = "checkbox"; cd.checked = o.decoratif;
    const la = el("label", "", "Texte alternatif de la d\u00e9coration"); la.htmlFor = "oa" + b.id;
    const a = el("textarea"); a.id = "oa" + b.id; a.value = o.alt; a.disabled = o.decoratif; a.oninput = () => { o.alt = a.value; changed(); };
    cd.onchange = () => { o.decoratif = cd.checked; a.disabled = o.decoratif; changed(); };
    d.append(cd, document.createTextNode("D\u00e9corative (le titre se suffit \u00e0 lui-m\u00eame)"));
    const aide = el("p", "aide", "\u00c0 renseigner si la d\u00e9coration porte un sens (par exemple un num\u00e9ro d\u2019\u00e9tape) ; \u00e0 marquer d\u00e9corative si elle ne fait qu\u2019embellir.");
    const bar = el("div"); bar.style.cssText = "display:flex;gap:8px;margin-top:8px";
    bar.append(bouton("Changer", "Changer la d\u00e9coration", () => ouvrirBanque({ orn: b.id })), bouton("Retirer", "Retirer la d\u00e9coration", () => { b.orn = null; renderEditeur(b.id); majApercu(); }));
    bloc.append(la, a, aide, el("br"), d, bar);
  }
  corps.appendChild(bloc);
}
function carte(b, i) {
  const c = el("section", "carte"); c.dataset.id = b.id;
  c.setAttribute("aria-label", `Bloc ${i + 1} : ${NOMS[b.type]}`);
  if (b.id === blocActif) c.classList.add("actif");
  const tete = el("div", "carte-tete");
  const rep = el("button", "repli"); rep.type = "button";
  const chev = el("span", "chev"), nomSp = el("span", "nom", `${i + 1}. ${NOMS[b.type]}`), ext = el("span", "extrait");
  rep.append(chev, nomSp, ext); tete.appendChild(rep);
  const labRepli = `${NOMS[b.type].toLowerCase()} n\u00b0 ${i + 1}`;
  const majRepli = () => {
    const r = !!b._replie; c.classList.toggle("replie", r);
    rep.setAttribute("aria-expanded", String(!r)); rep.setAttribute("aria-label", `${r ? "D\u00e9plier" : "Replier"} le bloc ${labRepli}`);
    chev.textContent = r ? "\u25B8" : "\u25BE"; ext.textContent = r ? extraitBloc(b) : "";
    const co = c.querySelector(".carte-corps"); if (co) co.hidden = r;
  };
  rep.onclick = () => { b._replie = !b._replie; majRepli(); };
  c._majRepli = majRepli;
  const lab = `${NOMS[b.type].toLowerCase()} n\u00b0 ${i + 1}`;
  tete.append(
    bouton("\u2191", "Monter le bloc " + lab, () => deplacer(b.id, -1)),
    bouton("\u2193", "Descendre le bloc " + lab, () => deplacer(b.id, 1)),
    bouton("Dupliquer", "Dupliquer le bloc " + lab, () => dupliquer(b.id)),
    bouton("Supprimer", "Supprimer le bloc " + lab, () => supprimer(b.id)));
  const corps = el("div", "carte-corps");
  if (b.type === "intertitre") {
    const l1 = el("label", "", "Niveau"); l1.htmlFor = "n" + b.id;
    const s = el("select"); s.id = "n" + b.id;
    [[1, "Niveau 1 (H1) \u2013 d\u00e9conseill\u00e9, c\u2019est le niveau du titre du tract"], [2, "Niveau 2 (H2) \u2013 intertitre"], [3, "Niveau 3 (H3) \u2013 sous-intertitre"], [4, "Niveau 4 (H4)"], [5, "Niveau 5 (H5)"], [6, "Niveau 6 (H6)"]].forEach(([v, t]) => { const o = el("option", "", t); o.value = v; if (b.niveau === v) o.selected = true; s.appendChild(o); });
    s.onchange = () => { b.niveau = +s.value; changed(); };
    const l2 = el("label", "", "Texte de l\u2019intertitre"); l2.htmlFor = "t" + b.id;
    const t = el("input"); t.type = "text"; t.id = "t" + b.id; t.value = b.texte; t.oninput = () => { b.texte = t.value; changed(); };
    corps.append(l1, s, l2, t);
    uiOrn(b, corps);
  }
  if (b.type === "texte") corps.append(riche(b));
  if (b.type === "encadre") {
    const l = el("label", "", "Couleur de l\u2019encadr\u00e9"); l.htmlFor = "s" + b.id;
    const s = el("select"); s.id = "s" + b.id;
    [["jaune", "Jaune"], ["rouge", "Rouge (texte blanc)"], ["gris", "Gris clair"]].forEach(([v, t]) => { const o = el("option", "", t); o.value = v; if (b.style === v) o.selected = true; s.appendChild(o); });
    s.onchange = () => { b.style = s.value; changed(); };
    const sel2 = (id, lib, opts, val, f) => { const l2 = el("label", "", lib); l2.htmlFor = id + b.id; const s2 = el("select"); s2.id = id + b.id; opts.forEach(([v, t]) => { const o = el("option", "", t); o.value = v; if (String(v) === String(val)) o.selected = true; s2.appendChild(o); }); s2.onchange = () => f(s2.value); corps.append(l2, s2); };
    corps.append(l, s);
    sel2("nv", "Niveau du texte", [[0, "Paragraphe (texte normal)"], ...[1, 2, 3, 4, 5, 6].map(n => [n, `Titre de niveau ${n} (H${n})`])], b.niveau || 0, v => { b.niveau = +v; renderEditeur(b.id); majApercu(); });
    if (b.niveau) sel2("pt", "Appliquer le niveau \u00e0", [["premier", "Le premier paragraphe seulement"], ["tout", "Tous les paragraphes"]], b.portee, v => { b.portee = v; changed(); });
    sel2("al", "Alignement du texte", [["gauche", "Comme le reste du texte"], ["centre", "Centr\u00e9"]], b.align, v => { b.align = v; changed(); });
    corps.appendChild(riche(b));
  }
  if (b.type === "liste") {
    const l = el("label", "", "Type de liste"); l.htmlFor = "o" + b.id;
    const s = el("select"); s.id = "o" + b.id;
    [[false, "Puces"], [true, "Num\u00e9rot\u00e9e"]].forEach(([v, t]) => { const o = el("option", "", t); o.value = v; if (b.ordonnee === v) o.selected = true; s.appendChild(o); });
    s.onchange = () => { b.ordonnee = s.value === "true"; changed(); };
    const l2 = el("label", "", "\u00c9l\u00e9ments (un par ligne)"); l2.htmlFor = "i" + b.id;
    const ta = el("textarea"); ta.id = "i" + b.id; ta.value = b.items; ta.oninput = () => { b.items = ta.value; changed(); };
    corps.append(l, s, l2, ta);
  }
  if (b.type === "image") {
    if (b.src) { const im = el("img", "vignette"); im.src = b.src; im.alt = ""; corps.appendChild(im); }
    const lf = el("label", "", b.src ? "Changer l\u2019image" : "Choisir une image"); lf.htmlFor = "f" + b.id;
    const f = el("input"); f.type = "file"; f.id = "f" + b.id; f.accept = "image/*";
    f.onchange = () => { if (f.files[0]) chargerImage(b, f.files[0]); };
    const la = el("label", "", "Texte alternatif (d\u00e9crit l\u2019image \u00e0 celles et ceux qui ne la voient pas)"); la.htmlFor = "a" + b.id;
    const a = el("textarea"); a.id = "a" + b.id; a.value = b.alt; a.disabled = b.deco; a.oninput = () => { b.alt = a.value; changed(); };
    const aide = el("p", "aide", "D\u00e9crivez ce qu\u2019on voit et pourquoi l\u2019image est l\u00e0, comme au t\u00e9l\u00e9phone. Pas besoin d\u2019\u00e9crire \u00ab image de \u00bb.");
    const d = el("label", "inline"); const cd = el("input"); cd.type = "checkbox"; cd.checked = b.deco;
    cd.onchange = () => { b.deco = cd.checked; a.disabled = b.deco; changed(); };
    d.append(cd, document.createTextNode("Image d\u00e9corative (aucun texte alternatif)"));
    const lc = el("label", "", "Cr\u00e9dit / l\u00e9gende (facultatif)"); lc.htmlFor = "c" + b.id;
    const cr = el("input"); cr.type = "text"; cr.id = "c" + b.id; cr.value = b.credit; cr.oninput = () => { b.credit = cr.value; changed(); };
    const lt = el("label", "", "Taille de l\u2019image"); lt.htmlFor = "z" + b.id;
    const st = el("select"); st.id = "z" + b.id;
    [["huitieme", "Minuscule (\u215b de la largeur)"], ["sixieme", "Tr\u00e8s petite (\u2159)"], ["petite", "Petite (\u00bc)"], ["moyenne", "Moyenne (\u00bd)"], ["grande", "Grande (\u00be)"], ["pleine", "Pleine largeur"]].forEach(([v, t]) => { const o = el("option", "", t); o.value = v; if ((b.taille || "pleine") === v) o.selected = true; st.appendChild(o); });
    st.onchange = () => { b.taille = st.value; changed(); };
    const lk = el("label", "", "Texte \u00e0 c\u00f4t\u00e9 de l\u2019image"); lk.htmlFor = "k" + b.id;
    const sk = el("select"); sk.id = "k" + b.id;
    [["", "Aucun (image seule)"], ["gauche", "Image \u00e0 gauche, texte \u00e0 droite"], ["droite", "Image \u00e0 droite, texte \u00e0 gauche"]].forEach(([v, t]) => { const o = el("option", "", t); o.value = v; if ((b.cote || "") === v) o.selected = true; sk.appendChild(o); });
    sk.onchange = () => { b.cote = sk.value; renderEditeur(b.id); majApercu(); };
    corps.append(lf, f, lt, st, lk, sk);
    if (b.cote) {
      const ld = el("label", "", "Disposition du texte"); ld.htmlFor = "d" + b.id;
      const sd = el("select"); sd.id = "d" + b.id;
      [["cote", "C\u00f4te \u00e0 c\u00f4te (le texte reste dans sa colonne)"], ["habillage", "Le texte entoure l\u2019\u00e9l\u00e9ment (habillage)"]].forEach(([v, t]) => { const o = el("option", "", t); o.value = v; if ((b.dispo || "cote") === v) o.selected = true; sd.appendChild(o); });
      sd.onchange = () => { b.dispo = sd.value; changed(); };
      corps.append(ld, sd, el("p", "aide", "Dans le mail, le texte reste \u00e0 c\u00f4t\u00e9 de l\u2019image : les messageries g\u00e8rent mal l\u2019habillage."));
    }
    if (b.cote) { corps.appendChild(el("p", "aide", "Saisissez ci-dessous le texte qui accompagne l\u2019image. Dans le mail, l\u2019image et le texte restent c\u00f4te \u00e0 c\u00f4te.")); corps.appendChild(riche(b)); }
    if (b.banqueId) corps.appendChild(el("p", "aide", "\u00c9l\u00e9ment de la banque : le texte alternatif propos\u00e9 est modifiable."));
    corps.append(la, a, aide, d);
    corps.append(lc, cr);
  }
  if (b.type === "image") {
    const lr = el("label", "", "Inclinaison de l\u2019image, en degr\u00e9s (de \u2212180 \u00e0 180 ; 0 = droite)"); lr.htmlFor = "r" + b.id;
    const ir = el("input"); ir.type = "number"; ir.id = "r" + b.id; ir.min = -180; ir.max = 180; ir.step = "0.5"; ir.value = rotEff(b);
    ir.oninput = () => { const v = parseFloat(ir.value); b.rot = isNaN(v) ? 0 : v; changed(); };
    ir.onchange = () => { b.rot = rotEff(b); ir.value = b.rot; changed(); };
    const raz = bouton("Remettre droite", "Remettre l\u2019image droite", () => { b.rot = 0; ir.value = 0; changed(); });
    const rl = el("div"); rl.style.cssText = "display:flex;gap:8px;align-items:center;flex-wrap:wrap"; rl.append(ir, raz);
    corps.append(lr, rl, el("p", "aide", "Seule l\u2019image est inclin\u00e9e (dans le PDF et dans le mail) ; le texte qui l\u2019accompagne reste droit."));
  }
  if (["intertitre", "texte", "liste", "encadre"].includes(b.type) || (b.type === "image" && b.cote)) {
    const lp = el("label", "", "Police de ce bloc"); lp.htmlFor = "p" + b.id;
    const sp = el("select"); sp.id = "p" + b.id;
    const o0 = el("option", "", `Par d\u00e9faut du tract (${POLICES[policeBloc({})].nom.replace(/ \(.*/, "")})`); o0.value = ""; sp.appendChild(o0);
    Object.entries(POLICES).forEach(([k, p]) => { const o = el("option", "", p.nom); o.value = k; o.style.fontFamily = pile(k); if (b.police === k) o.selected = true; sp.appendChild(o); });
    sp.onchange = () => { b.police = sp.value; changed(); };
    corps.append(lp, sp);
  }
  if (S.modele === "blocus") {
    const p = el("label", "inline"); const cp = el("input"); cp.type = "checkbox"; cp.checked = !!b.pleine;
    cp.onchange = () => { b.pleine = cp.checked; changed(); };
    p.append(cp, document.createTextNode("Sur toute la largeur (les deux colonnes)")); corps.appendChild(p);
  }
  c.append(tete, corps);
  c.addEventListener("focusin", () => { if (blocActif !== b.id) { blocActif = b.id; $$(".carte").forEach(x => x.classList.toggle("actif", x.dataset.id === b.id)); } });
  majRepli();
  return c;
}

function extraitBloc(b) {
  let t = "";
  if (b.type === "intertitre") t = b.texte;
  else if (b.type === "texte" || b.type === "encadre") t = b.paras.length ? texteDe(b.paras[0]) : "";
  else if (b.type === "liste") t = b.items.split("\n")[0] || "";
  else if (b.type === "image") t = b.deco ? "(d\u00e9corative)" : (b.alt || (b.src ? "sans texte alternatif" : "aucune image"));
  t = t.trim();
  return t ? "\u2014 " + (t.length > 46 ? t.slice(0, 45) + "\u2026" : t) : "";
}
function repliTout(v) { S.blocs.forEach(b => { b._replie = v; }); $$(".carte").forEach(c => c._majRepli && c._majRepli()); }
function majResumes() {
  const set = (id, t) => { const e = $("#" + id); if (e) e.textContent = t; };
  const ch = $("#choixCharte"), t = titreSimple();
  set("etatCharte", `${ch && ch.selectedOptions[0] ? ch.selectedOptions[0].text : ""} \u00b7 ${MODELES[S.modele] ? MODELES[S.modele].nom : ""}`);
  set("etatTitre", t ? (t.length > 40 ? t.slice(0, 39) + "\u2026" : t) : "pas encore de titre");
  set("etatTypo", `${POLICES[S.police] ? POLICES[S.police].nom.replace(/ \(.*\)/, "") : ""} \u00b7 ${S.align === "gauche" ? "\u00e0 gauche" : "justifi\u00e9"}`);
  set("etatBlocs", `${S.blocs.length} bloc${S.blocs.length > 1 ? "s" : ""}`);
}
/* Boites repliables du panneau de gauche : etat memorise pour chaque compte */
function initBoites() {
  const cle = "tracteur-editeur-boites-" + CHARTE.compte; let etat = {};
  try { etat = JSON.parse(localStorage.getItem(cle) || "{}"); } catch (e) {}
  $$("details.boite[data-id]").forEach(d => {
    if (d.dataset.id in etat) d.open = !!etat[d.dataset.id];
    d.addEventListener("toggle", () => { etat[d.dataset.id] = d.open; try { localStorage.setItem(cle, JSON.stringify(etat)); } catch (e) {} });
  });
  const tout = o => $$("details.boite[data-id]").forEach(d => { d.open = o; });
  $("#bBoitesRepli").onclick = () => tout(false); $("#bBoitesDeplie").onclick = () => tout(true);
}
function renderEditeur(focusId) {
  const sc = $(".gauche-scroll"), y = sc.scrollTop, h = $("#blocs"); h.innerHTML = "";
  if (S.blocs.length > 1) { const bar = el("div", "outils-blocs"); bar.append(bouton("Tout replier", "Replier tous les blocs", () => repliTout(true)), bouton("Tout d\u00e9plier", "D\u00e9plier tous les blocs", () => repliTout(false))); h.appendChild(bar); }
  S.blocs.forEach((b, i) => h.appendChild(carte(b, i)));
  sc.scrollTop = y;
  if (focusId) allerAuBloc(focusId, true);
}
function allerAuBloc(id, sansAnim) {
  const c = $(`.carte[data-id="${id}"]`); if (!c) return;
  const blRepli = S.blocs[idx(id)]; if (blRepli && blRepli._replie) { blRepli._replie = false; if (c._majRepli) c._majRepli(); }
  c.scrollIntoView({ block: "center", behavior: sansAnim ? "auto" : "smooth" });
  const f = c.querySelector("input:not([type=file]),textarea,select,.riche-zone,input[type=file]"); if (f) f.focus({ preventScroll: true });
  blocActif = id; $$(".carte").forEach(x => x.classList.toggle("actif", x.dataset.id === id));
}
const idx = id => S.blocs.findIndex(b => b.id === id);
function insererBloc(nb) {
  const i = blocActif ? idx(blocActif) : -1;
  S.blocs.splice(i < 0 ? S.blocs.length : i + 1, 0, nb);
  blocActif = nb.id; renderEditeur(nb.id); majApercu();
}
function ajouter(type) { insererBloc(nouveauBloc(type)); }
function deplacer(id, d) { const i = idx(id), j = i + d; if (j < 0 || j >= S.blocs.length) return; [S.blocs[i], S.blocs[j]] = [S.blocs[j], S.blocs[i]]; renderEditeur(); majApercu(); const bt = $$(`.carte[data-id="${id}"] .carte-tete button`)[d < 0 ? 1 : 2]; if (bt) bt.focus(); }
function dupliquer(id) { const i = idx(id), c = JSON.parse(JSON.stringify(S.blocs[i], sansCache)); c.id = uid(); S.blocs.splice(i + 1, 0, c); renderEditeur(c.id); majApercu(); }
function supprimer(id) {
  const i = idx(id), b = S.blocs[i];
  const vide = (b.type === "texte" || b.type === "encadre") ? !b.paras.length : b.type === "intertitre" ? !b.texte.trim() : b.type === "liste" ? !b.items.trim() : !b.src;
  if (!vide && !confirm("Supprimer ce bloc ?")) return;
  S.blocs.splice(i, 1); blocActif = null; renderEditeur(); majApercu(); toast("Bloc supprim\u00e9.");
}

/* Images : redimensionnement et conversion */
function chargerImage(b, file) {
  if (!/^image\//.test(file.type)) { toast("Ce fichier n\u2019est pas une image."); return; }
  const fr = new FileReader();
  fr.onload = () => {
    const im = new Image();
    im.onload = () => {
      const MAX = 1600, k = Math.min(1, MAX / im.naturalWidth);
      const w = Math.round(im.naturalWidth * k), h = Math.round(im.naturalHeight * k);
      if (file.type === "image/png" && file.size < 600000 && k === 1) { b.src = fr.result; b.mime = "image/png"; }
      else {
        const cv = document.createElement("canvas"); cv.width = w; cv.height = h;
        const cx = cv.getContext("2d"); cx.fillStyle = "#fff"; cx.fillRect(0, 0, w, h); cx.drawImage(im, 0, 0, w, h);
        b.src = cv.toDataURL("image/jpeg", 0.86); b.mime = "image/jpeg";
      }
      b.w = w; b.h = h; renderEditeur(b.id); majApercu();
    };
    im.onerror = () => toast("Impossible de lire cette image."); im.src = fr.result;
  };
  fr.readAsDataURL(file);
}

/* Lien */
$("#dlgLien").addEventListener("close", e => {
  const dlg = e.target; if (dlg.returnValue !== "ok" || !rangeSauvee || !edSauve) { rangeSauvee = null; return; }
  let u = $("#lienUrl").value.trim(); if (!u) return;
  if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(u)) u = "mailto:" + u; else if (!/^(https?:\/\/|mailto:)/i.test(u)) u = "https://" + u;
  const sel = getSelection(); sel.removeAllRanges(); sel.addRange(rangeSauvee); edSauve.focus();
  document.execCommand("createLink", false, u); edSauve.dispatchEvent(new Event("input")); rangeSauvee = null;
});

/* =====================================================================
   Projet : enregistrement, ouverture, brouillon
   ===================================================================== */
function chargerEtat(o) {
  if (!o || !Array.isArray(o.blocs)) throw new Error("fichier invalide");
  const T = x => typeof x === "string" ? x : "";
  const blocs = o.blocs.map(b => {
    const n = nouveauBloc(NOMS[b.type] ? b.type : "texte");
    if (n.type === "intertitre") {
      n.niveau = Math.max(1, Math.min(6, parseInt(b.niveau) || 2)); n.texte = T(b.texte);
      const o = b.orn;
      if (o && /^data:image\/(png|jpeg);base64,[A-Za-z0-9+/=]+$/.test(o.src || "")) n.orn = {
        banqueId: T(o.banqueId), alt: T(o.alt), decoratif: !!o.decoratif, cote: o.cote === "droite" ? "droite" : "gauche",
        taille: ["petite", "moyenne", "grande"].includes(o.taille) ? o.taille : "moyenne", src: o.src,
        svg: /^data:image\/svg\+xml;base64,[A-Za-z0-9+/=]+$/.test(o.svg || "") ? o.svg : "",
        mime: o.src.startsWith("data:image/png") ? "image/png" : "image/jpeg", w: +o.w || 1, h: +o.h || 1 };
    }
    if (n.type === "texte" || n.type === "encadre") { n.paras = (Array.isArray(b.paras) ? b.paras : []).map(nettoyerHtml).filter(p => texteDe(p).trim()); if (n.type === "encadre") { n.style = ["jaune", "rouge", "gris"].includes(b.style) ? b.style : "jaune"; n.niveau = Math.max(0, Math.min(6, parseInt(b.niveau) || 0)); n.portee = b.portee === "tout" ? "tout" : "premier"; n.align = b.align === "centre" ? "centre" : "gauche"; } }
    if (n.type === "liste") { n.ordonnee = !!b.ordonnee; n.items = T(b.items); }
    if (n.type === "image") {
      if (/^data:image\/(png|jpeg);base64,[A-Za-z0-9+/=]+$/.test(b.src || "")) { n.src = b.src; n.mime = b.src.startsWith("data:image/png") ? "image/png" : "image/jpeg"; n.w = +b.w || 0; n.h = +b.h || 0; }
      if (n.src && /^data:image\/svg\+xml;base64,[A-Za-z0-9+/=]+$/.test(b.svg || "")) n.svg = b.svg;
      n.taille = TAILLES[b.taille] ? b.taille : "pleine"; n.banqueId = T(b.banqueId);
      n.alt = T(b.alt); n.deco = !!b.deco; n.credit = T(b.credit);
      n.cote = ["gauche", "droite"].includes(b.cote) ? b.cote : ""; n.dispo = b.dispo === "habillage" ? "habillage" : "cote"; n.paras = (Array.isArray(b.paras) ? b.paras : []).map(nettoyerHtml).filter(p => texteDe(p).trim());
    }
    n.pleine = !!b.pleine; n.police = POLICES[b.police] ? b.police : "";
    n.rot = n.type === "image" ? Math.round((parseFloat(b.rot) || 0) * 2) / 2 : 0; n.rot = rotEff(n);
    return n;
  });
  S = { version: 1, modele: MODELES[o.modele] ? o.modele : "greve", align: o.align === "gauche" ? "gauche" : "justifie", barre: typeof o.barre === "boolean" ? o.barre : o.modele === "greve", titreMaj: !!o.titreMaj, police: POLICES[o.police] ? o.police : POLICE_DEFAUT, policeTitre: POLICES[o.policeTitre] ? o.policeTitre : "", titre: T(o.titre), objet: T(o.objet), blocs };
  blocActif = null; synchroChamps(); renderEditeur(); majApercu();
}
function remplirPolices() {
  const sp = $("#police"), st = $("#policeTitre"); sp.innerHTML = ""; st.innerHTML = "";
  const o0 = el("option", "", "Comme le reste du tract"); o0.value = ""; st.appendChild(o0);
  Object.entries(POLICES).forEach(([k, p]) => { [sp, st].forEach(sel => { const o = el("option", "", p.nom); o.value = k; o.style.fontFamily = pile(k); sel.appendChild(o); }); });
}
function synchroChamps() { $("#police").value = POLICES[S.police] ? S.police : POLICE_DEFAUT; $("#policeTitre").value = S.policeTitre || ""; $("#titreMaj").checked = !!S.titreMaj; $("#barre").checked = !!S.barre; $("#align").value = S.align || "justifie"; $("#modele").value = S.modele; $("#titre").value = S.titre; $("#objet").value = S.objet; $("#aideModele").textContent = MODELES[S.modele].aide; }
function sauvegardeAuto() { sauvegardeAutoD(); }
const sauvegardeAutoD = debounce(() => {
  try { localStorage.setItem(CLE_AUTO, JSON.stringify(S, sansCache)); }
  catch (e) { if (!sauvegardeAutoD.avert) { sauvegardeAutoD.avert = true; toast("Brouillon automatique impossible (trop volumineux) : pensez \u00e0 \u00ab Enregistrer le projet \u00bb."); } }
}, 700);

/* =====================================================================
   Export
   ===================================================================== */
function rotaterImage(src, w, h, deg, mime) {
  return new Promise((ok, ko) => {
    const im = new Image();
    im.onload = () => {
      const t = deg * Math.PI / 180, c = Math.abs(Math.cos(t)), sn = Math.abs(Math.sin(t));
      const W = Math.ceil(w * c + h * sn), H = Math.ceil(w * sn + h * c), cv = document.createElement("canvas"); cv.width = W; cv.height = H;
      const cx = cv.getContext("2d"); if (mime === "image/jpeg") { cx.fillStyle = "#fff"; cx.fillRect(0, 0, W, H); }
      cx.translate(W / 2, H / 2); cx.rotate(t); cx.drawImage(im, -w / 2, -h / 2, w, h);
      ok({ src: cv.toDataURL(mime === "image/jpeg" ? "image/jpeg" : "image/png", 0.88), w: W, h: H, mime });
    };
    im.onerror = ko; im.src = src;
  });
}
async function preparerRotations() {
  for (const b of S.blocs) {
    if (b.type !== "image" || !b.src) continue;
    const r = rotEff(b);
    if (!r || !b.w || !b.h) { delete b._rot; continue; }
    const cle = r + "|" + b.src.length;
    if (b._rot && b._rot.cle === cle) continue;
    try { b._rot = Object.assign({ cle }, await rotaterImage(b.src, b.w, b.h, r, b.mime)); } catch (e) { delete b._rot; }
  }
}
function rafraichirMail() { preparerRotations().then(() => { $("#mailFrame").srcdoc = mailHtml(true).html; }); }
function policesUtilisees() {
  const ids = new Set([S.police, S.policeTitre, ...S.blocs.map(b => b.police), ...(CHARTE.piedCompose ? CHARTE.piedCompose.textes.map(t => t.police) : []), ...(CHARTE.enteteCompose ? CHARTE.enteteCompose.textes.map(t => t.police) : [])].filter(id => id && POLICES[id] && POLICES[id].f));
  return [...ids].map(id => POLICES[id].f);
}
async function pdfServeur() {
  const r = await fetch("api/pdf.php", {
    method: "POST", credentials: "same-origin",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": window.__CSRF__ },
    body: JSON.stringify({ titre: titreSimple() || "Tract", html: tractHtml(), familles: policesUtilisees(), charte: CHARTE.id, conforme: !verifier().some(p => p.niv === "erreur") })
  });
  if (!r.ok) { let m = "erreur " + r.status; try { m = (await r.json()).erreur || m; } catch (e) {} throw new Error(m); }
  return new Uint8Array(await r.arrayBuffer());
}
function octetsB64(u8) { let bin = ""; for (let i = 0; i < u8.length; i += 8192) bin += String.fromCharCode(...u8.subarray(i, i + 8192)); return btoa(bin); }
async function imprimer() {
  $("#printRoot").innerHTML = tractHtml();
  const ancien = document.title; document.title = nomFichier("").replace(/\.$/, "");
  const fin = () => { document.title = ancien; $("#printRoot").innerHTML = ""; window.removeEventListener("afterprint", fin); };
  window.addEventListener("afterprint", fin);
  try { await document.fonts.ready; } catch (e) {}
  await new Promise(r => requestAnimationFrame(() => r()));
  window.print();
}
async function exporter(mode) {
  try { await document.fonts.ready; } catch (e) {}
  majApercu();
  const pb = verifier().filter(p => p.niv === "erreur");
  if (CONFIG.bloquerSiErreurs && pb.length) { montrerOnglet("tabAcc"); toast(`Export bloqu\u00e9 : ${pb.length} erreur${pb.length > 1 ? "s" : ""} \u00e0 corriger.`); return; }
  await preparerRotations();
  let pdf = null;
  if (mode !== "eml") {
    const btns = ["#bPdf", "#bEml", "#bTout"].map(x => $(x)), occupe = on => btns.forEach(b => { b.disabled = on; b.setAttribute("aria-busy", on ? "true" : "false"); });
    montrerAttente(mode === "tout" ? "Cr\u00e9ation du PDF et du mail en cours : cela peut prendre quelques secondes. Ne fermez pas la page." : "Cr\u00e9ation du PDF en cours : cela peut prendre quelques secondes. Ne fermez pas la page.");
    occupe(true);
    try { pdf = await pdfServeur(); masquerAttente(); occupe(false); }
    catch (e) {
      masquerAttente(); occupe(false);
      const suite = mode === "tout" ? "Continuer avec le seul mail, sans PDF joint ?" : "Ouvrir la fen\u00eatre d\u2019impression \u00e0 la place ?";
      if (!confirm("Le PDF n\u2019a pas pu \u00eatre cr\u00e9\u00e9 par le serveur (" + e.message + ").\n\n" + suite)) return;
      if (mode === "pdf") { setTimeout(imprimer, 0); return; }
    }
  }
  if (pdf) telecharger(new Blob([pdf], { type: "application/pdf" }), nomFichier(".pdf"));
  if (mode !== "pdf") telechargerEml(pdf ? { nom: nomFichier(".pdf"), b64: octetsB64(pdf) } : null);
}

/* =====================================================================
   Banque d'\u00e9l\u00e9ments (pictogrammes, num\u00e9ros, s\u00e9parateurs\u2026)
   ===================================================================== */
let banqueChargee = null;
fetch("api/banque.php", { credentials: "same-origin" }).then(r => r.ok ? r.json() : null).then(j => { if (j && Array.isArray(j.elements)) window.BANQUE = j; }).catch(() => {});
const banqueCourante = () => banqueChargee || (window.BANQUE && Array.isArray(window.BANQUE.elements) ? window.BANQUE : BANQUE_VIDE);
const norm = s => String(s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
function validerBanque(o) {
  if (!o || !Array.isArray(o.elements)) throw new Error("banque invalide");
  const T = x => typeof x === "string" ? x : "";
  const els = o.elements.filter(e => /^data:image\/(png|jpeg|svg\+xml);base64,[A-Za-z0-9+/=]+$/.test(e.src || "") && +e.w > 0 && +e.h > 0).map(e => ({
    id: T(e.id) || uid(), nom: T(e.nom) || "Sans nom", categorie: T(e.categorie), tags: Array.isArray(e.tags) ? e.tags.map(T) : [],
    alt: T(e.alt), deco: !!e.deco, taille: TAILLES[e.taille] ? e.taille : "moyenne", w: +e.w, h: +e.h, src: e.src }));
  return { nom: T(o.nom) || "Banque", version: 1, elements: els };
}
function renderBanque() {
  const B = banqueCourante(), cat = $("#bqCat"), cur = cat.value;
  $("#bqNom").textContent = "\u2013 " + B.nom + (B === BANQUE_VIDE ? " (vide)" : "");
  const cats = [...new Set(B.elements.map(e => e.categorie).filter(Boolean))];
  cat.innerHTML = ""; const o0 = el("option", "", "Toutes les cat\u00e9gories"); o0.value = ""; cat.appendChild(o0);
  cats.forEach(c => { const o = el("option", "", c); o.value = c; cat.appendChild(o); }); cat.value = cats.includes(cur) ? cur : "";
  const q = norm($("#bqRecherche").value).trim(), g = $("#bqGrille"); g.innerHTML = "";
  const liste = B.elements.filter(e => (!cat.value || e.categorie === cat.value) && (!q || norm([e.nom, e.categorie, ...e.tags].join(" ")).includes(q)));
  liste.forEach(e => {
    const b = el("button", "tuile"); b.type = "button"; b.setAttribute("aria-label", "Ins\u00e9rer : " + e.nom);
    const im = el("img"); im.src = e.src; im.alt = ""; b.append(im, el("span", "", e.nom));
    b.onclick = () => insererDepuisBanque(e); g.appendChild(b);
  });
  if (!liste.length) g.appendChild(el("p", "aide", "Aucun \u00e9l\u00e9ment ne correspond."));
}
function svgVersPng(src, w, h) {
  return new Promise((ok, ko) => {
    const im = new Image();
    im.onload = () => { const cv = document.createElement("canvas"); cv.width = w; cv.height = h; cv.getContext("2d").drawImage(im, 0, 0, w, h); ok(cv.toDataURL("image/png")); };
    im.onerror = () => ko(new Error("svg illisible")); im.src = src;
  });
}
let banqueCible = null;
async function appliquerOrn(e) {
  const b = S.blocs.find(x => x.id === banqueCible.orn); if (!b) return;
  const o = { banqueId: e.id, alt: e.alt || "", decoratif: !!e.deco, cote: (b.orn && b.orn.cote) || "gauche", taille: (b.orn && b.orn.taille) || "moyenne", src: "", svg: "", mime: "", w: 0, h: 0 };
  try {
    if (e.src.startsWith("data:image/svg+xml")) { const W = 300, H = Math.max(1, Math.round(W * e.h / e.w)); o.svg = e.src; o.src = await svgVersPng(e.src, W, H); o.mime = "image/png"; o.w = W; o.h = H; }
    else { o.src = e.src; o.mime = e.src.startsWith("data:image/png") ? "image/png" : "image/jpeg"; o.w = e.w; o.h = e.h; }
  } catch (er) { toast("Cet \u00e9l\u00e9ment n\u2019a pas pu \u00eatre converti pour le mail."); return; }
  b.orn = o; banqueCible = null; $("#dlgBanque").close(); renderEditeur(b.id); majApercu();
  toast(o.decoratif ? "D\u00e9coration ajout\u00e9e (d\u00e9corative)." : "D\u00e9coration ajout\u00e9e : v\u00e9rifiez son texte alternatif.");
}
async function insererDepuisBanque(e) {
  if (banqueCible && banqueCible.orn) return appliquerOrn(e);
  const nb = nouveauBloc("image");
  nb.alt = e.alt || ""; nb.deco = !!e.deco; nb.taille = e.taille; nb.banqueId = e.id;
  try {
    if (e.src.startsWith("data:image/svg+xml")) {
      const W = 800, H = Math.max(1, Math.round(W * e.h / e.w));
      nb.svg = e.src; nb.src = await svgVersPng(e.src, W, H); nb.mime = "image/png"; nb.w = W; nb.h = H;
    } else { nb.src = e.src; nb.mime = e.src.startsWith("data:image/png") ? "image/png" : "image/jpeg"; nb.w = e.w; nb.h = e.h; }
  } catch (er) { toast("Cet \u00e9l\u00e9ment n\u2019a pas pu \u00eatre converti pour le mail."); return; }
  $("#dlgBanque").close(); insererBloc(nb);
  toast(nb.deco ? "\u00c9l\u00e9ment ins\u00e9r\u00e9 (d\u00e9coratif)." : "\u00c9l\u00e9ment ins\u00e9r\u00e9 : v\u00e9rifiez son texte alternatif.");
}
function ouvrirBanque(cible) { banqueCible = cible || null; $("#bqMode").hidden = !banqueCible; renderBanque(); $("#dlgBanque").showModal(); $("#bqRecherche").focus(); }
$("#bBanque").onclick = () => ouvrirBanque(null);
$("#dlgBanque").addEventListener("close", () => { setTimeout(() => { banqueCible = null; }, 0); });
$("#bqRecherche").oninput = renderBanque; $("#bqCat").onchange = renderBanque;
$("#bqCharger").onclick = () => $("#bqFichier").click();
$("#bqFichier").onchange = e => {
  const f = e.target.files[0]; if (!f) return; const fr = new FileReader();
  fr.onload = () => { try { const t = fr.result.replace(/^\uFEFF/, "").replace(/^\s*window\.BANQUE\s*=\s*/, "").replace(/;\s*$/, ""); banqueChargee = validerBanque(JSON.parse(t)); renderBanque(); toast(`Banque charg\u00e9e : ${banqueChargee.elements.length} \u00e9l\u00e9ments.`); } catch (er) { toast("Ce fichier n\u2019est pas une banque valide."); } };
  fr.readAsText(f); e.target.value = "";
};

/* =====================================================================
   D\u00e9marrage
   ===================================================================== */
remplirPolices();
Object.entries(MODELES).forEach(([k, m]) => { const o = el("option", "", m.nom); o.value = k; $("#modele").appendChild(o); });
initBoites();
$("#choixCharte").onchange = e => {
  try { localStorage.setItem(CLE_AUTO, JSON.stringify(S, sansCache)); } catch (er) {}
  location.href = "app.php?charte=" + encodeURIComponent(e.target.value);
};
$("#modele").onchange = e => { S.modele = e.target.value; $("#aideModele").textContent = MODELES[S.modele].aide; renderEditeur(); majApercu(); };
$("#titre").oninput = e => { S.titre = e.target.value; changed(); };
$("#objet").oninput = e => { S.objet = e.target.value; changed(); };
$("#align").onchange = e => { S.align = e.target.value; majApercu(); };
$("#police").onchange = e => { S.police = e.target.value; renderEditeur(); majApercu(); };
$("#policeTitre").onchange = e => { S.policeTitre = e.target.value; majApercu(); };
$("#titreMaj").onchange = e => { S.titreMaj = e.target.checked; majApercu(); };
$("#barre").onchange = e => { S.barre = e.target.checked; majApercu(); };
$$("[data-ajout]").forEach(b => b.onclick = () => ajouter(b.dataset.ajout));
$("#bNouveau").onclick = () => { if (!confirm("Commencer un nouveau tract ? Le contenu actuel sera effac\u00e9 (pensez \u00e0 l\u2019enregistrer avant).")) return; S = { version: 1, modele: S.modele, align: S.align || "justifie", barre: S.barre, titreMaj: false, police: S.police, policeTitre: S.policeTitre, titre: "", objet: "", blocs: [nouveauBloc("texte")] }; blocActif = null; synchroChamps(); renderEditeur(); majApercu(); };
$("#bOuvrir").onclick = () => $("#fProjet").click();
$("#fProjet").onchange = e => { const f = e.target.files[0]; if (!f) return; const fr = new FileReader(); fr.onload = () => { try { chargerEtat(JSON.parse(fr.result)); toast("Projet ouvert."); } catch (er) { toast("Ce fichier n\u2019est pas un projet valide."); } }; fr.readAsText(f); e.target.value = ""; };
$("#bSauver").onclick = () => telecharger(new Blob([JSON.stringify(S, sansCache)], { type: "application/json" }), nomFichier(".tract.json"));
$("#bPdf").onclick = () => exporter("pdf");
$("#bEml").onclick = () => exporter("eml");
$("#bTout").onclick = () => exporter("tout");
window.addEventListener("resize", debounce(majApercu, 150));
if (document.fonts && document.fonts.addEventListener) document.fonts.addEventListener("loadingdone", () => majApercuD());
try { document.execCommand("defaultParagraphSeparator", false, "p"); } catch (e) {}
try { const d = localStorage.getItem(CLE_AUTO); if (d) { chargerEtat(JSON.parse(d)); toast("Brouillon pr\u00e9c\u00e9dent restaur\u00e9."); } else throw 0; }
catch (e) { S = departTract(); synchroChamps(); renderEditeur(); majApercu(); }
