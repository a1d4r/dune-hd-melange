// The bar of the save (#bar): shown while there is something to save - the changes since the page came (or since
// the last save that stayed on it), their count in #dirty - or a save wanted with none (a config to create), or
// results of a save in #sr (until "Hide", #srx).

import { byId, ID, isMark, type Page } from "./dom";
import { plural } from "./i18n";

export interface Bar {
  // After a change of the form.
  render(): void;
  // After a save: the values now are the ones saved.
  rebase(): void;
}

type Control = HTMLInputElement | HTMLSelectElement | HTMLButtonElement;

// The values of the controls posted, by name (a radio group: the value checked); not the hidden ones (the script's or
// PHP's own, as aio_tpl_seen), not the buttons.
function values(form: HTMLFormElement): Record<string, string> {
  const out: Record<string, string> = {};
  const controls = form.elements;
  for (let i = 0; i < controls.length; i++) {
    const control = controls[i] as Control;
    if (!control.name || control.tagName === "BUTTON" || control.type === "hidden") continue;
    if (control.type === "radio" || control.type === "checkbox") {
      if (!(control.name in out)) out[control.name] = "";
      if ((control as HTMLInputElement).checked) out[control.name] = control.value;
      continue;
    }
    // A mark of the template (disabled option) is never posted: as the template shown, not a change.
    if (control.tagName === "SELECT" && isMark((control as HTMLSelectElement).options[(control as HTMLSelectElement).selectedIndex]))
      out[control.name] = "";
    else out[control.name] = control.value;
  }
  return out;
}

// wanted: a save is wanted with no change (Aio), saving: a save goes (Lock).
export function setupBar(page: Page, wanted: () => boolean, saving: () => boolean): Bar {
  const { doc, form, texts } = page;
  const bar = byId(doc, ID.bar),
    dirty = byId(doc, ID.dirty),
    results = byId(doc, ID.saveResult),
    hide = byId(doc, ID.hideResult),
    lang = doc.documentElement.lang;
  let base = values(form);

  function render(): void {
    const now = values(form);
    let count = 0;
    for (const name in now) if (now[name] !== base[name]) count++;
    dirty.textContent = count ? plural(texts, lang, count) : "";
    hide.hidden = !results.firstChild || saving();
    bar.hidden = !count && !wanted() && !saving() && !results.firstChild;
  }
  hide.addEventListener("click", () => {
    results.textContent = "";
    render();
  });
  render();
  return {
    render: render,
    rebase(): void {
      base = values(form);
      render();
    },
  };
}
