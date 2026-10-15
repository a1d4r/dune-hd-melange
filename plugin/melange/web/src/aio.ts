// AIOStreams: the answer of "Where to get films" (the mode of a config melange makes, or own config), the server,
// its config and the template, and what of the page depends on them the CSS can't show.

import type { Sync } from "./api";
import type { Confs, KnownConf } from "./confs";
import { byId, checkedValue, CLS, DATA, ID, isMark, JACRED_RADIOS, NAME, type Page, query, VALUE, watchOf } from "./dom";
import type { Lock } from "./lock";

export interface Aio {
  render(): void;
  // The chosen server has a config of the mode chosen (not for own config).
  hasConf(): boolean;
  // "Save" creates a config: "Save and create the config".
  creates(): boolean;
  // The config of the server (of the mode synced) after aio_sync; then render().
  applySync(sync: Sync, reset: boolean): void;
  // A save now would replace the config of Debrid of the server with another template.
  resetPending(): boolean;
  // The OK of the user to replace the config of the mode chosen with the template.
  confirmReset(): boolean;
  // After a save: the template chosen is the one for a new config.
  rememberTemplate(): void;
}

export function setupAio(page: Page, confs: Confs, lock: Lock): Aio {
  const { doc, form, texts } = page;
  const server = byId<HTMLSelectElement>(doc, ID.aioServer),
    ownUrlField = byId<HTMLInputElement>(doc, ID.aioOwnUrl),
    rdKey = byId<HTMLInputElement>(doc, ID.rdKey),
    tbKey = byId<HTMLInputElement>(doc, ID.tbKey),
    template = byId<HTMLSelectElement>(doc, ID.template),
    templateShown = byId<HTMLInputElement>(doc, ID.templateShown),
    serverName = byId(doc, ID.aioServerName),
    saveButton = query<HTMLButtonElement>(form, "." + CLS.save);
  // The config of Debrid the template choice shows, by "<server> <config>"; undefined: as the page came.
  let templateKey: string | null | undefined;

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
  function renderHint(): void {
    byId(doc, ID.templateHint).textContent = template.options[template.selectedIndex]!.getAttribute(DATA.hint);
  }
  function p2p(): boolean {
    return mode() === VALUE.p2p;
  }
  // The template of the config of Debrid ("none": not known yet), else the one for a new config; a mark (disabled)
  // only while it is the one.
  function renderTemplate(): void {
    const conf = currentConf(VALUE.debrid);
    const shown = conf ? conf.tpl || VALUE.none : confs.templateForNew();
    for (let i = 0; i < template.options.length; i++) {
      const option = template.options[i]!;
      if (isMark(option)) option.hidden = option.value !== shown;
    }
    // A missing tplNew assigns null, as a missing data-new did.
    template.value = shown!;
    templateShown.value = shown!;
    renderHint();
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
    const host = /^[a-z][a-z0-9+.-]*:\/\/(?:[^\/?#@]*@)?(\[[^\]\/]*\]|[^\/:?#]+)/i.exec(own ? ownUrl() : option.value);
    serverName.textContent = host ? host[1]! : option.text.replace(/…$/, "");
  }
  function creates(): boolean {
    const hasKey = (rdKey.value + tbKey.value).replace(/\s+/g, "") !== "";
    return !ownWatch() && !currentConf() && (hasKey || p2p());
  }
  function render(): void {
    const option = selected(),
      ownSource = ownWatch(),
      ownServer = !!option && option.value === VALUE.own,
      conf = currentConf(),
      debrid = currentConf(VALUE.debrid);
    // Another config of Debrid (or none): its template; the page came with the right one.
    const key = debrid ? option!.value + " " + debrid.cfg : "";
    if (key !== templateKey) {
      if (templateKey !== undefined) renderTemplate();
      templateKey = key;
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
  template.addEventListener("change", renderHint);

  return {
    render: render,
    hasConf: () => !!currentConf(),
    creates: creates,
    applySync(sync: Sync, reset: boolean): void {
      // Of a server of the choice only.
      for (let i = 0; i < server.options.length; i++)
        if (server.options[i]!.value === sync.base && sync.mode)
          confs.store(sync.base, sync.mode, sync.conf, sync.base === VALUE.own ? ownUrl() : null);
      // The template of the config as the server found it; a failed reset keeps the choice for the retry.
      if (sync.ok || !reset) templateKey = null;
    },
    // Of Debrid with a config there, another template (another mode: its own config, never a reset).
    resetPending(): boolean {
      return !ownWatch() && !p2p() && !!currentConf() && template.value !== templateShown.value;
    },
    // P2P: its name; a mark shown: the reset is to the template for a new config (nothing posted).
    confirmReset(): boolean {
      const option = selected()!,
        address = option.value === VALUE.own ? ownUrl() : option.value;
      let chosen = template.options[template.selectedIndex]!;
      if (isMark(chosen))
        for (let i = 0; i < template.options.length; i++)
          if (template.options[i]!.value === confs.templateForNew()) chosen = template.options[i]!;
      const host = address.replace(/^[a-z]+:\/\//, "").replace(/[:\/].*$/, "");
      return confirm(texts.resetConfirm.replace("%s", host).replace("%s", p2p() ? texts.templateP2p : chosen.text));
    },
    rememberTemplate(): void {
      if (!isMark(template.options[template.selectedIndex])) confs.storeTemplateForNew(template.value);
      if (!currentConf(VALUE.debrid)) templateShown.value = template.value;
    },
  };
}
