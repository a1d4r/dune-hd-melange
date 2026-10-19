// The texts of the script: #init.i18n, written by aio_cgi_js() in the language of the page.

import type { Failure } from "./api";

// The keys of #init.i18n: the one list (Texts, the contract test).
export const TEXT_KEYS = [
  "duplicate",
  // The title of a disabled "Duplicate": every server block is in use.
  "serversFull",
  "copySuffix",
  // "Server": the name of a block without one is "Server <n>".
  "server",
  "checking",
  "saving",
  "save",
  "saved",
  // Instead of "saved" when the config after the save failed: only the settings on the Dune are saved.
  "savedOnDune",
  "saveAndCreate",
  "creating",
  "updating",
  "resetting",
  // After "Saved": aio_sync timed out or no connection, by the save's aio (create, update, reset).
  "notCreated",
  "notUpdated",
  "notReset",
  // %s: the host of the server, %s: the name of the template.
  "resetConfirm",
  // The name of the mode P2P on the main screen.
  "modeP2p",
  "passCopied",
  "passNotCopied",
  // No answer at all: no connection; %d: the seconds waited.
  "noConnection",
  "timeout",
  "linkExpired",
  // A field of a check has a character that can't be sent (a lone half of a surrogate pair).
  "badChars",
  // %d: the HTTP status.
  "httpError",
  // The format error of own JacRed (jr_speed sends it with bad: "format").
  "jacredFormat",
  "ownJacred",
  // In the line of "Check" of own JacRed.
  "releases",
  // A JacRed of "Compare speed" that answered none of the probes.
  "noAnswer",
  "yes",
  "no",
  "seconds",
  // "Check" or a card of "Compare speed" of JacRed when a save stopped the probes (a 403: linkExpired).
  "stopped",
  // The values of the lines of the main screen (as aio_cgi_rows).
  "watchOwn",
  "needKey",
  "needLink",
  "viaTs",
  "tsDune",
  "tsSpare",
  "noServers",
  // The line of the AIOStreams server: own one without the host of an address.
  "ownAioServer",
  // The count of the changes by the bar of the save, %d: the count; the forms of plural() (English: one, many).
  "changeOne",
  "changeFew",
  "changeMany",
] as const;

export type Texts = Record<(typeof TEXT_KEYS)[number], string>;

// Why a request gave no answer to show.
export function failureText(texts: Texts, failure: Failure): string {
  if (failure.kind === "net") return texts.noConnection;
  if (failure.kind === "timeout") return texts.timeout.replace("%d", String(failure.seconds));
  if (failure.status === 403) return texts.linkExpired;
  return texts.httpError.replace("%d", String(failure.status));
}

// The form of the count n of the changes: Russian has three (1, 2-4, 5-20 and 11-14), English two.
export function plural(texts: Texts, lang: string, n: number): string {
  const ten = n % 10,
    hundred = n % 100;
  let key: "changeOne" | "changeFew" | "changeMany" = "changeMany";
  if (lang !== "ru") key = n === 1 ? "changeOne" : "changeMany";
  else if (ten === 1 && hundred !== 11) key = "changeOne";
  else if (ten >= 2 && ten <= 4 && (hundred < 12 || hundred > 14)) key = "changeFew";
  return texts[key].replace("%d", String(n));
}
