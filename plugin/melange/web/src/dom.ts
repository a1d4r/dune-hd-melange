// What the script takes from the page of settings.php: ids, classes, field names, data- attributes.

import type { Texts } from "./i18n";

export const ID = {
  init: "init",
  addServer: "addsrv",
  aioServer: "as",
  // The name of the chosen server in the summary of details#asd.
  aioServerName: "asn",
  aioOwnUrl: "ao",
  aioOwnBox: "aobox",
  aioOwnCheck: "aob",
  aioOwnResult: "aor",
  template: "at",
  templateShown: "ats",
  templateHint: "ath",
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
  tsCheck: "tsb",
  tsResult: "tsr",
  // The box of the TorrServer field: on #torr, moved by the script into #tsp2p of the answer "Torrents"; then
  // #tsmoved on #torr points there and #tslink (the way without the script) goes.
  tsBox: "tsbox",
  tsP2p: "tsp2p",
  tsLink: "tslink",
  tsMoved: "tsmoved",
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

// The screens with a line on the main screen: #r<id>, its dot #d<id>, its value #v<id>.
export const ROWS = ["src", "voices", "torr", "dl", "cfg", "log"] as const;
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
  // A screen of the page.
  screen: "scr",
  // A field or server block an error of the save is about (made by the script).
  bad: "bad",
} as const;

// The data- attributes the page has for the script.
export const DATA = {
  // An option of the template choice: its hint.
  hint: "data-hint",
  // The texts of #jrp by the answer: a config made by melange or own one.
  made: "data-made",
  own: "data-own",
  // An option of the server choice: "1" - TMDB of its own; the id of the JacRed that answers it 403;
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
  // An option of the template choice: its name on the main screen.
  short: "data-short",
  // A button of #dseg: rd, tb.
  key: "data-k",
  // The state of #state and its lines: key, conf, man, ok.
  state: "data-st",
} as const;

export const NAME = {
  // "Where to get films": VALUE.debrid, VALUE.p2p (the configs melange makes, by their mode) or VALUE.own.
  watch: "watch",
  jacred: "jacred",
} as const;

// The values of the page the script compares with.
export const VALUE = {
  // The own one of a choice: the answer (a config of its own), the AIOStreams server, JacRed.
  own: "own",
  // The mark of the template choice: the template of the config not known yet.
  none: "none",
  // The modes; P2P is also the template of a config of that mode.
  debrid: "debrid",
  p2p: "p2p",
} as const;

// The radios of the choice of JacRed.
export const JACRED_RADIOS = "input[name=" + NAME.jacred + "]";

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

// The host of an address as parse_url() of PHP finds it ("" - none).
export function hostOf(url: string): string {
  const found = /^[a-z][a-z0-9+.-]*:\/\/(?:[^\/?#@]*@)?(\[[^\]\/]*\]|[^\/:?#]*)/i.exec(url);
  return found ? found[1]! : "";
}

// A disabled option of the template choice is a mark (the template of the config, as "none"), not a template to
// choose: never posted, never kept as the one for a new config.
export function isMark(option: HTMLOptionElement | undefined): boolean {
  return !!option && option.disabled;
}
