// AIOStreams: the answer of "Where to get films" (the mode of a config melange makes, or own config), the server,
// its config and the template, and what of the page depends on them the CSS can't show.

import type { Sync } from "./api";
import type { Confs, KnownConf } from "./confs";
import { AIO_SERVER_RADIOS, byId, checkedValue, CLS, DATA, hostOf, ID, JACRED_RADIOS, NAME, type Page, query, trimmed, VALUE, watchOf } from "./dom";
import type { Lock } from "./lock";

export interface Aio {
  render(): void;
  // "Save" creates a config: "Save and create the config".
  creates(): boolean;
  // The server chosen has a config of the mode chosen.
  hasConf(): boolean;
  // The host of the server chosen ("" - none chosen, another one without the host of an address).
  serverHost(): string;
  // The template of the config of the server in the mode chosen is not known yet (the mark "none"): a save finds it.
  templateUnknown(): boolean;
  // The config of the server (of the mode synced) after aio_sync; then render().
  applySync(sync: Sync, reset: boolean): void;
  // A save now would replace the config of the mode chosen of the server with another template.
  resetPending(): boolean;
  // The OK of the user to replace the config of the mode chosen with the template.
  confirmReset(): boolean;
  // After a save: the template chosen of each mode is the one for a new config (of P2P: only another one than shown).
  rememberTemplate(): void;
}

// The template choice of a mode (0.44.0: one each): the name of its radios, the one shown (posted as seen), the
// lines of its marks; key - the config it shows, by "<server> <config>" (undefined: as the page came).
interface Choice {
  mode: string;
  name: string;
  shown: HTMLInputElement;
  marks: HTMLElement;
  key: string | null | undefined;
}

export function setupAio(page: Page, confs: Confs, lock: Lock): Aio {
  const { doc, form, texts } = page;
  const servers = form.querySelectorAll<HTMLInputElement>(AIO_SERVER_RADIOS),
    ownUrlField = byId<HTMLInputElement>(doc, ID.aioOwnUrl),
    rdKey = byId<HTMLInputElement>(doc, ID.rdKey),
    tbKey = byId<HTMLInputElement>(doc, ID.tbKey),
    saveButton = query<HTMLButtonElement>(form, "." + CLS.save);
  const choice = (mode: string, name: string, shown: string, marks: string): Choice => ({
    mode: mode,
    name: name,
    shown: byId<HTMLInputElement>(doc, shown),
    marks: byId(doc, marks),
    key: undefined,
  });
  const choices = [
    choice(VALUE.debrid, NAME.template, ID.templateShown, ID.templateMarks),
    choice(VALUE.p2p, NAME.templateP2p, ID.templateP2pShown, ID.templateP2pMarks),
  ];
  const radiosOf = (c: Choice) => form.querySelectorAll<HTMLInputElement>("input[name=" + c.name + "]");
  // The template chosen; null: none (a mark shown), nothing posted.
  const picked = (c: Choice) => checkedValue(form, c.name);

  // The radio of the server chosen (none: as a form back with another value came).
  function selected(): HTMLInputElement | undefined {
    for (let i = 0; i < servers.length; i++) if (servers[i]!.checked) return servers[i];
    return undefined;
  }
  function ownUrl(): string {
    return ownUrlField.value
      .replace(/^\s+|\s+$/g, "")
      .replace(/\/$/, "")
      .replace(/:0+(\d)/g, ":$1")
      .toLowerCase();
  }
  // The mode chosen (one for all servers: a server has a config of each). Own config: the fields of the mode are
  // not shown (the CSS), Debrid stands for it.
  function mode(): string {
    return watchOf(form) === VALUE.p2p ? VALUE.p2p : VALUE.debrid;
  }
  function ownWatch(): boolean {
    return watchOf(form) === VALUE.own;
  }
  // Of the chosen server in the mode (the one chosen by default); of own one only if it is of the address typed.
  function currentConf(ofMode: string = mode()): KnownConf | null {
    const radio = selected();
    const conf = radio ? confs.known(radio.value, ofMode) : null;
    return radio && radio.value === VALUE.own && ownUrl() !== (conf ? conf.url : null) ? null : conf;
  }
  function p2p(): boolean {
    return mode() === VALUE.p2p;
  }
  // The one of the mode chosen; own config: none.
  function chosen(): Choice | null {
    return ownWatch() ? null : choices.find((c) => c.mode === mode())!;
  }
  // The line of the mark shown (as PHP): only while no card is checked.
  function renderMarks(c: Choice): void {
    const none = picked(c) === null;
    c.marks.querySelectorAll<HTMLElement>("[" + DATA.mark + "]").forEach((line) => (line.hidden = !none || line.getAttribute(DATA.mark) !== c.shown.value));
  }
  // The template of the config of its mode ("none": not known yet), else the one for a new config; a mark: no card
  // checked, its line shown.
  function renderTemplate(c: Choice): void {
    const conf = currentConf(c.mode);
    const shown = conf ? conf.tpl || VALUE.none : confs.templateForNew(c.mode);
    radiosOf(c).forEach((radio) => (radio.checked = radio.value === shown));
    // A missing tplNew assigns null, as a missing data-new did.
    c.shown.value = shown!;
    renderMarks(c);
  }
  // The link, login and password of the config (a missing one: empty).
  function renderConf(conf: KnownConf): void {
    const pass = byId<HTMLInputElement>(doc, ID.confPass);
    if (pass.value === conf.pass) return;
    byId<HTMLAnchorElement>(doc, ID.confLink).href = conf.cfg;
    byId<HTMLInputElement>(doc, ID.confLogin).value = conf.login || "";
    pass.value = conf.pass || "";
    byId(doc, ID.copyResult).textContent = "";
  }
  function swapText(id: string, own: boolean): void {
    const el = byId(doc, id);
    el.textContent = el.getAttribute(own ? DATA.own : DATA.made);
  }
  function creates(): boolean {
    const hasKey = trimmed(rdKey.value) + trimmed(tbKey.value) !== "";
    return !ownWatch() && !currentConf() && (hasKey || p2p());
  }
  function render(): void {
    const radio = selected(),
      ownSource = ownWatch(),
      ownServer = !!radio && radio.value === VALUE.own,
      conf = currentConf();
    // Another config of the mode of a choice (or none): its template; the page came with the right one.
    for (const c of choices) {
      const ofMode = currentConf(c.mode);
      const key = ofMode ? radio!.value + " " + ofMode.cfg : "";
      if (key !== c.key) {
        if (c.key !== undefined) renderTemplate(c);
        c.key = key;
      }
    }
    const modeP2p = p2p();
    byId(doc, ID.tmdbBox).style.display = !radio || radio.getAttribute(DATA.tmdb) === "1" ? "none" : "";
    byId(doc, ID.tmdbNote).style.display = ownServer ? "none" : "";
    byId(doc, ID.confNone).hidden = !!conf;
    byId(doc, ID.confHave).hidden = !conf;
    if (conf) renderConf(conf);
    swapText(ID.jacredText, ownSource);
    const refuses = radio ? radio.getAttribute(DATA.jr403) : null;
    // P2P on a server with Magnetio (no data-p2ptz): Magnetio asks JacRed, not the server; it can't reach a home
    // address either.
    const magnetio = modeP2p && !(radio && radio.getAttribute(DATA.p2pTorznab) === "1");
    byId(doc, ID.jacred403).hidden = ownSource || magnetio || !refuses || refuses !== checkedValue(form, NAME.jacred);
    byId(doc, ID.jacredLan).hidden = ownSource || (ownServer && !magnetio);
    if (!lock.saving()) saveButton.textContent = creates() ? texts.saveAndCreate : texts.save;
  }

  // As aio_cgi_host of PHP: of own one, the address as typed.
  function serverHost(): string {
    const radio = selected();
    return radio ? hostOf(radio.value === VALUE.own ? trimmed(ownUrlField.value) : radio.value) : "";
  }

  for (let i = 0; i < servers.length; i++) servers[i]!.addEventListener("change", render);
  ownUrlField.addEventListener("input", render);
  rdKey.addEventListener("input", render);
  tbKey.addEventListener("input", render);
  const watches = form.querySelectorAll<HTMLInputElement>("input[name=" + NAME.watch + "]");
  for (let i = 0; i < watches.length; i++) watches[i]!.addEventListener("change", render);
  const jacreds = form.querySelectorAll<HTMLInputElement>(JACRED_RADIOS);
  for (let i = 0; i < jacreds.length; i++) jacreds[i]!.addEventListener("change", render);
  render();
  for (const c of choices) radiosOf(c).forEach((radio) => radio.addEventListener("change", () => renderMarks(c)));

  return {
    render: render,
    creates: creates,
    hasConf: () => !!currentConf(),
    serverHost: serverHost,
    templateUnknown(): boolean {
      const c = chosen();
      return !!c && picked(c) === null && c.shown.value === VALUE.none;
    },
    applySync(sync: Sync, reset: boolean): void {
      // Of a server of the choice only.
      for (let i = 0; i < servers.length; i++)
        if (servers[i]!.value === sync.base && sync.mode)
          confs.store(sync.base, sync.mode, sync.conf, sync.base === VALUE.own ? ownUrl() : null);
      // Of both modes, the template of the config (as the server found it): one of the other mode chosen is saved
      // already, not a reset to ask for there later; a failed reset keeps the choice of its mode for the retry.
      for (const c of choices) if (c.mode !== sync.mode || sync.ok || !reset) c.key = null;
    },
    // Of the mode chosen with a config there, another template (the other mode: its own config, never a reset).
    resetPending(): boolean {
      const c = chosen();
      const value = c ? picked(c) : null;
      return !!c && !!currentConf() && value !== null && value !== c.shown.value;
    },
    // A mark shown: the reset is to the template for a new config of the mode (nothing posted).
    confirmReset(): boolean {
      const c = chosen() || choices[0]!;
      const value = picked(c) || confs.templateForNew(c.mode);
      const radio = Array.from(radiosOf(c)).find((one) => one.value === value);
      const title = radio ? form.querySelector('label[for="' + radio.id + '"] .t') : null;
      return confirm(texts.resetConfirm.replace("%s", serverHost()).replace("%s", title ? title.textContent || "" : ""));
    },
    rememberTemplate(): void {
      for (const c of choices) {
        // As the save: of P2P only another one than shown (else the default stays), of Debrid the one posted.
        const value = picked(c);
        if (value === null) continue;
        if (c.mode !== VALUE.p2p || value !== c.shown.value) confs.storeTemplateForNew(c.mode, value);
        if (!currentConf(c.mode)) c.shown.value = value;
      }
    },
  };
}
