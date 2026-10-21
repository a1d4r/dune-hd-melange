// The save without leaving the page: the form by fetch, then the config of the server (aio_sync).

import type { Aio } from "./aio";
import type { Api, ConfHow, Save, SaveError } from "./api";
import type { Bar, Values } from "./bar";
import { byId, checkedValue, CLS, ID, NAME, type Page, query, trimmed, tsUserinfo } from "./dom";
import type { Home } from "./home";
import { failureText, type Texts } from "./i18n";
import type { Lock } from "./lock";
import type { Marks } from "./marks";
import { addLine, encodedOr, setLine, show } from "./ui";

type Control = HTMLInputElement | HTMLSelectElement | HTMLButtonElement;

// The fields as the browser posts them. A lone surrogate in a field: URIError.
function serialize(form: HTMLFormElement): string[] {
  const pairs: string[] = [],
    controls = form.elements;
  for (let i = 0; i < controls.length; i++) {
    const control = controls[i] as Control;
    if (!control.name || control.disabled || control.tagName === "BUTTON") continue;
    if ((control.type === "checkbox" || control.type === "radio") && !(control as HTMLInputElement).checked) continue;
    pairs.push(encodeURIComponent(control.name) + "=" + encodeURIComponent(control.value));
  }
  return pairs;
}

// The texts of the config after the save, by the aio of its answer: on the button while it goes, and when it got
// no answer (saved already: the text tells which button to press again).
const CONF_TEXTS: Record<ConfHow, readonly [keyof Texts, keyof Texts]> = {
  create: ["creating", "notCreated"],
  update: ["updating", "notUpdated"],
  reset: ["resetting", "notReset"],
};

// changed: the main screen and the bar follow (a config made, the values saved).
export function setupSave(page: Page, api: Api, lock: Lock, aio: Aio, marks: Marks, bar: Bar, home: Home, changed: () => void): void {
  const { doc, form, texts } = page;
  const button = query<HTMLButtonElement>(form, "." + CLS.save);
  const results = () => byId(doc, ID.saveResult);
  const templates = [NAME.template, NAME.templateP2p];

  // The config of the chosen server after the save: its result replaces the "creating" line; failed, the line
  // "Saved" (savedLine) says only the settings on the Dune are.
  function syncConf(how: ConfHow, savedLine: HTMLElement): void {
    const [going, failed] = CONF_TEXTS[how];
    button.textContent = texts[going];
    const line = addLine(results(), "hint", button.textContent);
    api.aioSync(how === "reset").then((result) => {
      if (result.kind === "data") {
        const sync = result.data;
        aio.applySync(sync, how === "reset");
        // Hidden by "Hide" meanwhile: gone already.
        if (sync.skip && line.parentNode) line.parentNode.removeChild(line);
        else setLine(line, sync.ok ? "ok" : "err", sync.msg);
        // Its field marked as of a save error (no Debrid key, the own server).
        if (!sync.ok) marks.show([{ msg: sync.msg, field: sync.field }]);
      } else if (result.kind !== "http") setLine(line, "err", texts[failed]);
      else setLine(line, "err", failureText(texts, result));
      if (result.kind !== "data" || !result.data.ok) savedLine.textContent = texts.savedOnDune;
      const before = templates.map((name) => checkedValue(form, name));
      aio.render();
      // The template of the config found (a mark "none" no more) is no change either; the rest typed meanwhile is.
      templates.forEach((name, i) => {
        if (checkedValue(form, name) !== before[i]) bar.rebaseField(name);
      });
      changed();
    });
  }
  // The list under the button stays; the fields of the errors marked.
  function showErrors(errors: SaveError[]): void {
    show(results(), "err", errors.map((error) => error.msg));
    marks.show(errors);
  }
  // A login and password typed in the address are saved in their fields (aio_cgi_ts_parse): so on the page too, or
  // a next save takes them from the address again, not the fields. posted -> the values saved.
  function moveTsUserinfo(posted: Values): Values {
    const [addr, user, pass] = tsUserinfo(trimmed(posted.ts ?? ""));
    if (user === null || pass === null) return posted;
    const field = (id: string) => byId<HTMLInputElement>(doc, id);
    // Typed meanwhile: stays (a change).
    if (field(ID.ts).value === posted.ts) {
      field(ID.ts).value = addr;
      field(ID.tsUser).value = user;
      field(ID.tsPass).value = pass;
    }
    return { ...posted, ts: addr, ts_user: user, ts_pass: pass };
  }
  // posted: the values of the form as the save posted them.
  function saved(answer: Extract<Save, { ok: true }>, posted: Values): void {
    aio.rememberTemplate();
    if (!answer.notes.length && !answer.aio) {
      location.replace(answer.next);
      return;
    }
    results().textContent = "";
    home.saved();
    // Staying on the page: what is saved is not a change any more; typed while it went still is.
    bar.rebase(moveTsUserinfo(posted));
    const savedLine = addLine(results(), "ok", texts.saved);
    for (const note of answer.notes) addLine(results(), note.level === "ok" ? "ok" : "warn", note.msg);
    if (answer.aio) syncConf(answer.aio, savedLine);
  }
  function save(reset: boolean): void {
    const pairs = encodedOr(results(), texts.badChars, () => serialize(form));
    if (!pairs) return;
    const posted = bar.snapshot();
    if (reset) pairs.push("aio_reset=1");
    button.textContent = texts.saving;
    byId(doc, ID.message).textContent = "";
    results().textContent = "";
    marks.clear();
    api.save(pairs.join("&")).then((result) => {
      aio.render();
      if (result.kind !== "data") showErrors([{ msg: failureText(texts, result), field: null }]);
      else if (result.data.ok) saved(result.data, posted);
      else showErrors(result.data.errors);
      changed();
    });
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    if (lock.blocked()) return;
    // Another template with a config there: the save resets it, only asked.
    if (aio.resetPending()) {
      if (aio.confirmReset()) save(true);
      return;
    }
    save(false);
  });
  byId(doc, ID.resetConf).addEventListener("click", (event) => {
    event.preventDefault();
    if (lock.blocked() || !aio.confirmReset()) return;
    save(true);
  });
}
