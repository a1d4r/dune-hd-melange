// The field of TorrServer (one on the page): on #torr; with the answer "Torrents" (nothing plays without it) moved
// into #tsp2p of "Where to get films", and #tsmoved on #torr points there.

import { byId, ID, type Page, VALUE, watchOf } from "./dom";

export function setupTorrServer(page: Page): void {
  const { doc, form } = page;
  const box = byId(doc, ID.tsBox),
    p2pPlace = byId(doc, ID.tsP2p),
    moved = byId(doc, ID.tsMoved),
    origin = box.parentNode!,
    next = box.nextSibling;
  // The way to #torr without the script: the field is right there now.
  byId(doc, ID.tsLink).hidden = true;
  function render(): void {
    const p2p = watchOf(form) === VALUE.p2p;
    if (p2p && box.parentNode !== p2pPlace) p2pPlace.appendChild(box);
    if (!p2p && box.parentNode !== origin) origin.insertBefore(box, next);
    moved.hidden = !p2p;
  }
  form.addEventListener("change", render);
  render();
}
