// AIOStreams: the source, the server, its config and the template, and what of the page depends on them.

import type { Sync } from "./api";
import type { Confs, KnownConf } from "./confs";
import { byId, CLS, DATA, ID, isMark, isOwnSource, NAME, type Page, query, VALUE } from "./dom";
import type { Lock } from "./lock";

export interface Aio {
  render(): void;
  // The config of the server after aio_sync; then render().
  applySync(sync: Sync, reset: boolean): void;
  // A save now would replace the config of the server with another template.
  resetPending(): boolean;
  // The OK of the user to replace the config with the template.
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
  // The template of the config ("none": not known yet), else the one for a new config.
  // The choice shows it; a mark (disabled) only while it is the one.
  function renderTemplate(): void {
    const conf = currentConf();
    const shown = conf ? conf.tpl || VALUE.none : confs.templateForNew();
    for (let i = 0; i < template.options.length; i++) {
      const option = template.options[i]!;
      if (isMark(option)) option.hidden = option.value !== shown;
    }
    // A missing tplNew assigns null, as a missing data-new did.
    template.value = shown!;
    byId<HTMLInputElement>(doc, ID.templateShown).value = shown!;
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
    byId(doc, ID.aioOwnBox).style.display = ownServer ? "" : "none";
    byId(doc, ID.tmdbBox).style.display = !option || option.getAttribute(DATA.tmdb) === "1" ? "none" : "";
    byId(doc, ID.tmdbNote).style.display = ownServer ? "none" : "";
    byId(doc, ID.confNone).hidden = !!conf;
    byId(doc, ID.confHave).hidden = !conf;
    if (conf) renderConf(conf);
    swapText(ID.jacredHeading, ownSource);
    swapText(ID.jacredText, ownSource);
    byId(doc, ID.jacred403).hidden = ownSource || !option || option.getAttribute(DATA.jr403) !== "1";
    byId(doc, ID.tsOwn).hidden = !ownSource;
    byId(doc, ID.jacredLan).hidden = ownSource || ownServer;
    const hasKey = (rdKey.value + tbKey.value).replace(/\s+/g, "") !== "";
    if (!lock.saving()) saveButton.textContent = !ownSource && !conf && hasKey ? texts.saveAndCreate : texts.save;
  }

  server.addEventListener("change", render);
  ownUrlField.addEventListener("input", render);
  rdKey.addEventListener("input", render);
  tbKey.addEventListener("input", render);
  const sources = form.querySelectorAll<HTMLInputElement>("input[name=" + NAME.source + "]");
  for (let i = 0; i < sources.length; i++) sources[i]!.addEventListener("change", render);
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
    resetPending(): boolean {
      return !isOwnSource(form) && !!currentConf() && template.value !== byId<HTMLInputElement>(doc, ID.templateShown).value;
    },
    // A mark shown: the reset is to the template for a new config (nothing posted).
    confirmReset(): boolean {
      const option = selected()!,
        address = option.value === VALUE.own ? ownUrl() : option.value;
      let chosen = template.options[template.selectedIndex]!;
      if (isMark(chosen))
        for (let i = 0; i < template.options.length; i++)
          if (template.options[i]!.value === confs.templateForNew()) chosen = template.options[i]!;
      const host = address.replace(/^[a-z]+:\/\//, "").replace(/[:\/].*$/, "");
      return confirm(texts.resetConfirm.replace("%s", host).replace("%s", chosen.text));
    },
    rememberTemplate(): void {
      if (!isMark(template.options[template.selectedIndex])) confs.storeTemplateForNew(template.value);
      if (!currentConf()) byId<HTMLInputElement>(doc, ID.templateShown).value = template.value;
    },
  };
}
