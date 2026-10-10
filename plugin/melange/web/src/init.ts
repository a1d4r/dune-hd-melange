// The start of the script: the JSON of #init, then the parts of the page.
// No #init or a broken one: nothing at all, the page stays as without JS.

import { setupAdvanced } from "./advanced";
import { setupAio } from "./aio";
import { createApi, isObject } from "./api";
import { setupChecks } from "./checks";
import { setupClipboard } from "./clipboard";
import { createConfs } from "./confs";
import { ID, type Page } from "./dom";
import type { Texts } from "./i18n";
import { setupJacredNote, setupJacredSpeed } from "./jacred";
import { createLock } from "./lock";
import { setupMarks } from "./marks";
import { setupSave } from "./save";
import { bindEyes, maskSecrets } from "./secrets";
import { setupServerChecks, setupServers } from "./servers";

// Written by aio_cgi_js(). i18n and jacredDefault: always written, checked by isInit() (else #init is broken);
// confs: {<value of a server option>: {cfg, login, pass, tpl, url?}}, tplNew: the template for a new config; both
// checked by createConfs().
export interface Init {
  i18n: Texts;
  jacredDefault: string;
  tplNew?: unknown;
  confs?: unknown;
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
  const servers = setupServers(page, setupAdvanced(page));
  const lock = createLock(page);
  const aio = setupAio(page, createConfs(init.confs, init.tplNew), lock);
  setupClipboard(page);
  setupJacredNote(page, init.jacredDefault);
  // No fetch: the checks are not there, the form posts as without JS.
  if (typeof fetch !== "function") return;
  const api = createApi(page.form.getAttribute("action"), lock);
  setupChecks(page, api, lock);
  setupServerChecks(page, api, lock);
  setupJacredSpeed(page, api, lock);
  setupSave(page, api, lock, aio, setupMarks(page, servers));
}

// saved=1 of the address after a save (the "Saved" line of PHP) dropped, the rest kept as it is: a reload does not
// show it again. No history API: nothing.
function dropSaved(): void {
  try {
    const params = location.search.slice(1).split("&");
    const kept = params.filter((param) => param !== "saved=1");
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
