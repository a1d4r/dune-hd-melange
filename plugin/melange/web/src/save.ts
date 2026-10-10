// The save without leaving the page: the form by fetch, then the config of the server (aio_sync).

import type { Aio } from "./aio";
import type { Api, ConfHow, Save, SaveError } from "./api";
import { byId, CLS, ID, isMark, type Page, query } from "./dom";
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
    // A mark of the template is never posted, as by the browser.
    if (control.tagName === "SELECT") {
      const select = control as HTMLSelectElement;
      if (isMark(select.options[select.selectedIndex])) continue;
    }
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

export function setupSave(page: Page, api: Api, lock: Lock, aio: Aio, marks: Marks): void {
  const { doc, form, texts } = page;
  const button = query<HTMLButtonElement>(form, "." + CLS.save);
  const results = () => byId(doc, ID.saveResult);

  // The config of the chosen server after the save: its result replaces the "creating" line.
  function syncConf(how: ConfHow): void {
    const [going, failed] = CONF_TEXTS[how];
    button.textContent = texts[going];
    const line = addLine(results(), "hint", button.textContent);
    api.aioSync(how === "reset").then((result) => {
      if (result.kind === "data") {
        const sync = result.data;
        aio.applySync(sync, how === "reset");
        if (sync.skip) results().removeChild(line);
        else setLine(line, sync.ok ? "ok" : "err", sync.msg);
      } else if (result.kind !== "http") setLine(line, "err", texts[failed]);
      else setLine(line, "err", failureText(texts, result));
      aio.render();
    });
  }
  // The list under the button stays; the fields of the errors marked.
  function showErrors(errors: SaveError[]): void {
    show(results(), "err", errors.map((error) => error.msg));
    marks.show(errors);
  }
  function saved(answer: Extract<Save, { ok: true }>): void {
    aio.rememberTemplate();
    if (!answer.notes.length && !answer.aio) {
      location.replace(answer.next);
      return;
    }
    show(results(), "ok", [texts.saved]);
    for (const note of answer.notes) addLine(results(), note.level === "ok" ? "ok" : "warn", note.msg);
    if (answer.aio) syncConf(answer.aio);
  }
  function save(reset: boolean): void {
    const pairs = encodedOr(results(), texts.badChars, () => serialize(form));
    if (!pairs) return;
    if (reset) pairs.push("aio_reset=1");
    button.textContent = texts.saving;
    byId(doc, ID.message).textContent = "";
    results().textContent = "";
    marks.clear();
    api.save(pairs.join("&")).then((result) => {
      aio.render();
      if (result.kind !== "data") showErrors([{ msg: failureText(texts, result), field: null }]);
      else if (result.data.ok) saved(result.data);
      else showErrors(result.data.errors);
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
