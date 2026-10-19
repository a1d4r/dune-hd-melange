// AIOStreams: the answer of "Where to get films" (the mode of a config melange makes, or own config), the server,
// its config and the template, and what of the page depends on them the CSS can't show.

import type { Sync } from "./api";
import type { Confs, KnownConf } from "./confs";
import { byId, checkedValue, CLS, DATA, hostOf, ID, isMark, JACRED_RADIOS, NAME, type Page, query, trimmed, VALUE, watchOf } from "./dom";
import type { Lock } from "./lock";

export interface Aio {
  render(): void;
  // "Save" creates a config: "Save and create the config".
  creates(): boolean;
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

// The template choice of a mode (0.44.0: one each): its select, the one shown (posted as seen), its hint; key - the
// config it shows, by "<server> <config>" (undefined: as the page came).
interface Choice {
  mode: string;
  select: HTMLSelectElement;
  shown: HTMLInputElement;
  hint: HTMLElement;
  key: string | null | undefined;
}

export function setupAio(page: Page, confs: Confs, lock: Lock): Aio {
  const { doc, form, texts } = page;
  const server = byId<HTMLSelectElement>(doc, ID.aioServer),
    ownUrlField = byId<HTMLInputElement>(doc, ID.aioOwnUrl),
    rdKey = byId<HTMLInputElement>(doc, ID.rdKey),
    tbKey = byId<HTMLInputElement>(doc, ID.tbKey),
    serverName = byId(doc, ID.aioServerName),
    saveButton = query<HTMLButtonElement>(form, "." + CLS.save);
  const choice = (mode: string, select: string, shown: string, hint: string): Choice => ({
    mode: mode,
    select: byId<HTMLSelectElement>(doc, select),
    shown: byId<HTMLInputElement>(doc, shown),
    hint: byId(doc, hint),
    key: undefined,
  });
  const choices = [
    choice(VALUE.debrid, ID.template, ID.templateShown, ID.templateHint),
    choice(VALUE.p2p, ID.templateP2p, ID.templateP2pShown, ID.templateP2pHint),
  ];

  function selected(): HTMLOptionElement | undefined {
    return server.options[server.selectedIndex];
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
    const option = selected();
    const conf = option ? confs.known(option.value, ofMode) : null;
    return option && option.value === VALUE.own && ownUrl() !== (conf ? conf.url : null) ? null : conf;
  }
  function renderHint(c: Choice): void {
    c.hint.textContent = c.select.options[c.select.selectedIndex]!.getAttribute(DATA.hint);
  }
  function p2p(): boolean {
    return mode() === VALUE.p2p;
  }
  // The one of the mode chosen; own config: none.
  function chosen(): Choice | null {
    return ownWatch() ? null : choices.find((c) => c.mode === mode())!;
  }
  // The template of the config of its mode ("none": not known yet), else the one for a new config; a mark (disabled)
  // only while it is the one.
  function renderTemplate(c: Choice): void {
    const conf = currentConf(c.mode);
    const shown = conf ? conf.tpl || VALUE.none : confs.templateForNew(c.mode);
    for (let i = 0; i < c.select.options.length; i++) {
      const option = c.select.options[i]!;
      if (isMark(option)) option.hidden = option.value !== shown;
    }
    // A missing tplNew assigns null, as a missing data-new did.
    c.select.value = shown!;
    c.shown.value = shown!;
    renderHint(c);
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
  // The host of the server in the summary of #asd; own server without an address of a host typed: the option's name.
  function renderServerName(option: HTMLOptionElement | undefined): void {
    if (!option) return;
    const own = option.value === VALUE.own;
    const host = hostOf(own ? ownUrl() : option.value);
    serverName.textContent = host !== "" ? host : option.text.replace(/…$/, "");
  }
  function creates(): boolean {
    const hasKey = trimmed(rdKey.value) + trimmed(tbKey.value) !== "";
    return !ownWatch() && !currentConf() && (hasKey || p2p());
  }
  function render(): void {
    const option = selected(),
      ownSource = ownWatch(),
      ownServer = !!option && option.value === VALUE.own,
      conf = currentConf();
    // Another config of the mode of a choice (or none): its template; the page came with the right one.
    for (const c of choices) {
      const ofMode = currentConf(c.mode);
      const key = ofMode ? option!.value + " " + ofMode.cfg : "";
      if (key !== c.key) {
        if (c.key !== undefined) renderTemplate(c);
        c.key = key;
      }
    }
    const modeP2p = p2p();
    renderServerName(option);
    byId(doc, ID.aioOwnBox).style.display = ownServer ? "" : "none";
    byId(doc, ID.tmdbBox).style.display = !option || option.getAttribute(DATA.tmdb) === "1" ? "none" : "";
    byId(doc, ID.tmdbNote).style.display = ownServer ? "none" : "";
    byId(doc, ID.confNone).hidden = !!conf;
    byId(doc, ID.confHave).hidden = !conf;
    if (conf) renderConf(conf);
    swapText(ID.jacredText, ownSource);
    const refuses = option ? option.getAttribute(DATA.jr403) : null;
    // P2P on a server with Magnetio (no data-p2ptz): Magnetio asks JacRed, not the server; it can't reach a home
    // address either.
    const magnetio = modeP2p && !(option && option.getAttribute(DATA.p2pTorznab) === "1");
    byId(doc, ID.jacred403).hidden = ownSource || magnetio || !refuses || refuses !== checkedValue(form, NAME.jacred);
    byId(doc, ID.jacredLan).hidden = ownSource || (ownServer && !magnetio);
    if (!lock.saving()) saveButton.textContent = creates() ? texts.saveAndCreate : texts.save;
  }

  server.addEventListener("change", render);
  ownUrlField.addEventListener("input", render);
  rdKey.addEventListener("input", render);
  tbKey.addEventListener("input", render);
  const watches = form.querySelectorAll<HTMLInputElement>("input[name=" + NAME.watch + "]");
  for (let i = 0; i < watches.length; i++) watches[i]!.addEventListener("change", render);
  const jacreds = form.querySelectorAll<HTMLInputElement>(JACRED_RADIOS);
  for (let i = 0; i < jacreds.length; i++) jacreds[i]!.addEventListener("change", render);
  render();
  for (const c of choices) c.select.addEventListener("change", () => renderHint(c));

  return {
    render: render,
    creates: creates,
    templateUnknown(): boolean {
      const c = chosen();
      return !!c && c.select.value === VALUE.none;
    },
    applySync(sync: Sync, reset: boolean): void {
      // Of a server of the choice only.
      for (let i = 0; i < server.options.length; i++)
        if (server.options[i]!.value === sync.base && sync.mode)
          confs.store(sync.base, sync.mode, sync.conf, sync.base === VALUE.own ? ownUrl() : null);
      // Of both modes, the template of the config (as the server found it): one of the other mode chosen is saved
      // already, not a reset to ask for there later; a failed reset keeps the choice of its mode for the retry.
      for (const c of choices) if (c.mode !== sync.mode || sync.ok || !reset) c.key = null;
    },
    // Of the mode chosen with a config there, another template (the other mode: its own config, never a reset).
    resetPending(): boolean {
      const c = chosen();
      return !!c && !!currentConf() && c.select.value !== c.shown.value;
    },
    // A mark shown: the reset is to the template for a new config of the mode (nothing posted).
    confirmReset(): boolean {
      const option = selected()!,
        address = option.value === VALUE.own ? ownUrl() : option.value,
        c = chosen() || choices[0]!;
      let picked = c.select.options[c.select.selectedIndex]!;
      if (isMark(picked))
        for (let i = 0; i < c.select.options.length; i++)
          if (c.select.options[i]!.value === confs.templateForNew(c.mode)) picked = c.select.options[i]!;
      const host = address.replace(/^[a-z]+:\/\//, "").replace(/[:\/].*$/, "");
      return confirm(texts.resetConfirm.replace("%s", host).replace("%s", picked.text));
    },
    rememberTemplate(): void {
      for (const c of choices) {
        // As the save: of P2P only another one than shown (else the default stays), of Debrid the one posted.
        const posted = !isMark(c.select.options[c.select.selectedIndex]);
        if (posted && (c.mode !== VALUE.p2p || c.select.value !== c.shown.value)) confs.storeTemplateForNew(c.mode, c.select.value);
        if (!currentConf(c.mode)) c.shown.value = c.select.value;
      }
    },
  };
}
