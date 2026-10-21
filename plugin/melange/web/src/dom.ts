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
} as const;

// The screens with a line on the main screen: #r<id>, its dot #d<id>, its value #v<id>; in the order of the page.
export const ROWS = ["src", "cfg", "voices", "torr", "dl", "log"] as const;
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
  // A button of #dseg: rd, tb.
  key: "data-k",
  // The state of #state and its lines: key, conf, man, ok.
  state: "data-st",
} as const;

export const NAME = {
  // "Where to get films": VALUE.debrid, VALUE.p2p (the configs melange makes, by their mode) or VALUE.own.
  watch: "watch",
  // The AIOStreams server: a URL of the list or VALUE.own (own one, its address in #ao).
  aioServer: "aio_server",
  jacred: "jacred",
  // The template of the configs of each mode (no radio checked: a mark shown, nothing posted).
  template: "aio_tpl",
  templateP2p: "aio_tpl_p2p",
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
} as const;

// The radios of the choice of JacRed.
export const JACRED_RADIOS = "input[name=" + NAME.jacred + "]";
// The radios of the choice of the AIOStreams server (screen #cfg).
export const AIO_SERVER_RADIOS = "input[name=" + NAME.aioServer + "]";

// The cards of a group of radios (label.opt[for] of each): an error of their name= marks it.
export const RADIO_GROUP = ".choice";

// A block of a qBittorrent server.
export const SERVER_BLOCK = "details." + CLS.serverBlock;
// s<n>_<key>: a field of server block n.
export const SERVER_FIELD = /^s([1-9])_([a-z]+)$/;
// The fields of a block posted by its "Check".
export const SERVER_CHECK_FIELD = /^s[1-9]_(url|user|pass|category|tags)$/;

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

export function checkedValue(form: HTMLFormElement, name: string): string | null {
  const el = form.querySelector<HTMLInputElement>("input[name=" + name + "]:checked");
  return el ? el.value : null;
}

// The answer of "Where to get films" (none checked: Debrid, as PHP shows it).
export function watchOf(form: HTMLFormElement): string {
  return checkedValue(form, NAME.watch) || VALUE.debrid;
}

// A value without the spaces around it, as PHP trims it (AIO_TRIM).
export const trimmed = (value: string): string => value.replace(/^[ \t\r\n\x0B]+|[ \t\r\n\x0B]+$/g, "");

// An address typed as [http://]login:password@host[:port] -> [the address without them, login, password] as typed
// (no ":" - an empty password); no "@", nothing before it or a "/" there -> [s, null, null]. As aio_ts_userinfo
// (common.php): the last "@" ends the password.
export function tsUserinfo(s: string): [string, string | null, string | null] {
  const at = s.lastIndexOf("@"),
    pre = /^http:\/\//i.test(s) ? 7 : 0;
  const ui = s.slice(pre, at);
  if (at <= pre || ui.indexOf("/") >= 0) return [s, null, null];
  const c = ui.indexOf(":");
  return [s.slice(0, pre) + s.slice(at + 1), c < 0 ? ui : ui.slice(0, c), c < 0 ? "" : ui.slice(c + 1)];
}

// The host of an address as parse_url() of PHP finds it ("" - none).
export function hostOf(url: string): string {
  const found = /^[a-z][a-z0-9+.-]*:\/\/(?:[^\/?#@]*@)?(\[[^\]\/]*\]|[^\/:?#]*)/i.exec(url);
  return found ? found[1]! : "";
}
