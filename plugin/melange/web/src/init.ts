// The start of the script: the JSON of #init, then the parts of the page.
// No #init or a broken one: nothing at all, the page stays as without JS.

import { setupAio } from "./aio";
import { createApi, isObject } from "./api";
import { setupBackup } from "./backup";
import { setupBar } from "./bar";
import { setupChecks } from "./checks";
import { setupClipboard } from "./clipboard";
import { createConfs } from "./confs";
import { setupDebrid } from "./debrid";
import { ID, type Page } from "./dom";
import { setupHome } from "./home";
import type { Texts } from "./i18n";
import { setupJacredNote, setupJacredSpeed } from "./jacred";
import { createLock } from "./lock";
import { setupMarks } from "./marks";
import { type Profiles, setupProfiles } from "./profiles";
import { setupRows } from "./rows";
import { setupSave } from "./save";
import { bindEyes, maskSecrets } from "./secrets";
import { setupServerChecks, setupServers } from "./servers";

// Written by aio_cgi_js(). i18n and jacredDefault: always written, checked by isInit() (else #init is broken);
// confs: {<mode>: {<value of a server option>: {cfg, login, pass, tpl, url?}}}, tplNew: the template for a new config;
// both checked by createConfs(); profiles: the source profiles (parseProfiles).
export interface Init {
  i18n: Texts;
  jacredDefault: string;
  tplNew?: unknown;
  confs?: unknown;
  profiles?: unknown;
}

function isInit(json: unknown): json is Init {
  return isObject(json) && isObject(json.i18n) && typeof json.jacredDefault === "string";
}

export function readInit(doc: Document): Init | null {
  const el = doc.getElementById(ID.init);
  if (!el) return null;
  let json: unknown = null;
  try {
    json = JSON.parse(el.textContent || "");
  } catch (e) {
    return null;
  }
  return isInit(json) ? json : null;
}

function setupForm(page: Page, init: Init): void {
  const servers = setupServers(page);
  const lock = createLock(page);
  const aio = setupAio(page, createConfs(init.confs, init.tplNew), lock);
  const debrid = setupDebrid(page);
  setupClipboard(page);
  setupJacredNote(page, init.jacredDefault);
  const home = setupHome(page, aio);
  const bar = setupBar(page, () => aio.creates() || aio.templateUnknown(), lock.saving);
  // The source profiles: by the script only, with fetch.
  let profiles: Profiles | null = null;
  // The main screen and the bar after any change: the handlers of the form, after those of its fields.
  const changed = () => {
    home.render();
    bar.render();
    if (profiles) profiles.render();
  };
  page.form.addEventListener("input", changed);
  page.form.addEventListener("change", changed);
  // No fetch: the checks are not there, the form posts as without JS.
  if (typeof fetch !== "function") return;
  const api = createApi(page.form.getAttribute("action"), lock);
  setupChecks(page, api, lock);
  setupServerChecks(page, api, lock);
  setupJacredSpeed(page, api, lock);
  profiles = setupProfiles(page, api, lock, bar, init.profiles, changed);
  const shown = profiles;
  setupSave(page, api, lock, aio, setupMarks(page, servers, debrid), bar, home, changed, () => shown.refresh(), shown);
  setupRows(page, api, changed);
  setupBackup(page, api, lock);
}

// saved=1 of the address after a save, with rows=1 when the rows changed, kept=1 when the source changed since the page
// came, imported=1 or 2 after an import (the lines of PHP)
// dropped, the rest kept as it is: a reload does not show them again. No history API: nothing.
function dropSaved(): void {
  try {
    const params = location.search.slice(1).split("&");
    const kept = params.filter((param) => param !== "saved=1" && param !== "rows=1" && param !== "kept=1" && param !== "imported=1" && param !== "imported=2");
    if (kept.length === params.length || typeof history.replaceState !== "function") return;
    history.replaceState(history.state, "", location.pathname + (kept.length ? "?" + kept.join("&") : "") + location.hash);
  } catch (e) {}
}

// Once per document: a second call on the same one binds every handler twice. No state is kept between documents.
export function start(doc: Document): void {
  const init = readInit(doc);
  if (!init) return;
  dropSaved();
  const form = doc.forms[0];
  if (form) setupForm({ doc: doc, form: form, texts: init.i18n }, init);
  maskSecrets(doc);
  bindEyes(doc);
}
