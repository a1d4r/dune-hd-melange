// What the script takes from the page of settings.php: ids, classes, field names, data- attributes.

import type { Texts } from "./i18n";

export const ID = {
  init: "init",
  addServer: "addsrv",
  aioOwnUrl: "ao",
  aioOwnCheck: "aob",
  aioOwnResult: "aor",
  // The template choice (its radios: NAME.template): the one shown (posted as seen), the lines of its marks.
  templateShown: "ats",
  templateMarks: "ath",
  // The same two of "Torrents" (0.44.0: a template choice of each mode).
  templateP2pShown: "atps",
  templateP2pMarks: "atph",
  // Debrid: the switch of the service (both keys empty), the box of each key and the link opening the other one.
  debridSwitch: "dseg",
  rdBox: "rdbox",
  tbBox: "tbbox",
  rdAdd: "rdadd",
  tbAdd: "tbadd",
  // "Main Debrid" (0.56.0): shown with both keys.
  debridMain: "dmain",
  tmdbBox: "tmbox",
  tmdbNote: "tmnote",
  confNone: "anone",
  confHave: "ahave",
  confLink: "alink",
  confLogin: "al",
  confPass: "ap",
  copyPass: "apc",
  copyResult: "apr",
  resetConf: "arb",
  rdKey: "rd",
  rdCheck: "rdb",
  rdResult: "rdr",
  tbKey: "tb",
  tbCheck: "tbb",
  tbResult: "tbr",
  tmdbKey: "tm",
  tmdbCheck: "tmb",
  tmdbResult: "tmr",
  manifest: "m",
  manifestCheck: "mb",
  manifestResult: "mr",
  jacredText: "jrp",
  jacred403: "jr403",
  jacredLan: "jrlan",
  jacredNote: "jrnote",
  jacredOwnUrl: "jou",
  jacredOwnKey: "jok",
  jacredCheck: "jcb",
  jacredResult: "jcr",
  jacredCompare: "jrsb",
  // The errors of "Compare speed" (its results go into the rows).
  jacredSpeed: "jrsr",
  ts: "ts",
  // Login and password of TorrServer (0.45.0).
  tsUser: "tsu",
  tsPass: "tsp",
  tsCheck: "tsb",
  tsResult: "tsr",
  // The main screen: the question of the first run (none after it), the state (hidden on the first run).
  first: "first",
  state: "state",
  // The save: the bar under the screens (its results, the button hiding them, the count of the changes).
  bar: "bar",
  dirty: "dirty",
  saveResult: "sr",
  hideResult: "srx",
  message: "msg",
  // The rows of the catalog: the choice as posted, the lines of the list's state, "Try again", the box of the list
  // (hidden till it comes), the switch of its parts (buttons by DATA.key: movie, series); of movies and of series:
  // the part, the rows shown in their order, all rows by groups. rowsWas: the choice as the page came (or the last save
  // that stayed on it); posted back the same, the save keeps the stored one (the dialog of the rows on the Dune).
  rowsValue: "crv",
  rowsWas: "crw",
  rowsStatus: "crs",
  rowsRetry: "crr",
  rowsBox: "crb",
  rowsSwitch: "crt",
  rowsMoviePart: "crpm",
  rowsSeriesPart: "crps",
  rowsMovies: "crom",
  rowsMovieGroups: "crgm",
  rowsSeries: "cros",
  rowsSeriesGroups: "crgs",
  // The backup (#bak): "Save to a file" (a link, hidden on the first run), the line instead of it there, the box of
  // "Load from a file" (hidden without the script), its button, the file input (data-max: the longest body of the import),
  // results.
  backupExport: "bkx",
  backupNone: "bkn",
  backupImport: "bki",
  backupButton: "bkb",
  backupFile: "bkf",
  backupResult: "bkr",
  // The source as the page came (or the last save that stayed on it: "was" of its answer): posted back the same, the
  // save keeps the stored one (MENU of a list on the Dune may have switched the profile since).
  sourceWas: "sw",
} as const;

// The editor of the source profiles (0.58.0, profiles.ts) at the top of #src: on a page PHP made with "Source profiles"
// on only. The block, the list of the profiles (by the script), the name of the profile (posted), the box of "Make it
// current" and "Delete", those two, their results; the profile of the save as posted: its place (an index, new, cur),
// its name as the page came, 1 - in use then.
export const EDITOR = {
  block: "pe",
  list: "pel",
  name: "pfn",
  buttons: "peb",
  use: "peu",
  del: "pex",
  result: "per",
  sel: "pfs",
  was: "pfw",
  now: "pfc",
} as const;

// The screens with a line on the main screen: #r<id>, its dot #d<id>, its value #v<id>; in the order of the page.
export const ROWS = ["src", "cfg", "voices", "torr", "rows", "look", "dl", "adv", "bak", "log"] as const;
export type Row = (typeof ROWS)[number];

export const CLS = {
  save: "save",
  eye: "eye",
  secret: "sec",
  on: "on",
  check: "chk",
  serverBlock: "srv",
  serverCheck: "srvchk",
  serverDup: "srvdup",
  serverResult: "srvr",
  jacredBuiltin: "jrb",
  // The time and the releases of a JacRed of "Compare speed" in its card (data-jr: its id or "own").
  jacredSpeed: "ms",
  // The fields of the own one of a choice (the last card): shown by the CSS while it is chosen, or with ownOpen (an
  // error of a save on one of them: PHP or the script).
  ownFields: "ownf",
  ownOpen: "open",
  // The title of a card (label.opt).
  cardTitle: "t",
  // A screen of the page.
  screen: "scr",
  // A field or server block an error of the save is about (made by the script).
  bad: "bad",
  // A mark after a title ("of Melange"; "now", "no config" of a source profile).
  badge: "badge",
  // A line under a title (of a card, a profile), the warning one (also of a mark).
  value: "v",
  warn: "wn",
  // A row of a list (of the main screen; a source profile, made by the script), its text, its chevron.
  row: "row",
  rowText: "rt",
  chevron: "chev",
  // A box of results (aria-live).
  result: "res",
} as const;

// The data- attributes the page has for the script.
export const DATA = {
  // The texts of #jrp by the answer: a config made by melange or own one.
  made: "data-made",
  own: "data-own",
  // A radio of the server choice: "1" - TMDB of its own; the id of the JacRed that answers it 403;
  // "1" - in P2P the server asks JacRed itself (Torznab), not Magnetio.
  tmdb: "data-tmdb",
  jr403: "data-jr403",
  p2pTorznab: "data-p2ptz",
  // An eye: the id of its field.
  eyeFor: "data-for",
  // A built-in JacRed: its id.
  jacredId: "data-id",
  // The card of a JacRed in "Compare speed": its id, "own".
  jacredOf: "data-jr",
  // A radio of the template choice or a line of its mark: its name on the main screen.
  short: "data-short",
  // A line of a mark of the template choice (the template of the config not a template to choose): its value.
  mark: "data-mark",
  // A button of #dseg: rd, tb; of #crt: movie, series.
  key: "data-k",
  // The file input of the backup: the longest body of the import (post_max_size of the page).
  max: "data-max",
  // The state of #state and its lines: key, conf, man, ok.
  state: "data-st",
  // Of a field of a server block by its client (s<n>_type: qbt, tr): of a label - its text, of an input - its
  // placeholder.
  qbt: "data-qbt",
  tr: "data-tr",
} as const;

export const NAME = {
  // "Where to get films": VALUE.debrid, VALUE.p2p (the configs melange makes, by their mode) or VALUE.own.
  watch: "watch",
  // The own server of AIOStreams (its address), the link of the own config.
  aioOwnUrl: "aio_own_url",
  manifest: "manifest",
  // "Source profiles" (0.58.0): VALUE.on, VALUE.off.
  srcProfiles: "src_profiles",
  // The AIOStreams server: a URL of the list or VALUE.own (own one, its address in #ao).
  aioServer: "aio_server",
  jacred: "jacred",
  // The template of the configs of each mode (no radio checked: a mark shown, nothing posted).
  template: "aio_tpl",
  templateP2p: "aio_tpl_p2p",
  // "Film logo in place of the title" (0.55.0): VALUE.on, VALUE.off.
  catLogo: "cat_logo",
  // "IMDb rating on posters": VALUE.on, VALUE.off.
  catRating: "cat_rating",
} as const;

// The values of the page the script compares with.
export const VALUE = {
  // The own one of a choice: the answer (a config of its own), the AIOStreams server, JacRed.
  own: "own",
  // The mark of the template choice: the template of the config not known yet.
  none: "none",
  // The modes (the keys of #init.confs and #init.tplNew).
  debrid: "debrid",
  p2p: "p2p",
  // The answers of the radios on and off.
  on: "on",
  off: "off",
} as const;

// The radios of the choice of JacRed.
export const JACRED_RADIOS = "input[name=" + NAME.jacred + "]";
// The radios of the choice of the AIOStreams server (screen #cfg).
export const AIO_SERVER_RADIOS = "input[name=" + NAME.aioServer + "]";

// The cards of a group of radios (label.opt[for] of each): an error of their name= marks it.
export const RADIO_GROUP = ".choice";

// A block of a server (qBittorrent WebAPI or Transmission).
export const SERVER_BLOCK = "details." + CLS.serverBlock;
// s<n>_<key>: a field of server block n.
export const SERVER_FIELD = /^s([1-9])_([a-z]+)$/;
// The fields of a block posted by its "Check".
export const SERVER_CHECK_FIELD = /^s[1-9]_(type|url|user|pass|category|tags)$/;

// The page of the script: its document, the form and the texts.
export interface Page {
  doc: Document;
  form: HTMLFormElement;
  texts: Texts;
}

export function byId<E extends HTMLElement = HTMLElement>(doc: Document, id: string): E {
  const el = doc.getElementById(id);
  if (!el) throw new Error("settings: no #" + id);
  return el as E;
}

export function query<E extends Element = HTMLElement>(root: ParentNode, selector: string): E {
  const el = root.querySelector<E>(selector);
  if (!el) throw new Error("settings: no " + selector);
  return el;
}

export function checkedRadio(form: HTMLFormElement, name: string): HTMLInputElement | null {
  return form.querySelector<HTMLInputElement>("input[name=" + name + "]:checked");
}

export function checkedValue(form: HTMLFormElement, name: string): string | null {
  const el = checkedRadio(form, name);
  return el ? el.value : null;
}

// The answer of "Where to get films" (none checked: Debrid, as PHP shows it).
export function watchOf(form: HTMLFormElement): string {
  return checkedValue(form, NAME.watch) || VALUE.debrid;
}

// A value without the spaces around it, as PHP trims it (AIO_TRIM).
export const trimmed = (value: string): string => value.replace(/^[ \t\r\n\x0B]+|[ \t\r\n\x0B]+$/g, "");

// An address typed as [http://]login:password@host[:port] -> [the address without them, login, password] as typed
// (no ":" - an empty password); no "@", no login before it or a "/", "?", "#" there -> [s, null, null] (a bad
// address). As aio_ts_userinfo (common.php): the last "@" ends the password.
export function tsUserinfo(s: string): [string, string | null, string | null] {
  const at = s.lastIndexOf("@"),
    pre = /^http:\/\//i.test(s) ? 7 : 0;
  const ui = s.slice(pre, at);
  const c = ui.indexOf(":"),
    user = c < 0 ? ui : ui.slice(0, c);
  if (at <= pre || /[\/?#]/.test(ui) || trimmed(user) === "") return [s, null, null];
  return [s.slice(0, pre) + s.slice(at + 1), user, c < 0 ? "" : ui.slice(c + 1)];
}

// The host of an address as parse_url() of PHP finds it ("" - none).
export function hostOf(url: string): string {
  const found = /^[a-z][a-z0-9+.-]*:\/\/(?:[^\/?#@]*@)?(\[[^\]\/]*\]|[^\/:?#]*)/i.exec(url);
  return found ? found[1]! : "";
}
