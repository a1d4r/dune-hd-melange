// The marks of the errors of a save: the field (or the server block, the cards of radios) of each, aria-invalid; the
// top one on the page: its screen opened (the address #<screen>), the details or own fields around it shown, brought
// into view.
// A mark goes once its field is edited or a new save starts.

import type { SaveError } from "./api";
import type { Debrid } from "./debrid";
import { CLS, type Page, RADIO_GROUP, SERVER_FIELD } from "./dom";
import type { Servers } from "./servers";

export interface Marks {
  show(errors: SaveError[]): void;
  clear(): void;
}

// What shows an error, and whether it takes the focus (a block does not).
interface Mark {
  el: HTMLElement;
  focus: boolean;
}

export function setupMarks(page: Page, servers: Servers, debrid: Debrid): Marks {
  const form = page.form;
  // Its server block (shown, opened; the PHP checks a block as a whole, s<n>_url for any field), else its field.
  function markOf(error: SaveError): Mark | null {
    if (error.field === null) return null;
    const server = SERVER_FIELD.exec(error.field);
    if (server) {
      const blockNo = Number(server[1]);
      return servers.has(blockNo) ? { el: servers.reveal(blockNo), focus: false } : null;
    }
    const field = form.elements.namedItem(error.field);
    if (field && "tagName" in field) {
      debrid.reveal(field as HTMLElement);
      return { el: field as HTMLElement, focus: true };
    }
    // Radios (RadioNodeList): the cards of the first one (its label, the first on the page: not of #home), if they
    // are in a group (RADIO_GROUP); no focus, as of a block.
    const radio = field && field.length ? (field[0] as HTMLInputElement) : null;
    const card = radio && radio.id ? form.querySelector('label[for="' + radio.id + '"]') : null;
    const group = card ? card.closest(RADIO_GROUP) : null;
    return group ? { el: group as HTMLElement, focus: false } : null;
  }
  function unmark(el: Element): void {
    el.classList.remove(CLS.bad);
    el.removeAttribute("aria-invalid");
  }
  function clear(): void {
    form.querySelectorAll("." + CLS.bad + ",[aria-invalid]").forEach(unmark);
    form.querySelectorAll("." + CLS.ownFields).forEach((box) => box.classList.remove(CLS.on));
  }
  // Its screen the target of the address (the CSS shows it; the back of the phone goes to the main one), every
  // details around it open, the fields of an own one shown even if another card is chosen.
  function open(el: HTMLElement): void {
    for (let up = el.parentElement; up; up = up.parentElement) {
      if (up.tagName === "DETAILS") (up as HTMLDetailsElement).open = true;
      if (up.classList.contains(CLS.ownFields)) up.classList.add(CLS.on);
      if (up.classList.contains(CLS.screen) && up.id && location.hash !== "#" + up.id) location.hash = "#" + up.id;
    }
  }
  function show(errors: SaveError[]): void {
    const marks: Mark[] = [];
    for (const error of errors) {
      const mark = markOf(error);
      if (!mark) continue;
      mark.el.classList.add(CLS.bad);
      if (mark.focus) mark.el.setAttribute("aria-invalid", "true");
      marks.push(mark);
    }
    // The top one on the page, not the first one of the answer.
    const top = form.querySelector("." + CLS.bad);
    const mark = marks.find((one) => one.el === top);
    if (!mark) return;
    open(mark.el);
    if (mark.el.scrollIntoView) mark.el.scrollIntoView({ block: "center" });
    if (mark.focus) mark.el.focus({ preventScroll: true });
  }

  // A radio is in no group: its cards are (label[for]).
  const edited = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const card = target.type === "radio" && target.id ? form.querySelector('label[for="' + target.id + '"]') : null;
    const marked = (card || target).closest("." + CLS.bad + ",[aria-invalid]");
    if (marked) unmark(marked);
    if (target.getAttribute("aria-invalid")) unmark(target);
  };
  form.addEventListener("input", edited);
  form.addEventListener("change", edited);
  return { show: show, clear: clear };
}
