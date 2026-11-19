// The editor of the sources (v2; #pe at the top of "Where to get films", lib/profiles.php): the page is of one source,
// ?p=<id> (its fields, the save saves it), ?p=new&from=<id> - a new one, the copy of that one. Here, from #init.profiles:
// with two sources or more (or on a new one) the list - a link each to the page of it (?p=<id>#src), the one of the
// page marked (aria-current), "main" on the main one, "no config"; "+ Add source" (?p=new&from=<the id of the page>)
// while there are fewer than max, else the line of the sources full; one source alone - "+ Add source" only, at the end
// of #src. A link is followed always, the changes not saved are lost then. "Make it the main one" (a=profile_main; not
// of the main one) and "Delete" (a=profile_del, asked first; not of the last one) - of a saved source only, disabled
// while the form has something to save; done - the page anew (of it; deleted - of the main one). After a save that
// stayed on the page: the id of the answer in the fields of the save (prof, prof_from) and the address, the list of
// the answer; after the config of the server (aio_sync) - its list.

import type { Api, ProfileList, SavedProfile } from "./api";
import { parseProfiles } from "./api";
import type { Bar } from "./bar";
import { byId, CLS, EDITOR, make, type Page } from "./dom";
import { failureText } from "./i18n";
import type { Lock } from "./lock";
import { show } from "./ui";

export interface Profiles {
  // After a change of the form: the buttons follow the bar.
  render(): void;
  // After a save of the source of the page that stayed on it: the answer's id of it; the list.
  saved(prof: SavedProfile, list: ProfileList | null): void;
  // The list of the file now (after aio_sync).
  update(list: ProfileList): void;
}

// The page of source p ("new": a new one, the copy of from) of the form's action (settings?t=<token>), on #src.
const pageOf = (action: string, p: string, from = "") =>
  action + "&p=" + encodeURIComponent(p) + (from ? "&from=" + encodeURIComponent(from) : "") + "#src";

// null: no editor on the page (a page of before v2).
export function setupProfiles(page: Page, api: Api, lock: Lock, bar: Bar, data: unknown): Profiles | null {
  const { doc, form, texts } = page;
  if (!doc.getElementById(EDITOR.block)) return null;
  // The list (with its buttons) or the box of "+ Add source" alone: PHP made the one of the page.
  const listBox = doc.getElementById(EDITOR.list),
    addBox = doc.getElementById(EDITOR.add),
    buttons = doc.getElementById(EDITOR.buttons),
    sel = byId<HTMLInputElement>(doc, EDITOR.sel),
    from = byId<HTMLInputElement>(doc, EDITOR.from);
  const action = form.getAttribute("action") || "";
  let profiles: ProfileList = parseProfiles(data) || { list: [], max: 0 };
  const current = () => profiles.list.find((p) => p.id === sel.value);

  // A link of the list: a row of the main screen (its title, the line under it, the chevron).
  function row(href: string, title: string, line: string, here: boolean, badges: [string, string][]): HTMLAnchorElement {
    const link = make(doc, "a", CLS.row);
    link.href = href;
    if (here) link.setAttribute("aria-current", "page");
    const text = make(doc, "span", CLS.rowText);
    const head = make(doc, "span", CLS.cardTitle, title);
    for (const [cls, badge] of badges) head.appendChild(make(doc, "span", cls, badge));
    text.appendChild(head);
    if (line) text.appendChild(make(doc, "span", CLS.value, line));
    link.append(text, make(doc, "span", CLS.chevron, "›"));
    return link;
  }
  // "+ Add source": the copy of the source of the page (of a new one - of the one it copies); full - the line why not.
  function addRow(): HTMLElement {
    if (profiles.list.length >= profiles.max) return make(doc, "p", CLS.row + " " + CLS.value, texts.profilesFull);
    const of = sel.value === "new" ? from.value : sel.value;
    return row(pageOf(action, "new", of), texts.profileNew, "", sel.value === "new", []);
  }
  function renderList(): void {
    if (listBox) {
      listBox.textContent = "";
      for (const p of profiles.list) {
        const badges: [string, string][] = [];
        if (p.main) badges.push([CLS.badge, texts.profileMain]);
        if (!p.ready) badges.push([CLS.badge + " " + CLS.warn, texts.profileNoConf]);
        listBox.appendChild(row(pageOf(action, p.id), p.name, p.line, p.id === sel.value, badges));
      }
      listBox.appendChild(addRow());
    }
    // One source alone, saved: its "+ Add source" at the end of #src.
    if (addBox) {
      addBox.textContent = "";
      if (profiles.list.length === 1 && current()) addBox.appendChild(addRow());
    }
  }
  function render(): void {
    if (!buttons) return;
    const p = current();
    buttons.hidden = !p;
    byId(doc, EDITOR.main).hidden = !p || p.main;
    byId(doc, EDITOR.del).hidden = !p || profiles.list.length < 2;
    lock.render();
  }
  // A request of "Make it the main one" or "Delete": done - the page of to, else the line why here.
  function act(source: HTMLButtonElement, call: () => ReturnType<Api["profileMain"]>, to: () => string): void {
    if (!lock.free(source)) return;
    lock.run(source, true);
    const results = byId(doc, EDITOR.result);
    show(results, "hint", [texts.saving]);
    call().then((result) => {
      lock.run(source, false);
      if (result.kind !== "data") return show(results, "err", [failureText(texts, result)]);
      const answer = result.data;
      if (answer.ok) return location.replace(to());
      show(results, "err", [answer.msg]);
      if (answer.profiles) update(answer.profiles);
    });
  }

  function update(list: ProfileList): void {
    profiles = list;
    renderList();
    render();
  }

  if (buttons) {
    const main = byId<HTMLButtonElement>(doc, EDITOR.main),
      del = byId<HTMLButtonElement>(doc, EDITOR.del);
    for (const button of [main, del]) lock.hold(button, () => bar.pending());
    main.addEventListener("click", () => {
      const p = current();
      if (p) act(main, () => api.profileMain(p.id), () => pageOf(action, p.id));
    });
    del.addEventListener("click", () => {
      const p = current();
      if (!p || !lock.free(del)) return;
      // The main one deleted: the first one left becomes it (as PHP).
      const next = p.main ? profiles.list.find((q) => q.id !== p.id) : undefined;
      const ask = texts.profileDeleteConfirm.replace("%s", () => p.name) + (next ? " " + texts.profileDeleteMain.replace("%s", () => next.name) : "");
      if (!confirm(ask)) return;
      // The page of the main one now.
      act(del, () => api.profileDel(p.id), () => action + "#src");
    });
  }
  renderList();
  render();
  return {
    render: render,
    update: update,
    saved(prof: SavedProfile, list: ProfileList | null): void {
      sel.value = prof.id;
      from.value = "";
      if (list) profiles = list;
      renderList();
      render();
      // A reload shows this source (a new one has its id now).
      try {
        history.replaceState(history.state, "", pageOf(action, prof.id));
      } catch (e) {}
    },
  };
}
