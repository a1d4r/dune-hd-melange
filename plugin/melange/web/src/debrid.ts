// The keys of Debrid: both empty - the switch Real-Debrid / TorBox (#dseg) and the field of one; one filled - its
// field and "+ Add <the other>"; both filled (or the other one added) - both fields.

import { byId, DATA, ID, type Page } from "./dom";

export interface Debrid {
  // The field of the key shown (an error of a save is about it).
  reveal(field: HTMLElement): void;
}

type Key = "rd" | "tb";

export function setupDebrid(page: Page): Debrid {
  const doc = page.doc;
  const switcher = byId(doc, ID.debridSwitch);
  const keys: Record<Key, { field: HTMLInputElement; box: HTMLElement; add: HTMLElement }> = {
    rd: { field: byId<HTMLInputElement>(doc, ID.rdKey), box: byId(doc, ID.rdBox), add: byId(doc, ID.rdAdd) },
    tb: { field: byId<HTMLInputElement>(doc, ID.tbKey), box: byId(doc, ID.tbBox), add: byId(doc, ID.tbAdd) },
  };
  const filled = (key: Key) => keys[key].field.value.replace(/\s+/g, "") !== "";
  // The one shown while not both are; both: as the page came filled, or one added.
  let current: Key = !filled("rd") && filled("tb") ? "tb" : "rd";
  let both = filled("rd") && filled("tb");

  function render(): void {
    const other: Key = current === "rd" ? "tb" : "rd";
    // The switch only while nothing is typed: a key typed in is never left hidden by it.
    const switching = !both && !filled("rd") && !filled("tb");
    switcher.hidden = !switching;
    keys[current].box.hidden = false;
    keys[other].box.hidden = !both;
    keys[current].add.hidden = true;
    keys[other].add.hidden = both || switching;
    const buttons = switcher.querySelectorAll("button");
    for (let i = 0; i < buttons.length; i++)
      buttons[i]!.setAttribute("aria-pressed", buttons[i]!.getAttribute(DATA.key) === current ? "true" : "false");
  }
  function show(key: Key, focus: boolean): void {
    if (key !== current) both = true;
    render();
    if (focus) keys[key].field.focus();
  }

  const buttons = switcher.querySelectorAll("button");
  for (let i = 0; i < buttons.length; i++) {
    const button = buttons[i]!;
    button.addEventListener("click", () => {
      const key = button.getAttribute(DATA.key);
      if (key !== "rd" && key !== "tb") return;
      current = key;
      render();
    });
  }
  keys.rd.add.addEventListener("click", () => show("rd", true));
  keys.tb.add.addEventListener("click", () => show("tb", true));
  keys.rd.field.addEventListener("input", render);
  keys.tb.field.addEventListener("input", render);
  render();
  return {
    reveal(field: HTMLElement): void {
      if (field === keys.rd.field) show("rd", false);
      else if (field === keys.tb.field) show("tb", false);
    },
  };
}
