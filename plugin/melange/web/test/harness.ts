// The settings page of a fixture (the HTML PHP makes, test/fixtures) in happy-dom, the script started on it,
// fetch and the globals the script takes past the document replaced.

import { jest } from "bun:test";
import { readdirSync, readFileSync } from "fs";
import { Window } from "happy-dom";
import { type Init, readInit, start } from "../src/init";

const FIXTURE_DIR = import.meta.dir + "/fixtures/";

// Every page melange_cgi_test.sh --dump makes.
export const FIXTURES: string[] = readdirSync(FIXTURE_DIR)
  .filter((file) => file.endsWith(".html"))
  .map((file) => file.slice(0, -".html".length))
  .sort();

export function fixtureHtml(name: string): string {
  return readFileSync(FIXTURE_DIR + name + ".html", "utf8");
}

// The action of the form, settings?t=<token>: the same on every fixture.
export const TOKEN: string = (() => {
  const found = new Set(FIXTURES.map((name) => (/<form [^>]*action="(settings\?t=[0-9a-f]+)"/.exec(fixtureHtml(name)) || [])[1]));
  const only = [...found][0];
  if (found.size !== 1 || !only) throw new Error("fixtures: not one form action settings?t=<token>: " + [...found].join(", "));
  return only;
})();

// A request the script made.
export interface Call {
  method: string;
  url: string;
  body: string | null;
  contentType: string | null;
}

// An answer to a request: JSON or text (status 200 unless given), no connection, no answer until aborted,
// or one given later (a request in flight).
export type Reply =
  | { json: unknown; status?: number }
  | { text: string; status?: number }
  | { net: true }
  | { hang: true }
  | { wait: Promise<Reply> };

export const NET: Reply = { net: true };
export const HANG: Reply = { hang: true };
export const json = (body: unknown, status?: number): Reply => (status === undefined ? { json: body } : { json: body, status: status });

// A reply given later: resolve() answers the request.
export function later(): { reply: Reply; resolve: (reply: Reply) => void } {
  let resolve: (reply: Reply) => void = () => {};
  const wait = new Promise<Reply>((done) => (resolve = done));
  return { reply: { wait: wait }, resolve: (reply: Reply) => resolve(reply) };
}

// Only status and text() are what the script reads of a response.
function answer(reply: Reply, signal: AbortSignal | null | undefined): Promise<Response> {
  if ("wait" in reply) return reply.wait.then((next) => answer(next, signal));
  if ("net" in reply) return Promise.reject(new TypeError("Failed to fetch"));
  if ("hang" in reply)
    return new Promise((_resolve, reject) => {
      if (signal) signal.addEventListener("abort", () => reject(new DOMException("The operation was aborted.", "AbortError")));
    });
  const text = "json" in reply ? JSON.stringify(reply.json) : reply.text;
  const response = { status: reply.status === undefined ? 200 : reply.status, text: () => Promise.resolve(text) };
  return Promise.resolve(response as unknown as Response);
}

const saved: [string, PropertyDescriptor | undefined][] = [];

export function setGlobal(name: string, value: unknown): void {
  saved.push([name, Object.getOwnPropertyDescriptor(globalThis, name)]);
  Object.defineProperty(globalThis, name, { value: value, configurable: true, writable: true });
}

// A second page would take over fetch, location and confirm of the first one.
let opened = false;

// For afterEach of every test file.
export function cleanup(): void {
  opened = false;
  while (saved.length) {
    const [name, desc] = saved.pop()!;
    if (desc) Object.defineProperty(globalThis, name, desc);
    else delete (globalThis as Record<string, unknown>)[name];
  }
  if (jest.isFakeTimers()) jest.useRealTimers();
}

// All the promises of the script settled (they chain on microtasks only: the replies above).
export function flush(): Promise<void> {
  return new Promise((done) => setImmediate(done));
}

export interface Page {
  win: Window;
  doc: Document;
  form: HTMLFormElement;
  init: Init;
  calls: Call[];
  // Answers of the next requests, in order.
  replies: Reply[];
  // location.replace(), confirm(): calls and what confirm() answers.
  replaced: string[];
  confirms: string[];
  confirmAnswer: boolean;
  el<E extends HTMLElement = HTMLElement>(id: string): E;
  // A field by its name=.
  field<E extends HTMLElement = HTMLInputElement>(name: string): E;
  click(id: string): Promise<void>;
  // A value typed into a field by its id or name=: the event of a text or password field (input), else change.
  type(target: string | HTMLElement, value: string): void;
  choose(name: string, value: string): void;
}

export interface Options {
  // Before the script: no fetch, a broken #init…
  before?: (doc: Document) => void;
  // false: the script not started.
  start?: boolean;
}

// happy-dom 20.14.6: an option with "selected" after another one parsed gets the selectedness of the option at the
// index of the count of selected ones (HTMLSelectElement updateSelectedness, "selected.length >= 2"), e.g. #as
// "own" -> its second option. Fixed as a browser parses it: the last option with "selected".
function fixSelects(doc: Document): void {
  doc.querySelectorAll("select").forEach((select) => {
    let last = -1;
    for (let i = 0; i < select.options.length; i++) if (select.options[i]!.hasAttribute("selected")) last = i;
    if (last >= 0) select.selectedIndex = last;
  });
}

export function open(name: string, options: Options = {}): Page {
  if (opened) throw new Error("open: one page a test (cleanup in afterEach)");
  opened = true;
  const win = new Window({
    url: "http://192.168.1.10/plugins/melange/cgi-bin/" + TOKEN,
    settings: { navigation: { disableMainFrameNavigation: true, disableFallbackToSetURL: true } },
  });
  win.document.write(fixtureHtml(name));
  const doc = win.document as unknown as Document;
  fixSelects(doc);
  const init = readInit(doc);
  if (!init) throw new Error("fixture " + name + ": no #init");
  const page: Page = {
    win: win,
    doc: doc,
    form: doc.forms[0]!,
    init: init,
    calls: [],
    replies: [],
    replaced: [],
    confirms: [],
    confirmAnswer: false,
    el: <E extends HTMLElement>(id: string) => {
      const el = doc.getElementById(id);
      if (!el) throw new Error("no #" + id);
      return el as E;
    },
    field: <E extends HTMLElement>(fieldName: string) => {
      const el = doc.querySelector('[name="' + fieldName + '"]');
      if (!el) throw new Error("no name=" + fieldName);
      return el as E;
    },
    click: (id: string) => {
      page.el(id).click();
      return flush();
    },
    type: (target: string | HTMLElement, value: string) => {
      const el = (typeof target !== "string" ? target : doc.getElementById(target) ? page.el(target) : page.field(target)) as HTMLInputElement;
      el.value = value;
      const text = el.tagName === "INPUT" && (el.type === "text" || el.type === "password");
      el.dispatchEvent(new win.Event(text ? "input" : "change", { bubbles: true }) as unknown as Event);
    },
    choose: (radioName: string, value: string) => {
      const group = doc.querySelectorAll<HTMLInputElement>('input[name="' + radioName + '"]');
      const radio = doc.querySelector<HTMLInputElement>('input[name="' + radioName + '"][value="' + value + '"]')!;
      // happy-dom 20.14.6 unchecks the others of the group without clearing its query cache: a cached
      // "input[name=…]:checked" keeps the old one. Unchecked here one by one, as a browser has them.
      group.forEach((other) => {
        if (other !== radio) other.checked = false;
      });
      radio.checked = true;
      radio.dispatchEvent(new win.Event("change", { bubbles: true }) as unknown as Event);
    },
  };
  setGlobal("fetch", (url: string, request: RequestInit = {}) => {
    const headers = (request.headers || {}) as Record<string, string>;
    page.calls.push({
      method: request.method || "GET",
      url: url,
      body: request.body === undefined || request.body === null ? null : String(request.body),
      contentType: headers["Content-Type"] || null,
    });
    const reply = page.replies.shift();
    if (!reply) return Promise.reject(new Error("no reply for " + url));
    return answer(reply, request.signal);
  });
  setGlobal("location", { replace: (url: string) => page.replaced.push(url) });
  setGlobal("confirm", (text: string) => {
    page.confirms.push(text);
    return page.confirmAnswer;
  });
  // No clipboard API unless a test gives it.
  setGlobal("navigator", {});
  setGlobal("window", { isSecureContext: false });
  if (options.before) options.before(doc);
  if (options.start !== false) start(doc);
  return page;
}

// The lines of a result container: [class, text] each.
export function lines(el: HTMLElement): [string, string][] {
  const out: [string, string][] = [];
  el.querySelectorAll("p").forEach((line) => out.push([line.className, line.textContent || ""]));
  return out;
}

export const shown = (el: HTMLElement): boolean => el.style.display !== "none" && !el.hidden;
