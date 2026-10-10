// The "Check" buttons of keys, TorrServer, the manifest and own AIOStreams.

import type { Api, Check, Result } from "./api";
import { byId, ID, type Page } from "./dom";
import { failureText, type Texts } from "./i18n";
import type { Lock } from "./lock";
import { encodedOr, type LineClass, show } from "./ui";

// A result to show: the class of its lines and the lines.
export type Shown = [cls: LineClass, lines: string[]];

// While the request goes, the button is disabled and the result says "Checking…". run() builds the body first: a
// field it can't encode (a lone surrogate) throws URIError at once, then nothing is sent or locked.
export function bindCheck<T>(
  lock: Lock,
  texts: Texts,
  button: HTMLButtonElement,
  out: () => HTMLElement,
  run: () => Promise<Result<T>>,
  render: (data: T) => Shown,
): void {
  lock.hold(button);
  button.addEventListener("click", () => {
    if (!lock.free(button)) return;
    const pending = encodedOr(out(), texts.badChars, run);
    if (!pending) return;
    lock.run(button, true);
    show(out(), "hint", [texts.checking]);
    pending.then((result) => {
      lock.run(button, false);
      const [cls, lines]: Shown = result.kind === "data" ? render(result.data) : ["err", [failureText(texts, result)]];
      show(out(), cls, lines);
    });
  });
}

function okOrErr(check: Check): Shown {
  return [check.ok ? "ok" : "err", [check.msg]];
}

function byLevel(check: Check): Shown {
  return [check.level === "warn" ? "warn" : check.ok ? "ok" : "err", [check.msg]];
}

export function setupChecks(page: Page, api: Api, lock: Lock): void {
  const doc = page.doc;
  const value = (id: string) => byId<HTMLInputElement>(doc, id).value;
  function bind(buttonId: string, outId: string, run: () => Promise<Result<Check>>, render: (check: Check) => Shown): void {
    bindCheck(lock, page.texts, byId<HTMLButtonElement>(doc, buttonId), () => byId(doc, outId), run, render);
  }
  bind(ID.tsCheck, ID.tsResult, () => api.tsCheck(value(ID.ts)), okOrErr);
  bind(ID.manifestCheck, ID.manifestResult, () => api.aioCheck(value(ID.manifest)), byLevel);
  bind(ID.aioOwnCheck, ID.aioOwnResult, () => api.aioStatus(value(ID.aioOwnUrl)), byLevel);
  bind(ID.rdCheck, ID.rdResult, () => api.keyCheck("rd", value(ID.rdKey)), byLevel);
  bind(ID.tbCheck, ID.tbResult, () => api.keyCheck("tb", value(ID.tbKey)), byLevel);
  bind(ID.tmdbCheck, ID.tmdbResult, () => api.keyCheck("tmdb", value(ID.tmdbKey)), byLevel);
}
