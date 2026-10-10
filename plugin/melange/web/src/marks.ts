// The marks of the errors of a save: the field (or the server block) of each, the top one on the page brought
// into view. A mark goes once its field is edited or a new save starts.

import type { SaveError } from "./api";
import { CLS, type Page, SERVER_FIELD } from "./dom";
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

export function setupMarks(page: Page, servers: Servers): Marks {
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
    // A radio group (RadioNodeList) is no field to mark.
    return field && "tagName" in field ? { el: field as HTMLElement, focus: true } : null;
  }
  function clear(): void {
    form.querySelectorAll("." + CLS.bad).forEach((marked) => marked.classList.remove(CLS.bad));
  }
  function show(errors: SaveError[]): void {
    const marks: Mark[] = [];
    for (const error of errors) {
      const mark = markOf(error);
      if (!mark) continue;
      mark.el.classList.add(CLS.bad);
      marks.push(mark);
    }
    // The top one on the page, not the first one of the answer.
    const top = form.querySelector("." + CLS.bad);
    const mark = marks.find((one) => one.el === top);
    if (!mark) return;
    if (mark.el.scrollIntoView) mark.el.scrollIntoView({ block: "center" });
    if (mark.focus) mark.el.focus({ preventScroll: true });
  }

  const unmark = (event: Event) => {
    const marked = (event.target as Element).closest("." + CLS.bad);
    if (marked) marked.classList.remove(CLS.bad);
  };
  form.addEventListener("input", unmark);
  form.addEventListener("change", unmark);
  return { show: show, clear: clear };
}
