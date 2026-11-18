// "Скрытые" of "Что не показывать" (#hide, lib/hidden.php): the titles hidden on the Dune (POP UP → "Hide"), from
// #init.hidden - a row each with its name, the line under it and "Unhide" (a=title_unhide: PHP takes it out of the
// file as it is now, the answer is the list then). An empty list - the line of none; no list (a file of a later
// Melange, not readable) - why. By the script with fetch only: without it the line of PHP (no list) stays.

import { type Api, type HiddenItem, parseHidden } from "./api";
import { byId, CLS, ID, make, type Page } from "./dom";
import { failureText } from "./i18n";
import type { Lock } from "./lock";
import { addLine, show } from "./ui";

export function setupHidden(page: Page, api: Api, lock: Lock, data: unknown): void {
  const { doc, texts } = page;
  const list = byId(doc, ID.hiddenList),
    results = byId(doc, ID.hiddenResult);
  const hidden = parseHidden(data);

  function render(items: HiddenItem[]): void {
    list.textContent = "";
    for (const item of items) {
      const row = make(doc, "div", CLS.row),
        text = make(doc, "span", CLS.rowText),
        button = make(doc, "button", CLS.check + " " + CLS.inline, texts.hiddenReturn);
      text.append(make(doc, "span", CLS.cardTitle, item.name), make(doc, "span", CLS.value, item.line));
      button.type = "button";
      button.addEventListener("click", () => unhide(button, item));
      row.append(text, button);
      list.appendChild(row);
      // On the page first: the lock lets go of a button off it.
      lock.hold(button);
    }
    list.hidden = !items.length;
    if (!items.length) addLine(results, "hint", texts.hiddenNone);
  }
  function unhide(button: HTMLButtonElement, item: HiddenItem): void {
    if (!lock.free(button)) return;
    lock.run(button, true);
    show(results, "hint", [texts.saving]);
    api.titleUnhide(item.k).then((result) => {
      lock.run(button, false);
      if (result.kind !== "data") return show(results, "err", [failureText(texts, result)]);
      const answer = result.data;
      show(results, answer.ok ? "ok" : "err", answer.msg ? [answer.msg] : []);
      if (answer.items) render(answer.items);
    });
  }

  results.textContent = "";
  if (hidden.msg) show(results, "warn", [hidden.msg]);
  else render(hidden.items);
}
