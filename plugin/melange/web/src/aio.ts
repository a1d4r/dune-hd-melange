// AIOStreams: the source, the server, its config, the mode and the template, and what of the page depends on them.

import type { Sync } from "./api";
import type { Confs, KnownConf } from "./confs";
import { byId, checkedValue, CLS, DATA, ID, isMark, isOwnSource, JACRED_RADIOS, NAME, type Page, query, VALUE } from "./dom";
import type { Lock } from "./lock";

export interface Aio {
  render(): void;
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
    modes = form.querySelectorAll<HTMLInputElement>("input[name=" + NAME.mode + "]"),
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
  // The mode chosen (one for all servers: a server has a config of each).
  function mode(): string {
    return checkedValue(form, NAME.mode) || VALUE.debrid;
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
  function render(): void {
    const option = selected(),
      ownSource = isOwnSource(form),
      ownServer = !!option && option.value === VALUE.own,
      conf = currentConf(),
      debrid = currentConf(VALUE.debrid);
    // Another config of Debrid (or none): its template; the page came with the right one.
    const key = debrid ? option!.value + " " + debrid.cfg : "";
    if (key !== templateKey) {
      if (templateKey !== undefined) renderTemplate();
      templateKey = key;
    }
    byId(doc, ID.sourceMade).style.display = ownSource ? "none" : "";
    byId(doc, ID.sourceOwn).style.display = ownSource ? "" : "none";
    const modeP2p = p2p();
    byId(doc, ID.modeDebrid).style.display = modeP2p ? "none" : "";
    byId(doc, ID.modeP2p).style.display = modeP2p ? "" : "none";
    byId(doc, ID.aioOwnBox).style.display = ownServer ? "" : "none";
    byId(doc, ID.tmdbBox).style.display = !option || option.getAttribute(DATA.tmdb) === "1" ? "none" : "";
    byId(doc, ID.tmdbNote).style.display = ownServer ? "none" : "";
    byId(doc, ID.confNone).hidden = !!conf;
    byId(doc, ID.confHave).hidden = !conf;
    if (conf) renderConf(conf);
    swapText(ID.jacredHeading, ownSource);
    swapText(ID.jacredText, ownSource);
    const refuses = option ? option.getAttribute(DATA.jr403) : null;
    // P2P: Magnetio asks JacRed, not the server; it can't reach a home address either.
    byId(doc, ID.jacred403).hidden = ownSource || modeP2p || !refuses || refuses !== checkedValue(form, NAME.jacred);
    byId(doc, ID.tsOwn).hidden = !ownSource;
    byId(doc, ID.jacredLan).hidden = ownSource || (ownServer && !modeP2p);
    const hasKey = (rdKey.value + tbKey.value).replace(/\s+/g, "") !== "";
    if (!lock.saving()) saveButton.textContent = !ownSource && !conf && (hasKey || modeP2p) ? texts.saveAndCreate : texts.save;
  }

  server.addEventListener("change", render);
  ownUrlField.addEventListener("input", render);
  rdKey.addEventListener("input", render);
  tbKey.addEventListener("input", render);
  const sources = form.querySelectorAll<HTMLInputElement>("input[name=" + NAME.source + "]");
  for (let i = 0; i < sources.length; i++) sources[i]!.addEventListener("change", render);
  for (let i = 0; i < modes.length; i++) modes[i]!.addEventListener("change", render);
  const jacreds = form.querySelectorAll<HTMLInputElement>(JACRED_RADIOS);
  for (let i = 0; i < jacreds.length; i++) jacreds[i]!.addEventListener("change", render);
  render();
  template.addEventListener("change", renderHint);

  return {
    render: render,
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
      return !isOwnSource(form) && !p2p() && !!currentConf() && template.value !== templateShown.value;
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
