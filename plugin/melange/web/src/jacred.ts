// JacRed: the note under a non-default choice, "Check" of own one and "Compare speed" (3 probes each, 2 s apart).

import type { Api, JrSpeed } from "./api";
import { byId, checkedValue, CLS, DATA, ID, JACRED_RADIOS, NAME, type Page, query, VALUE } from "./dom";
import { failureText, type Texts } from "./i18n";
import type { Lock } from "./lock";
import { encodedOr, setBusy, show } from "./ui";

const PROBES = 3;
// name: as shown, value: the id of a built-in one or "own" (data-jr of its card), target: the body of jr_speed
// without q.
interface Target {
  name: string;
  value: string;
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

// The line of a card of "Compare speed": the mean time of the probes answered and the releases they found
// ("0,6 s · 214"), how many answered if not all; none: noAnswer.
function speedLine(texts: Texts, lang: string, results: JrSpeed[]): string {
  const ok = results.filter((result) => result.ok);
  if (!ok.length) return texts.noAnswer;
  let time = (ok.reduce((sum, result) => sum + result.ms, 0) / ok.length / 1000).toFixed(1);
  if (lang === "ru") time = time.replace(".", ",");
  const counts = ok.map((result) => Number(result.count));
  const releases = counts.every((n) => isFinite(n)) ? String(counts.reduce((sum, n) => sum + n, 0)) : ok.map((result) => result.count).join(" / ");
  return time + " " + texts.seconds + " · " + releases + (ok.length < results.length ? " (" + ok.length + "/" + results.length + ")" : "");
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
  const { doc, form, texts } = page,
    lang = doc.documentElement.lang;
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

  // "Check" of own JacRed (its address): the line under the field.
  checkButton.addEventListener("click", () => {
    if (!lock.free(checkButton)) return;
    const out = byId(doc, ID.jacredResult),
      badOwn = texts.ownJacred + ": " + texts.jacredFormat;
    const target = encodedOr(out, texts.badChars, () => ownTarget(doc));
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

  // Every built-in JacRed and own one (if its address is there): the line of each in its card (data-jr), the
  // errors of those that failed in #jrsr.
  compareButton.addEventListener("click", () => {
    if (!lock.free(compareButton)) return;
    const out = byId(doc, ID.jacredSpeed),
      own = encodedOr(out, texts.badChars, () => ownTarget(doc));
    if (own === undefined) return;
    const builtins = form.querySelectorAll<HTMLInputElement>("input." + CLS.jacredBuiltin);
    const targets = Array.from(builtins, (radio) => builtinTarget(radio.getAttribute(DATA.jacredId)!));
    if (own) targets.push(own);
    const cards = targets.map((target) => query(form, "." + CLS.jacredSpeed + "[" + DATA.jacredOf + '="' + target.value + '"]'));
    // Own one not compared: no old line of it left.
    form.querySelectorAll("." + CLS.jacredSpeed).forEach((card) => (card.textContent = ""));
    cards.forEach((card) => (card.textContent = "…"));
    out.textContent = "";
    setRunning(out, true);
    const errors: string[] = [];
    measure(targets, (index, results) => {
      // Own one is the last: a wrong address or key stops it at once.
      if (results[results.length - 1]!.badFormat && targets[index]!.value === VALUE.own) {
        cards[index]!.textContent = texts.noAnswer;
        errors.push(texts.ownJacred + ": " + texts.jacredFormat);
        return false;
      }
      if (results.length < PROBES) {
        cards[index]!.textContent = results.length + "/" + PROBES;
        return true;
      }
      cards[index]!.textContent = speedLine(texts, lang, results);
      const failed = results.filter((result) => !result.ok);
      if (failed.length) errors.push(targets[index]!.name + ": " + failed[failed.length - 1]!.msg);
      return true;
    }).then((done) => {
      for (let i = done; i < targets.length; i++) cards[i]!.textContent = texts.stopped;
      if (done < targets.length && lock.expired()) errors.push(texts.linkExpired);
      show(out, "err", errors);
      setRunning(out, false);
    });
  });
}
