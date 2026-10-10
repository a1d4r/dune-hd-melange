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
  // The heads of the table of "Compare all"; releases also in the line of "Check".
  "colName",
  "colAvailable",
  "colTime",
  "releases",
  // A button in a row of "Compare all": checks the radio of that JacRed.
  "choose",
  "yes",
  "no",
  "seconds",
  // "Check" or a row of "Compare all" of JacRed when a save stopped the probes (a 403: linkExpired).
  "stopped",
] as const;

export type Texts = Record<(typeof TEXT_KEYS)[number], string>;

// Why a request gave no answer to show.
export function failureText(texts: Texts, failure: Failure): string {
  if (failure.kind === "net") return texts.noConnection;
  if (failure.kind === "timeout") return texts.timeout.replace("%d", String(failure.seconds));
  if (failure.status === 403) return texts.linkExpired;
  return texts.httpError.replace("%d", String(failure.status));
}
