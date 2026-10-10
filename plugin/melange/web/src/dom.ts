// What the script takes from the page of settings.php: ids, classes, field names, data- attributes.

import type { Texts } from "./i18n";

export const ID = {
  init: "init",
  advanced: "adv",
  // The link of the contents of the page to Advanced (to a heading inside it).
  tocAdvanced: "tocadv",
  addServer: "addsrv",
  aioServer: "as",
  aioOwnUrl: "ao",
  aioOwnBox: "aobox",
  aioOwnCheck: "aob",
  aioOwnResult: "aor",
  template: "at",
  templateShown: "ats",
  templateHint: "ath",
  sourceMade: "smade",
  sourceOwn: "sown",
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
  jacredHeading: "jrh",
  jacredText: "jrp",
  jacred403: "jr403",
  jacredLan: "jrlan",
  jacredNote: "jrnote",
  jacredOwnUrl: "jou",
  jacredOwnKey: "jok",
  jacredCheck: "jcb",
  jacredResult: "jcr",
  jacredCompare: "jrsb",
  jacredTable: "jrsr",
  ts: "ts",
  tsCheck: "tsb",
  tsResult: "tsr",
  tsOwn: "tsown",
  saveResult: "sr",
  message: "msg",
} as const;

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
  // "Choose" in a row of the table of "Compare all" (made by the script).
  jacredChoose: "jrch",
  // A field or server block an error of the save is about (made by the script).
  bad: "bad",
} as const;

// The data- attributes the page has for the script.
export const DATA = {
  // An option of the template choice: its hint.
  hint: "data-hint",
  // The texts of #jrh, #jrp by the source.
  made: "data-made",
  own: "data-own",
  // An option of the server choice: "1" - TMDB of its own; "1" - JacRed answers it 403.
  tmdb: "data-tmdb",
  jr403: "data-jr403",
  // An eye: the id of its field.
  eyeFor: "data-for",
  // A built-in JacRed: its id.
  jacredId: "data-id",
} as const;

export const NAME = {
  source: "source",
  jacred: "jacred",
} as const;

// The values of the page the script compares with.
export const VALUE = {
  // The own one of a choice: of the source (a config of its own), the AIOStreams server, JacRed.
  own: "own",
  // The mark of the template choice: the template of the config not known yet.
  none: "none",
} as const;

// The radios of the choice of JacRed.
export const JACRED_RADIOS = "input[name=" + NAME.jacred + "]";

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

export function isOwnSource(form: HTMLFormElement): boolean {
  return checkedValue(form, NAME.source) === VALUE.own;
}

// A disabled option of the template choice is a mark (the template of the config, as "none"), not a template to
// choose: never posted, never kept as the one for a new config.
export function isMark(option: HTMLOptionElement | undefined): boolean {
  return !!option && option.disabled;
}
