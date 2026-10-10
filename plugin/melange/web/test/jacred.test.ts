// §2.5 JacRed: "Check" (3 probes 2 s apart, the stop on the format error, own one without an address) and
// "Compare all" (every JacRed in turn, a table).

import { afterEach, beforeEach, describe, expect, jest, test } from "bun:test";
import { CLS, ID } from "../src/dom";
import { cleanup, flush, json, lines, open, type Page, TOKEN } from "./harness";

beforeEach(() => jest.useFakeTimers());
afterEach(cleanup);

const URL = TOKEN + "&a=jr_speed&ajax=1";
const OWN = "b=&url=http%3A%2F%2F192.168.1.10%3A9117&key=testkey01";
const BUILTINS = ["jacred.stream", "jr.maxvol.pro", "jac.stull.xyz", "jac.red"];

const speed = (n: number, ms: number) => json({ ok: true, msg: "", n: n, ms: ms });
const fail = (msg: string) => json({ ok: false, msg: msg, n: 0, ms: 0 });
// The answer of a wrong address, key or id: its text whatever it is, the mark bad: "format".
const badFormat = (msg: string) => json({ ok: false, msg: msg, n: 0, ms: 0, bad: "format" });
const bodies = (page: Page) => page.calls.map((call) => (call.method === "POST" && call.url === URL ? call.body : "?" + call.url));
const busy = (page: Page) => [page.el<HTMLButtonElement>(ID.jacredCheck).disabled, page.el<HTMLButtonElement>(ID.jacredCompare).disabled];
// aria-busy of #jcr and #jrsr: a screen reader waits for the result, not each step.
const ariaBusy = (page: Page) => [ID.jacredResult, ID.jacredTable].map((id) => page.el(id).getAttribute("aria-busy"));

// The pause before the next probe passed, its answer in.
async function pause(ms = 2000): Promise<void> {
  jest.advanceTimersByTime(ms);
  await flush();
}

describe("Check", () => {
  test("a built-in one: 3 probes 2 s apart, n/3 between, the sum line; both buttons disabled meanwhile", async () => {
    const page = open("empty");
    const t = page.init.i18n;
    page.replies.push(speed(10, 1200), speed(12, 1500), speed(11, 1800));
    await page.click(ID.jacredCheck);
    expect(bodies(page)).toEqual(["b=jacred.stream&q=0"]);
    expect(lines(page.el(ID.jacredResult))).toEqual([["hint", t.checking + " 1/3"]]);
    expect(busy(page)).toEqual([true, true]);
    expect(ariaBusy(page)).toEqual(["true", null]);
    await pause(1999);
    expect(page.calls.length).toBe(1);
    await pause(1);
    expect(bodies(page)).toEqual(["b=jacred.stream&q=0", "b=jacred.stream&q=1"]);
    expect(lines(page.el(ID.jacredResult))).toEqual([["hint", t.checking + " 2/3"]]);
    await pause();
    expect(bodies(page)).toEqual(["b=jacred.stream&q=0", "b=jacred.stream&q=1", "b=jacred.stream&q=2"]);
    expect(lines(page.el(ID.jacredResult))).toEqual([
      ["ok", "jacred.stream · " + t.yes + " · 1.5 " + t.seconds + " · " + t.releases + ": 10 / 12 / 11"],
    ]);
    expect(busy(page)).toEqual([false, false]);
    expect(ariaBusy(page)).toEqual([null, null]);
  });

  test("the chosen one: another built-in, and a 2nd click while it goes does nothing", async () => {
    const page = open("empty");
    page.choose("jacred", "jac.red");
    page.replies.push(speed(1, 100), speed(1, 100), speed(1, 100));
    await page.click(ID.jacredCheck);
    await page.click(ID.jacredCheck);
    await page.click(ID.jacredCompare);
    expect(bodies(page)).toEqual(["b=jac.red&q=0"]);
    await pause();
    await pause();
    expect(bodies(page)).toEqual(["b=jac.red&q=0", "b=jac.red&q=1", "b=jac.red&q=2"]);
  });

  test("some probes failed: available (k/3), the time of the good ones, — for the failed; not JSON is a failed probe", async () => {
    const page = open("empty");
    const t = page.init.i18n;
    page.replies.push(fail("Timeout"), { text: "oops", status: 500 }, speed(7, 2500));
    await page.click(ID.jacredCheck);
    await pause();
    await pause();
    expect(page.calls.length).toBe(3);
    expect(lines(page.el(ID.jacredResult))).toEqual([
      ["ok", "jacred.stream · " + t.yes + " (1/3) · 2.5 " + t.seconds + " · " + t.releases + ": — / — / 7"],
    ]);
  });

  test("all failed: not available with the last error, err", async () => {
    const page = open("empty");
    const t = page.init.i18n;
    page.replies.push(fail("Timeout"), fail("Timeout"), fail("HTTP 502"));
    await page.click(ID.jacredCheck);
    await pause();
    await pause();
    expect(lines(page.el(ID.jacredResult))).toEqual([
      ["err", "jacred.stream · " + t.no + ": HTTP 502 · — · " + t.releases + ": — / — / —"],
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
    page.choose("jacred", "own");
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

describe("Compare all", () => {
  // The text of every cell (of the first one, the name: Choose is under it).
  const table = (page: Page) =>
    Array.from(page.el(ID.jacredTable).querySelectorAll("tr")).map((row) =>
      Array.from(row.querySelectorAll("th,td")).map((cell) =>
        Array.from(cell.childNodes)
          .filter((node) => node.nodeName !== "BUTTON")
          .map((node) => node.textContent)
          .join(""),
      ),
    );
  const chooseButtons = (page: Page) => Array.from(page.el(ID.jacredTable).querySelectorAll<HTMLButtonElement>("button." + CLS.jacredChoose));

  test("every built-in and own one in turn, 3 probes each 2 s apart, a row each", async () => {
    const page = open("filled");
    const t = page.init.i18n;
    const names: string[] = [...BUILTINS, "192.168.1.10"];
    for (let i = 0; i < names.length; i++) page.replies.push(speed(i, 1000), speed(i, 1000), i === 2 ? fail("Timeout") : speed(i, 4000));
    await page.click(ID.jacredCompare);
    expect(busy(page)).toEqual([true, true]);
    expect(ariaBusy(page)).toEqual([null, "true"]);
    expect(table(page)).toEqual([
      [t.colName, t.colAvailable, t.colTime, t.releases],
      [names[0]!, t.checking + " 1/3", "", ""],
      ...names.slice(1).map((name) => [name, t.checking, "", ""]),
    ]);
    // 2 pauses a JacRed, the next one right after the last probe.
    for (let i = 0; i < 2 * names.length; i++) await pause();
    const targets = [...BUILTINS.map((id) => "b=" + id), OWN];
    expect(bodies(page)).toEqual(targets.flatMap((target) => [0, 1, 2].map((q) => target + "&q=" + q)));
    const sec = (s: string) => s + " " + t.seconds;
    expect(table(page).slice(1)).toEqual([
      [names[0]!, t.yes, sec("2.0"), "0 / 0 / 0"],
      [names[1]!, t.yes, sec("2.0"), "1 / 1 / 1"],
      [names[2]!, t.yes + " (2/3)", sec("1.0"), "2 / 2 / —"],
      [names[3]!, t.yes, sec("2.0"), "3 / 3 / 3"],
      [names[4]!, t.yes, sec("2.0"), "4 / 4 / 4"],
    ]);
    expect(busy(page)).toEqual([false, false]);
    expect(ariaBusy(page)).toEqual([null, null]);
  });

  test("Choose in every row from the start: checks the radio of that JacRed (own one too), the note follows", async () => {
    const page = open("filled");
    const t = page.init.i18n;
    for (let i = 0; i < 15; i++) page.replies.push(speed(1, 1000));
    await page.click(ID.jacredCompare);
    const buttons = chooseButtons(page);
    // Under the name, no column of its own (the table fits a phone of 320 px).
    expect(buttons.map((button) => [button.textContent, button.previousSibling!.textContent, button.closest("td")!.cellIndex])).toEqual(
      [...BUILTINS, "192.168.1.10"].map((name) => [t.choose, name, 0]),
    );
    expect(Array.from(page.el(ID.jacredTable).querySelectorAll("tr"), (row) => row.cells.length)).toEqual([4, 4, 4, 4, 4, 4]);
    expect(page.el("jrnote").hidden).toBe(false);
    buttons[0]!.click();
    expect([page.el<HTMLInputElement>("jrb1").checked, page.el<HTMLInputElement>("jrown").checked, page.el("jrnote").hidden]).toEqual([true, false, true]);
    buttons[3]!.click();
    expect([page.el<HTMLInputElement>("jrb4").checked, page.el("jrnote").hidden]).toEqual([true, false]);
    buttons[4]!.click();
    expect(page.el<HTMLInputElement>("jrown").checked).toBe(true);
    // Measuring goes on meanwhile, the buttons stay after it.
    for (let i = 0; i < 10; i++) await pause();
    expect(page.calls.length).toBe(15);
    expect(chooseButtons(page).map((button) => button.disabled)).toEqual([false, false, false, false, false]);
  });

  test("a new Compare all: the rows (and their buttons) of the old table go", async () => {
    const page = open("empty");
    for (let i = 0; i < 24; i++) page.replies.push(speed(1, 1000));
    await page.click(ID.jacredCompare);
    for (let i = 0; i < 8; i++) await pause();
    await page.click(ID.jacredCompare);
    expect(chooseButtons(page).length).toBe(4);
    expect(page.el(ID.jacredTable).querySelectorAll("table").length).toBe(1);
  });

  test("no own address: the built-in ones only; bad: format does not stop it", async () => {
    const page = open("empty");
    for (let i = 0; i < 12; i++) page.replies.push(i < 3 ? badFormat(page.init.i18n.jacredFormat) : speed(1, 1000));
    await page.click(ID.jacredCompare);
    for (let i = 0; i < 8; i++) await pause();
    expect(page.calls.length).toBe(12);
    expect(table(page).map((row) => row[0])).toEqual([page.init.i18n.colName, ...BUILTINS]);
  });

  test("a lone surrogate in the address of own one: nothing sent or locked, the broken character said instead of a table", async () => {
    const page = open("filled");
    page.type(ID.jacredOwnUrl, "http://\ud800jacred");
    await page.click(ID.jacredCompare);
    expect(page.calls).toEqual([]);
    expect(lines(page.el(ID.jacredTable))).toEqual([["err", page.init.i18n.badChars]]);
    expect(page.el(ID.jacredTable).querySelectorAll("table").length).toBe(0);
    expect([busy(page), ariaBusy(page)]).toEqual([[false, false], [null, null]]);
  });
});
