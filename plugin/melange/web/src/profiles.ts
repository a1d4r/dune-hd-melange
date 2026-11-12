// The editor of the source profiles (#pe at the top of "Where to get films", 0.58.0): PHP makes it with "Source
// profiles" on (the page is of one profile, ?p=<i>; its fields are those of the source, the save saves it), the CSS
// shows it while that is on. Here: the list of the profiles from #init.profiles - a link each to the page of it
// (?p=<i>#src), the one of the page marked (aria-current), "now" on the one in use, "no config"; "+ New profile"
// (?p=new) while there are fewer than max. A link is not followed while the form has something to save (the bar): the
// line why (#ped) instead. "Make it current" (a=profile_use) and "Delete" (a=profile_del, asked first) - of a saved
// profile only, the first not of the one in use; disabled while the form has something to save; done - the page of
// it anew. After a save of the profile that stayed on the page: its place, name and "in use" of the answer in the
// fields of the save (prof, prof_was, prof_now) and the address, the list of the answer; after the config of the
// server (aio_sync) - its list.

import type { Api, ProfileList, SavedProfile } from "./api";
import { parseProfiles } from "./api";
import type { Bar } from "./bar";
import { byId, CLS, EDITOR, type Page } from "./dom";
import { failureText } from "./i18n";
import type { Lock } from "./lock";
import { show } from "./ui";

export interface Profiles {
  // After a change of the form: the buttons and the links follow the bar.
  render(): void;
  // After a save of the profile of the page that stayed on it: the answer's place of it, its name, in use; the list.
  saved(prof: SavedProfile, list: ProfileList | null): void;
  // The list of the file now (after aio_sync).
  update(list: ProfileList): void;
}

// The page of profile p ("new": a new one) of the form's action (settings?t=<token>), on #src.
const pageOf = (action: string, p: string) => action + "&p=" + p + "#src";

// null: no editor on the page ("Source profiles" off when it came).
export function setupProfiles(page: Page, api: Api, lock: Lock, bar: Bar, data: unknown): Profiles | null {
  const { doc, form, texts } = page;
  const box = doc.getElementById(EDITOR.block);
  if (!box) return null;
  const listBox = byId(doc, EDITOR.list),
    why = byId(doc, EDITOR.dirty),
    buttons = byId(doc, EDITOR.buttons),
    use = byId<HTMLButtonElement>(doc, EDITOR.use),
    del = byId<HTMLButtonElement>(doc, EDITOR.del),
    results = byId(doc, EDITOR.result),
    sel = byId<HTMLInputElement>(doc, EDITOR.sel),
    was = byId<HTMLInputElement>(doc, EDITOR.was),
    now = byId<HTMLInputElement>(doc, EDITOR.now);
  const action = form.getAttribute("action") || "";
  let profiles: ProfileList = parseProfiles(data) || { list: [], max: 0 };
  // The saved profile of the page (its place): -1 - a new one.
  const index = () => (/^[0-9]+$/.test(sel.value) ? Number(sel.value) : -1);
  const current = () => profiles.list[index()];

  function el<K extends keyof HTMLElementTagNameMap>(tag: K, cls: string, text?: string): HTMLElementTagNameMap[K] {
    const made = doc.createElement(tag);
    if (cls) made.className = cls;
    if (text !== undefined) made.textContent = text;
    return made;
  }
  // A link of the list: a row of the main screen (its title, the line under it, the chevron).
  function row(href: string, title: string, line: string, here: boolean, badges: [string, string][]): HTMLAnchorElement {
    const link = el("a", CLS.row);
    link.href = href;
    if (here) link.setAttribute("aria-current", "page");
    const text = el("span", CLS.rowText);
    const head = el("span", CLS.cardTitle, title);
    for (const [cls, badge] of badges) head.appendChild(el("span", cls, badge));
    text.appendChild(head);
    if (line) text.appendChild(el("span", CLS.value, line));
    link.append(text, el("span", CLS.chevron, "›"));
    // Not with something to save: the page of another profile would lose it.
    link.addEventListener("click", (event) => {
      if (!bar.pending()) return;
      event.preventDefault();
      why.textContent = texts.profileDirty;
      why.hidden = false;
    });
    return link;
  }
  function renderList(): void {
    listBox.textContent = "";
    profiles.list.forEach((p, i) => {
      const badges: [string, string][] = [];
      if (p.now) badges.push([CLS.badge, texts.profileNow]);
      if (!p.ready) badges.push([CLS.badge + " " + CLS.warn, texts.profileNoConf]);
      listBox.appendChild(row(pageOf(action, String(i)), p.name, p.line, i === index(), badges));
    });
    if (profiles.list.length < profiles.max)
      listBox.appendChild(row(pageOf(action, "new"), texts.profileNew, "", sel.value === "new", []));
    else listBox.appendChild(el("p", CLS.row + " " + CLS.value, texts.profilesFull));
  }
  function render(): void {
    const p = current();
    buttons.hidden = !p;
    use.hidden = !p || p.now;
    if (!bar.pending()) why.hidden = true;
    lock.render();
  }
  // A request of "Make it current" or "Delete": done - the page of to (its place now), else the line why here.
  function act(source: HTMLButtonElement, call: () => ReturnType<Api["profileUse"]>, to: () => string): void {
    if (!lock.free(source)) return;
    lock.run(source, true);
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

  for (const button of [use, del]) lock.hold(button, () => bar.pending());
  use.addEventListener("click", () => {
    const p = current();
    if (p) act(use, () => api.profileUse(index(), p.name), () => pageOf(action, String(index())));
  });
  del.addEventListener("click", () => {
    const p = current();
    if (!p || !lock.free(del) || !confirm(texts.profileDeleteConfirm.replace("%s", () => p.name))) return;
    // The page of the one in use now (none: of the source in use).
    act(del, () => api.profileDel(index(), p.name), () => action + "#src");
  });
  renderList();
  render();
  return {
    render: render,
    update: update,
    saved(prof: SavedProfile, list: ProfileList | null): void {
      sel.value = String(prof.i);
      was.value = prof.name;
      now.value = prof.now ? "1" : "";
      if (list) profiles = list;
      renderList();
      render();
      // A reload shows this profile (a new one has its place now).
      try {
        history.replaceState(history.state, "", pageOf(action, String(prof.i)));
      } catch (e) {}
    },
  };
}
