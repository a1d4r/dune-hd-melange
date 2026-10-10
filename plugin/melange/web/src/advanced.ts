// Advanced (details#adv): open or closed as left in the tab, opened by its link of the contents and by a server
// block shown.

import { byId, ID, type Page } from "./dom";

export interface Advanced {
  open(): void;
}

// Whether Advanced is open, kept for the tab: the save leaves by location.replace, the page comes closed.
const ADVANCED_KEY = "melange.adv";

// sessionStorage, when the browser lets it (a private mode may refuse it or throw): else nothing kept.
function kept(key: string): string | null {
  try {
    return typeof sessionStorage !== "undefined" ? sessionStorage.getItem(key) : null;
  } catch (e) {
    return null;
  }
}

function keep(key: string, value: string): void {
  try {
    if (typeof sessionStorage !== "undefined") sessionStorage.setItem(key, value);
  } catch (e) {}
}

export function setupAdvanced(page: Page): Advanced {
  const advanced = byId<HTMLDetailsElement>(page.doc, ID.advanced);
  if (kept(ADVANCED_KEY) === "1") advanced.open = true;
  advanced.addEventListener("toggle", () => keep(ADVANCED_KEY, advanced.open ? "1" : "0"));
  // Not every browser opens a closed details on the way to a link inside it.
  byId(page.doc, ID.tocAdvanced).addEventListener("click", () => (advanced.open = true));
  return { open: () => (advanced.open = true) };
}
