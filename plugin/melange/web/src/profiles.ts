// The source profiles (#pr, 0.58.0) at the bottom of "Where to get films", by the script only (the CSS shows the block
// with "Source profiles" on). The list from #init.profiles, then from each answer of a=profile_*: a card each - its
// name with "now" on the one in use, its kind and host, "No config" when its config is not on the Dune; "Edit" opens
// its name (and the link of an own config) and "Save the profile" (a=profile_edit), "Delete" asks first
// (a=profile_del). "Save the current one as a profile" (a=profile_add) makes one of the saved source: disabled while
// the form has something to save (the bar) or the profiles are full, the line why under it. Each request is a write
// as a save is: none goes while another one does.

import { type Api, parseProfiles, type Profile, type ProfileAnswer, type ProfileList, type Result } from "./api";
import type { Bar } from "./bar";
import { byId, checkedValue, CLS, ID, NAME, type Page, query, sourceWasLink, VALUE } from "./dom";
import { failureText } from "./i18n";
import type { Lock } from "./lock";
import { bindEye, maskSecret } from "./secrets";
import { encodedOr, show } from "./ui";

export interface Profiles {
  // After a change of the form: the button of a new one follows the bar.
  render(): void;
  // After a save that stayed on the page (and the config made there): "now" and "No config" anew (a=profiles).
  refresh(): void;
}

// The fields of a request by the part of its errors (name, url).
type Fields = Record<string, HTMLInputElement>;

// changed: the main screen and the bar follow a field the script set (the link in use).
export function setupProfiles(page: Page, api: Api, lock: Lock, bar: Bar, data: unknown, changed: () => void): Profiles {
  const { doc, texts } = page;
  const block = byId(doc, ID.profiles),
    listBox = byId(doc, ID.profileList),
    none = byId(doc, ID.profilesNone),
    nameField = byId<HTMLInputElement>(doc, ID.profileName),
    add = byId<HTMLButtonElement>(doc, ID.profileAdd),
    dirty = byId(doc, ID.profileDirty),
    full = byId(doc, ID.profilesFull),
    results = byId(doc, ID.profileResult);
  let profiles: ProfileList = parseProfiles(data) || { list: [], def: "", max: 0 };
  const isFull = () => profiles.list.length >= profiles.max;
  block.hidden = false;
  lock.hold(add, () => bar.pending() || isFull());

  function el<K extends keyof HTMLElementTagNameMap>(tag: K, cls: string, text?: string): HTMLElementTagNameMap[K] {
    const made = doc.createElement(tag);
    if (cls) made.className = cls;
    if (text !== undefined) made.textContent = text;
    return made;
  }
  function button(text: string, cls: string = CLS.check): HTMLButtonElement {
    const made = el("button", cls, text);
    made.type = "button";
    return made;
  }
  // A field of a card as aio_cgi_unposted makes one (no name=: not posted, not a change of the bar).
  function field(id: string, label: string, value: string, max: number): [HTMLLabelElement, HTMLDivElement, HTMLInputElement] {
    const labelEl = el("label", "", label);
    labelEl.htmlFor = id;
    const box = el("div", "f");
    const input = el("input", "");
    input.id = id;
    input.value = value;
    for (const [name, attr] of [["autocomplete", "off"], ["autocapitalize", "off"], ["autocorrect", "off"], ["spellcheck", "false"]])
      input.setAttribute(name!, attr!);
    if (max) input.maxLength = max;
    box.appendChild(input);
    return [labelEl, box, input];
  }

  // A request of the button: out gets the result of an error about one of the fields (marked) or, with no field,
  // results of the block; a list in the answer is shown anew.
  function act(source: HTMLButtonElement, out: HTMLElement, fields: Fields, call: () => Promise<Result<ProfileAnswer>>, done?: (answer: ProfileAnswer) => void): void {
    if (!lock.free(source)) return;
    const pending = encodedOr(out, texts.badChars, call);
    if (!pending) return;
    for (const name in fields) fields[name]!.classList.remove(CLS.bad);
    lock.run(source, true);
    show(out, "hint", [texts.saving]);
    pending.then((result) => {
      lock.run(source, false);
      if (result.kind !== "data") return show(out, "err", [failureText(texts, result)]);
      const answer = result.data;
      const bad = !answer.ok && answer.part ? fields[answer.part] : undefined;
      if (bad && bad.isConnected) {
        show(out, "err", [answer.msg]);
        bad.classList.add(CLS.bad);
        bad.focus();
        return;
      }
      if (done) done(answer);
      if (out !== results) out.textContent = "";
      if (answer.profiles) {
        profiles = answer.profiles;
        renderList();
      }
      if (answer.msg) show(results, answer.ok ? "ok" : "err", [answer.msg]);
      else results.textContent = "";
      render();
    });
  }

  // The link of the profile in use changed (it is the source now): the field of the own config takes it while it
  // shows the old one, the save no change by it; src_was too while it has the old one (else it is another source
  // than the stored one already: the save takes the posted one).
  function linkInUse(old: string, url: string): void {
    const manifest = page.form.querySelector<HTMLInputElement>("input[name=" + NAME.manifest + "]");
    if (!manifest || manifest.value !== old) return;
    manifest.value = url;
    bar.rebaseField(NAME.manifest);
    const was = byId<HTMLInputElement>(doc, ID.sourceWas);
    was.value = sourceWasLink(was.value, old, url);
    changed();
  }

  // The card of profile i; its buttons of requests go to held (the lock takes them once on the page).
  function card(p: Profile, i: number, held: HTMLButtonElement[]): HTMLElement {
    const box = el("div", CLS.profile);
    const head = el("p", CLS.profileHead);
    const title = el("span", CLS.cardTitle, p.name);
    if (p.now) title.appendChild(el("span", CLS.badge, texts.profileNow));
    head.appendChild(title);
    head.appendChild(el("span", CLS.value, p.line));
    if (!p.ready) head.appendChild(el("span", CLS.value + " " + CLS.warn, texts.profileNoConf));
    box.appendChild(head);
    // "Edit": the fields and "Save the profile", hidden till then.
    const edit = el("div", "");
    edit.hidden = true;
    const fields: Fields = {};
    // The longest name: as of the field of a new one (AIO_PROFILE_NAME_MAX).
    const [nameLabel, nameBox, name] = field("pen" + i, texts.profileName, p.name, nameField.maxLength > 0 ? nameField.maxLength : 0);
    edit.append(nameLabel, nameBox);
    fields.name = name;
    if (p.url !== null) {
      const [urlLabel, urlBox, url] = field("peu" + i, texts.profileLink, p.url, 0);
      url.classList.add(CLS.secret);
      maskSecret(url);
      // The eye of the page (its icon), bound to this field.
      const eye = query<HTMLButtonElement>(doc, "." + CLS.eye).cloneNode(true) as HTMLButtonElement;
      eye.removeAttribute("data-for");
      eye.classList.remove(CLS.on);
      bindEye(eye, url);
      urlBox.appendChild(eye);
      edit.append(urlLabel, urlBox);
      fields.url = url;
    }
    const out = el("div", CLS.result);
    out.setAttribute("aria-live", "polite");
    const save = button(texts.profileSave);
    edit.appendChild(save);
    const open = button(texts.profileEdit),
      del = button(texts.profileDelete, CLS.check + " " + CLS.profileDelete);
    open.setAttribute("aria-expanded", "false");
    box.append(edit, open, del, out);
    held.push(save, del);
    open.addEventListener("click", () => {
      edit.hidden = !edit.hidden;
      open.setAttribute("aria-expanded", edit.hidden ? "false" : "true");
      if (!edit.hidden) name.focus();
    });
    save.addEventListener("click", () => {
      const url = fields.url ? fields.url.value : null;
      act(save, out, fields, () => api.profileEdit(i, p.name, name.value, url), (answer) => {
        if (answer.ok && answer.manifest && p.url !== null && answer.profiles && answer.profiles.list[i])
          linkInUse(p.url, answer.profiles.list[i]!.url || "");
      });
    });
    del.addEventListener("click", () => {
      if (!lock.free(del) || !confirm(texts.profileDeleteConfirm.replace("%s", p.name))) return;
      act(del, out, {}, () => api.profileDel(i, p.name));
    });
    return box;
  }

  function renderList(): void {
    listBox.textContent = "";
    const held: HTMLButtonElement[] = [];
    profiles.list.forEach((p, i) => listBox.appendChild(card(p, i, held)));
    for (const one of held) lock.hold(one);
    none.hidden = profiles.list.length > 0;
    nameField.placeholder = profiles.def;
  }
  function render(): void {
    const fullNow = isFull();
    full.hidden = !fullNow;
    dirty.hidden = fullNow || !bar.pending();
    lock.render();
  }

  add.addEventListener("click", () => {
    act(add, results, { name: nameField }, () => api.profileAdd(nameField.value), (answer) => {
      if (answer.ok) nameField.value = "";
    });
  });
  renderList();
  render();
  return {
    render: render,
    refresh(): void {
      // Off as saved: the block is hidden, a save turning it on comes first.
      if (checkedValue(page.form, NAME.srcProfiles) !== VALUE.on) return;
      api.profiles().then((result) => {
        if (result.kind !== "data" || !result.data.profiles) return;
        profiles = result.data.profiles;
        renderList();
        render();
      });
    },
  };
}
