// JacRed: the note under a non-default choice, "Check" and "Compare all" (3 probes each, 2 s apart).

import type { Api, JrSpeed } from "./api";
import { byId, checkedValue, CLS, DATA, ID, JACRED_RADIOS, NAME, type Page, VALUE } from "./dom";
import { failureText, type Texts } from "./i18n";
import { type Choice, createTable } from "./jrtable";
import type { Lock } from "./lock";
import { encodedOr, setBusy, show } from "./ui";

const PROBES = 3;
// target: the body of jr_speed without q.
interface Target extends Choice {
  target: string;
}

export function setupJacredNote(page: Page, jacredDefault: string): void {
  const note = byId(page.doc, ID.jacredNote),
    radios = page.form.querySelectorAll<HTMLInputElement>(JACRED_RADIOS);
  function render(): void {
    const chosen = checkedValue(page.form, NAME.jacred);
    note.hidden = chosen === null || chosen === jacredDefault;
  }
  for (let i = 0; i < radios.length; i++) radios[i]!.addEventListener("change", render);
  render();
}

function summarize(texts: Texts, results: JrSpeed[]) {
  const ok = results.filter((result) => result.ok).length,
    ms = results.reduce((sum, result) => sum + (result.ok ? result.ms : 0), 0),
    failed = results.filter((result) => !result.ok),
    lastError = failed.length ? failed[failed.length - 1]!.msg : "";
  const partly = ok < results.length ? " (" + ok + "/" + results.length + ")" : "";
  return {
    ok: ok,
    avail: ok ? texts.yes + partly : texts.no + (lastError ? ": " + lastError : ""),
    time: ok ? (ms / ok / 1000).toFixed(1) + " " + texts.seconds : "—",
    count: results.map((result) => (result.ok ? result.count : "—")).join(" / "),
  };
}

// Own JacRed by its address; null: no address. A lone surrogate in a field: URIError.
function ownTarget(doc: Document): Target | null {
  const url = byId<HTMLInputElement>(doc, ID.jacredOwnUrl).value,
    key = byId<HTMLInputElement>(doc, ID.jacredOwnKey).value;
  if (url.replace(/\s+/g, "") === "") return null;
  const host = /^\s*https?:\/\/([^\/:?#\s]+)/i.exec(url);
  return { name: host ? host[1]! : url, value: VALUE.own, target: "b=&url=" + encodeURIComponent(url) + "&key=" + encodeURIComponent(key) };
}

const builtinTarget = (id: string): Target => ({ name: id, value: id, target: "b=" + encodeURIComponent(id) });

export function setupJacredSpeed(page: Page, api: Api, lock: Lock): void {
  const { doc, form, texts } = page;
  const checkButton = byId<HTMLButtonElement>(doc, ID.jacredCheck),
    compareButton = byId<HTMLButtonElement>(doc, ID.jacredCompare);
  // Both disabled while either one goes; out aria-busy (a screen reader says the result, not each step).
  function setRunning(out: HTMLElement, on: boolean): void {
    [checkButton, compareButton].forEach((button) => lock.run(button, on));
    setBusy(out, on);
  }
  [checkButton, compareButton].forEach((button) => lock.hold(button));
  // Why the probes stopped: the link expired (said here too, not only in the banner far above) or a save.
  const stopText = () => (lock.expired() ? texts.linkExpired : texts.stopped);
  // The probes of each target in turn; onProbe() false: no more. A 403 or a save since the start (even one done
  // within a pause: the settings may have changed) stops them before a probe.
  // Resolves to the index of the target stopped, else the count of targets.
  async function measure(targets: Target[], onProbe: (index: number, results: JrSpeed[]) => boolean): Promise<number> {
    const saves = lock.saves();
    for (let index = 0; index < targets.length; index++) {
      const results: JrSpeed[] = [];
      for (let probe = 0; probe < PROBES; probe++) {
        if (probe) await new Promise((resolve) => setTimeout(resolve, 2000));
        if (lock.expired() || lock.saves() !== saves) return index;
        const result = await api.jrSpeed(targets[index]!.target, probe);
        results.push(result.kind === "data" ? result.data : { ok: false, msg: failureText(texts, result), count: "0", ms: 0 });
        if (!onProbe(index, results)) return targets.length;
      }
    }
    return targets.length;
  }

  checkButton.addEventListener("click", () => {
    if (!lock.free(checkButton)) return;
    const out = byId(doc, ID.jacredResult),
      chosen = checkedValue(form, NAME.jacred),
      badOwn = texts.ownJacred + ": " + texts.jacredFormat;
    const target = encodedOr(out, texts.badChars, () =>
      chosen !== null && chosen !== VALUE.own ? builtinTarget(chosen) : ownTarget(doc),
    );
    if (target === undefined) return;
    if (!target) return show(out, "err", [badOwn]);
    setRunning(out, true);
    show(out, "hint", [texts.checking]);
    measure([target], (_index, results) => {
      const last = results[results.length - 1]!;
      if (last.badFormat) {
        show(out, "err", [badOwn]);
        return false;
      }
      if (results.length < PROBES) {
        show(out, "hint", [texts.checking + " " + results.length + "/" + PROBES]);
        return true;
      }
      const sum = summarize(texts, results);
      const line = target.name + " · " + sum.avail + " · " + sum.time + " · " + texts.releases + ": " + sum.count;
      show(out, sum.ok ? "ok" : "err", [line]);
      return true;
    }).then((done) => {
      if (!done) show(out, lock.expired() ? "err" : "hint", [target.name + " · " + stopText()]);
      setRunning(out, false);
    });
  });

  compareButton.addEventListener("click", () => {
    if (!lock.free(compareButton)) return;
    const out = byId(doc, ID.jacredTable),
      own = encodedOr(out, texts.badChars, () => ownTarget(doc));
    if (own === undefined) return;
    const builtins = form.querySelectorAll<HTMLInputElement>("input." + CLS.jacredBuiltin);
    const targets = Array.from(builtins, (radio) => builtinTarget(radio.getAttribute(DATA.jacredId)!));
    if (own) targets.push(own);
    setRunning(out, true);
    const table = createTable(page, lock, out, targets);
    measure(targets, (index, results) => {
      if (results.length < PROBES) {
        table.set(index, [texts.checking + " " + results.length + "/" + PROBES]);
        return true;
      }
      const sum = summarize(texts, results);
      table.set(index, [sum.avail, sum.time, sum.count]);
      return true;
    }).then((done) => {
      for (let i = done; i < targets.length; i++) table.set(i, [stopText()]);
      setRunning(out, false);
    });
  });
}
