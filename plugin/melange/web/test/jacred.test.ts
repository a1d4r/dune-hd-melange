// §2.5 JacRed: "Check" of own one (3 probes 2 s apart, the stop on the format error, without an address) and
// "Compare speed" (every JacRed in turn, the line of each in its card).

import { afterEach, beforeEach, describe, expect, jest, test } from "bun:test";
import { CLS, ID } from "../src/dom";
import { cleanup, flush, json, lines, open, type Page, TOKEN } from "./harness";

beforeEach(() => jest.useFakeTimers());
afterEach(cleanup);

const URL = TOKEN + "&a=jr_speed&ajax=1";
const OWN = "b=&url=http%3A%2F%2F192.168.1.10%3A9117&key=testkey01";
const BUILTINS = ["jacred.kompotik.com", "jacred.stream", "jr.maxvol.pro", "jac.stull.xyz", "jac.red"];

const speed = (n: number, ms: number) => json({ ok: true, msg: "", n: n, ms: ms });
const fail = (msg: string) => json({ ok: false, msg: msg, n: 0, ms: 0 });
// The answer of a wrong address, key or id: its text whatever it is, the mark bad: "format".
const badFormat = (msg: string) => json({ ok: false, msg: msg, n: 0, ms: 0, bad: "format" });
const bodies = (page: Page) => page.calls.map((call) => (call.method === "POST" && call.url === URL ? call.body : "?" + call.url));
const busy = (page: Page) => [page.el<HTMLButtonElement>(ID.jacredCheck).disabled, page.el<HTMLButtonElement>(ID.jacredCompare).disabled];
// aria-busy of #jcr and #jrsr: a screen reader waits for the result, not each step.
const ariaBusy = (page: Page) => [ID.jacredResult, ID.jacredSpeed].map((id) => page.el(id).getAttribute("aria-busy"));

// The pause before the next probe passed, its answer in.
async function pause(ms = 2000): Promise<void> {
  jest.advanceTimersByTime(ms);
  await flush();
}

describe("Check", () => {
  test("own one: 3 probes 2 s apart, n/3 between, the sum line; both buttons disabled meanwhile", async () => {
    const page = open("filled");
    const t = page.init.i18n;
    page.replies.push(speed(10, 1200), speed(12, 1500), speed(11, 1800));
    await page.click(ID.jacredCheck);
    expect(bodies(page)).toEqual([OWN + "&q=0"]);
    expect(lines(page.el(ID.jacredResult))).toEqual([["hint", t.checking + " 1/3"]]);
    expect(busy(page)).toEqual([true, true]);
    expect(ariaBusy(page)).toEqual(["true", null]);
    await pause(1999);
    expect(page.calls.length).toBe(1);
    await pause(1);
    expect(bodies(page)).toEqual([OWN + "&q=0", OWN + "&q=1"]);
    expect(lines(page.el(ID.jacredResult))).toEqual([["hint", t.checking + " 2/3"]]);
    await pause();
    expect(bodies(page)).toEqual([OWN + "&q=0", OWN + "&q=1", OWN + "&q=2"]);
    expect(lines(page.el(ID.jacredResult))).toEqual([
      ["ok", "192.168.1.10 · " + t.yes + " · 1.5 " + t.seconds + " · " + t.releases + ": 10 / 12 / 11"],
    ]);
    expect(busy(page)).toEqual([false, false]);
    expect(ariaBusy(page)).toEqual([null, null]);
  });

  test("own one whatever is chosen, and a 2nd click while it goes does nothing", async () => {
    const page = open("filled");
    page.choose("jacred", "jac.red");
    page.replies.push(speed(1, 100), speed(1, 100), speed(1, 100));
    await page.click(ID.jacredCheck);
    await page.click(ID.jacredCheck);
    await page.click(ID.jacredCompare);
    expect(bodies(page)).toEqual([OWN + "&q=0"]);
    await pause();
    await pause();
    expect(bodies(page)).toEqual([OWN + "&q=0", OWN + "&q=1", OWN + "&q=2"]);
  });

  test("some probes failed: available (k/3), the time of the good ones, — for the failed; not JSON is a failed probe", async () => {
    const page = open("filled");
    const t = page.init.i18n;
    page.replies.push(fail("Timeout"), { text: "oops", status: 500 }, speed(7, 2500));
    await page.click(ID.jacredCheck);
    await pause();
    await pause();
    expect(page.calls.length).toBe(3);
    expect(lines(page.el(ID.jacredResult))).toEqual([
      ["ok", "192.168.1.10 · " + t.yes + " (1/3) · 2.5 " + t.seconds + " · " + t.releases + ": — / — / 7"],
    ]);
  });

  test("all failed: not available with the last error, err", async () => {
    const page = open("filled");
    const t = page.init.i18n;
    page.replies.push(fail("Timeout"), fail("Timeout"), fail("HTTP 502"));
    await page.click(ID.jacredCheck);
    await pause();
    await pause();
    expect(lines(page.el(ID.jacredResult))).toEqual([
      ["err", "192.168.1.10 · " + t.no + ": HTTP 502 · — · " + t.releases + ": — / — / —"],
    ]);
  });

  test("own one: its address and key, the host as its name", async () => {
    const page = open("filled");
    const t = page.init.i18n;
    page.replies.push(speed(5, 1000), speed(5, 1000), speed(5, 1000));
    await page.click(ID.jacredCheck);
    await pause();
    await pause();
    expect(bodies(page)).toEqual([OWN + "&q=0", OWN + "&q=1", OWN + "&q=2"]);
    expect(lines(page.el(ID.jacredResult))).toEqual([
      ["ok", "192.168.1.10 · " + t.yes + " · 1.0 " + t.seconds + " · " + t.releases + ": 5 / 5 / 5"],
    ]);
  });

  test("own one without an address: the format error, no request", async () => {
    const page = open("empty");
    page.type(ID.jacredOwnUrl, "   ");
    await page.click(ID.jacredCheck);
    expect(page.calls).toEqual([]);
    expect(lines(page.el(ID.jacredResult))).toEqual([["err", page.init.i18n.ownJacred + ": " + page.init.i18n.jacredFormat]]);
    expect(busy(page)).toEqual([false, false]);
  });

  test("bad: format in an answer: stop after the first probe, the format error of own JacRed", async () => {
    const page = open("filled");
    page.replies.push(badFormat("any text"));
    await page.click(ID.jacredCheck);
    await pause();
    await pause();
    expect(page.calls.length).toBe(1);
    expect(lines(page.el(ID.jacredResult))).toEqual([["err", page.init.i18n.ownJacred + ": " + page.init.i18n.jacredFormat]]);
    expect(busy(page)).toEqual([false, false]);
  });

  test("the text of the format error without bad: a failed probe, no stop (no compare by text)", async () => {
    const page = open("filled");
    const t = page.init.i18n;
    page.replies.push(fail(t.jacredFormat), fail(t.jacredFormat), fail("HTTP 502"));
    await page.click(ID.jacredCheck);
    await pause();
    await pause();
    expect(page.calls.length).toBe(3);
    expect(lines(page.el(ID.jacredResult))).toEqual([
      ["err", "192.168.1.10 · " + t.no + ": HTTP 502 · — · " + t.releases + ": — / — / —"],
    ]);
  });

  test("a lone surrogate in a field of own one: nothing sent or locked, the broken character said; fixed, it goes", async () => {
    const page = open("filled");
    page.type(ID.jacredOwnKey, "key\udc00");
    await page.click(ID.jacredCheck);
    expect(page.calls).toEqual([]);
    expect(lines(page.el(ID.jacredResult))).toEqual([["err", page.init.i18n.badChars]]);
    expect([busy(page), ariaBusy(page)]).toEqual([[false, false], [null, null]]);
    page.type(ID.jacredOwnKey, "testkey01");
    page.replies.push(speed(1, 100));
    await page.click(ID.jacredCheck);
    expect(bodies(page)).toEqual([OWN + "&q=0"]);
  });
});

describe("Compare speed", () => {
  // The line in the card of every JacRed (data-jr), own one last.
  const cards = (page: Page) => Array.from(page.form.querySelectorAll("." + CLS.jacredSpeed), (card) => card.textContent);
  const sec = (page: Page, s: string) => s + " " + page.init.i18n.seconds;

  test("every built-in and own one in turn, 3 probes each 2 s apart, the time and releases in the card of each", async () => {
    const page = open("filled");
    const t = page.init.i18n;
    const names: string[] = [...BUILTINS, "192.168.1.10"];
    for (let i = 0; i < names.length; i++) page.replies.push(speed(i, 1000), speed(i, 1000), i === 2 ? fail("Timeout") : speed(i, 4000));
    await page.click(ID.jacredCompare);
    expect(busy(page)).toEqual([true, true]);
    expect(ariaBusy(page)).toEqual([null, "true"]);
    expect(cards(page)).toEqual(["1/3", "…", "…", "…", "…", "…"]);
    // 2 pauses a JacRed, the next one right after the last probe.
    for (let i = 0; i < 2 * names.length; i++) await pause();
    const targets = [...BUILTINS.map((id) => "b=" + id), OWN];
    expect(bodies(page)).toEqual(targets.flatMap((target) => [0, 1, 2].map((q) => target + "&q=" + q)));
    // Russian: a decimal comma; the releases of the probes answered summed.
    expect(cards(page)).toEqual([
      sec(page, "2,0") + " · 0",
      sec(page, "2,0") + " · 3",
      sec(page, "1,0") + " · 4 (2/3)",
      sec(page, "2,0") + " · 9",
      sec(page, "2,0") + " · 12",
      sec(page, "2,0") + " · 15",
    ]);
    // The failed probe of one: its error under the button.
    expect(lines(page.el(ID.jacredSpeed))).toEqual([["err", names[2] + ": Timeout"]]);
    expect(busy(page)).toEqual([false, false]);
    expect(ariaBusy(page)).toEqual([null, null]);
    expect(t.noAnswer).not.toBe("");
  });

  test("none of the probes answered: no answer in the card, the last error under the button", async () => {
    const page = open("empty");
    for (let i = 0; i < 15; i++) page.replies.push(i < 3 ? fail(i < 2 ? "Timeout" : "HTTP 502") : speed(1, 600));
    await page.click(ID.jacredCompare);
    for (let i = 0; i < 10; i++) await pause();
    expect(cards(page)).toEqual([page.init.i18n.noAnswer, ...Array(4).fill(sec(page, "0,6") + " · 3"), ""]);
    expect(lines(page.el(ID.jacredSpeed))).toEqual([["err", BUILTINS[0] + ": HTTP 502"]]);
  });

  test("the cards are labels of the radios: a click on one chooses that JacRed while it goes", async () => {
    const page = open("filled");
    for (let i = 0; i < 18; i++) page.replies.push(speed(1, 1000));
    await page.click(ID.jacredCompare);
    (page.form.querySelector("." + CLS.jacredSpeed + "[data-jr='jac.red']")!.closest("label") as HTMLElement).click();
    expect(page.el<HTMLInputElement>("jrb5").checked).toBe(true);
    for (let i = 0; i < 12; i++) await pause();
    expect(page.calls.length).toBe(18);
  });

  test("a new Compare speed: the old lines go (own one's too, when its address is gone)", async () => {
    const page = open("filled");
    for (let i = 0; i < 33; i++) page.replies.push(speed(1, 1000));
    await page.click(ID.jacredCompare);
    for (let i = 0; i < 12; i++) await pause();
    expect(cards(page)[5]).toBe(sec(page, "1,0") + " · 3");
    page.type(ID.jacredOwnUrl, "");
    await page.click(ID.jacredCompare);
    expect(cards(page)).toEqual(["1/3", "…", "…", "…", "…", ""]);
  });

  test("own one with a wrong address (bad: format): no answer, the format error, no more probes of it", async () => {
    const page = open("filled");
    for (let i = 0; i < 15; i++) page.replies.push(speed(1, 1000));
    page.replies.push(badFormat("any"));
    await page.click(ID.jacredCompare);
    for (let i = 0; i < 12; i++) await pause();
    expect(page.calls.length).toBe(16);
    expect(cards(page)[5]).toBe(page.init.i18n.noAnswer);
    expect(lines(page.el(ID.jacredSpeed))).toEqual([["err", page.init.i18n.ownJacred + ": " + page.init.i18n.jacredFormat]]);
    expect(busy(page)).toEqual([false, false]);
  });

  test("no own address: the built-in ones only; bad: format of a built-in one does not stop it", async () => {
    const page = open("empty");
    for (let i = 0; i < 15; i++) page.replies.push(i < 3 ? badFormat(page.init.i18n.jacredFormat) : speed(1, 1000));
    await page.click(ID.jacredCompare);
    for (let i = 0; i < 10; i++) await pause();
    expect(page.calls.length).toBe(15);
    expect(cards(page)).toEqual([page.init.i18n.noAnswer, ...Array(4).fill(sec(page, "1,0") + " · 3"), ""]);
  });

  test("a lone surrogate in the address of own one: nothing sent or locked, the broken character said", async () => {
    const page = open("filled");
    page.type(ID.jacredOwnUrl, "http://\ud800jacred");
    await page.click(ID.jacredCompare);
    expect(page.calls).toEqual([]);
    expect(lines(page.el(ID.jacredSpeed))).toEqual([["err", page.init.i18n.badChars]]);
    expect(cards(page)).toEqual(["", "", "", "", "", ""]);
    expect([busy(page), ariaBusy(page)]).toEqual([[false, false], [null, null]]);
  });
});
