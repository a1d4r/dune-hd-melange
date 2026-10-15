// AIOStreams: the source, the server, its config, the mode and the template, and what of the page depends on them.

import type { Sync } from "./api";
import type { Confs, KnownConf } from "./confs";
import { byId, checkedValue, CLS, DATA, ID, isMark, isOwnSource, JACRED_RADIOS, NAME, type Page, query, VALUE } from "./dom";
import type { Lock } from "./lock";

export interface Aio {
  render(): void;
  // The config of the server after aio_sync; then render().
  applySync(sync: Sync, reset: boolean): void;
  // A save now would replace the config of the server with another template (or another mode).
  resetPending(): boolean;
  // The OK of the user to replace the config with the template.
  confirmReset(): boolean;
  // After a save: the template and the mode chosen are the ones for a new config.
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
  // The config shown, by "<server> <config>"; undefined: as the page came.
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
  // Of the chosen server; of own one only if it is of the address typed.
  function currentConf(): KnownConf | null {
    const option = selected();
    const conf = option ? confs.known(option.value) : null;
    return option && option.value === VALUE.own && ownUrl() !== (conf ? conf.url : null) ? null : conf;
  }
  function renderHint(): void {
    byId(doc, ID.templateHint).textContent = template.options[template.selectedIndex]!.getAttribute(DATA.hint);
  }
  function p2p(): boolean {
    return checkedValue(form, NAME.mode) === VALUE.p2p;
  }
  // The mode of a config as PHP found it (not the one for a new config: that is one for all servers); no config (or
  // none sent): the one for a new config.
  function modeOf(conf: KnownConf | null): string | null {
    return conf && conf.mode ? conf.mode : confs.modeForNew();
  }
  // The template of the config ("none": not known yet), else the one for a new config (P2P in that mode); the
  // choice shows it, of P2P the one for a new config of Debrid; a mark (disabled) only while it is the one. The mode:
  // that of the config.
  function renderTemplate(): void {
    const conf = currentConf();
    const shown = conf ? conf.tpl || VALUE.none : confs.modeForNew() === VALUE.p2p ? VALUE.p2p : confs.templateForNew();
    const choice = shown === VALUE.p2p ? confs.templateForNew() : shown;
    for (let i = 0; i < template.options.length; i++) {
      const option = template.options[i]!;
      if (isMark(option)) option.hidden = option.value !== choice;
    }
    // A missing tplNew assigns null, as a missing data-new did.
    template.value = choice!;
    templateShown.value = shown!;
    const mode = modeOf(conf);
    for (let i = 0; i < modes.length; i++) modes[i]!.checked = modes[i]!.value === mode;
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
      conf = currentConf();
    // Another config (or none): its template; the page came with the right one.
    const key = conf ? option!.value + " " + conf.cfg : "";
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
        if (server.options[i]!.value === sync.base) confs.store(sync.base, sync.conf, sync.base === VALUE.own ? ownUrl() : null);
      // The template of the config as the server found it; a failed reset keeps the choice for the retry.
      if (sync.ok || !reset) templateKey = null;
    },
    // Another mode than that of the config, or of Debrid another template.
    resetPending(): boolean {
      const conf = currentConf();
      if (isOwnSource(form) || !conf) return false;
      return checkedValue(form, NAME.mode) !== modeOf(conf) || (!p2p() && template.value !== templateShown.value);
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
      const mode = checkedValue(form, NAME.mode);
      if (mode) confs.storeModeForNew(mode);
      if (!currentConf()) templateShown.value = p2p() ? VALUE.p2p : template.value;
    },
  };
}
