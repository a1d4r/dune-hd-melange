// The lock of the page: no check while a save goes, a save stops the probes of JacRed (it is never held up by a
// check); after a 403 one banner on top and every control of the form disabled.

import { afterEach, describe, expect, jest, test } from "bun:test";
import { CLS, ID } from "../src/dom";
import { cleanup, flush, json, later, lines, open, type Page, TOKEN } from "./harness";

afterEach(cleanup);

const FORBIDDEN = { text: "403 Forbidden\n", status: 403 };
const SAVE_URL = TOKEN + "&ajax=1";
const JR_URL = TOKEN + "&a=jr_speed&ajax=1";
const speed = json({ ok: true, msg: "", n: 1, ms: 1000 });

const saveButton = (page: Page) => page.form.querySelector<HTMLButtonElement>("." + CLS.save)!;
// Every button of a request but the save ones: the checks, JacRed, the checks of the server blocks.
const checkButtons = (page: Page) => [
  ...[ID.tsCheck, ID.manifestCheck, ID.aioOwnCheck, ID.rdCheck, ID.tbCheck, ID.tmdbCheck, ID.jacredCheck, ID.jacredCompare].map(
    (id) => page.el<HTMLButtonElement>(id),
  ),
  ...Array.from(page.form.querySelectorAll<HTMLButtonElement>("." + CLS.serverCheck)),
];
const disabled = (buttons: HTMLButtonElement[]) => buttons.map((button) => button.disabled);
const all = (count: number, value: boolean) => Array.from({ length: count }, () => value);
const rowStates = (page: Page) => Array.from(page.el(ID.jacredTable).querySelectorAll("tr")).slice(1).map((row) => row.cells[1]!.textContent);
async function pause(): Promise<void> {
  jest.advanceTimersByTime(2000);
  await flush();
}

describe("after a 403", () => {
  test("of a check: one banner on top, its line stays, every control of the form disabled, nothing starts", async () => {
    const page = open("err");
    const t = page.init.i18n;
    expect(page.el(ID.message).querySelectorAll("p").length).toBe(4);
    page.replies.push(FORBIDDEN);
    await page.click(ID.rdCheck);
    expect(lines(page.el(ID.message))).toEqual([["err", t.linkExpired]]);
    expect(lines(page.el(ID.rdResult))).toEqual([["err", t.linkExpired]]);
    const controls = Array.from(page.form.elements) as HTMLInputElement[];
    expect(controls.length).toBeGreaterThan(50);
    expect(controls.filter((control) => !control.disabled).map((control) => control.name || control.id || control.className)).toEqual([]);
    expect(saveButton(page).getAttribute("aria-busy")).toBe(null);
    page.form.dispatchEvent(new page.win.Event("submit", { cancelable: true }) as unknown as Event);
    await page.click(ID.tbCheck);
    await page.click(ID.jacredCompare);
    expect(page.calls.length).toBe(1);
  });

  test("of a save: the banner and the line under the button; Save stays disabled", async () => {
    const page = open("filled");
    page.replies.push(FORBIDDEN);
    saveButton(page).click();
    await flush();
    expect(lines(page.el(ID.message))).toEqual([["err", page.init.i18n.linkExpired]]);
    expect(lines(page.el(ID.saveResult))).toEqual([["err", page.init.i18n.linkExpired]]);
    expect([saveButton(page).disabled, page.el<HTMLButtonElement>(ID.resetConf).disabled]).toEqual([true, true]);
  });

  test("in Compare all: no more probes, the rows not done say the link expired (not stopped); the buttons stay disabled", async () => {
    jest.useFakeTimers();
    const page = open("empty");
    const t = page.init.i18n;
    page.replies.push(speed, FORBIDDEN);
    await page.click(ID.jacredCompare);
    await pause();
    expect(page.calls.length).toBe(2);
    expect(rowStates(page)[0]).toBe(t.checking + " 2/3");
    await pause();
    for (let i = 0; i < 10; i++) await pause();
    expect(page.calls.length).toBe(2);
    expect(rowStates(page)).toEqual([t.linkExpired, t.linkExpired, t.linkExpired, t.linkExpired]);
    expect(Array.from(page.el(ID.jacredTable).querySelectorAll<HTMLButtonElement>("button." + CLS.jacredChoose)).map((b) => b.disabled)).toEqual(all(4, true));
    expect(page.el(ID.jacredTable).getAttribute("aria-busy")).toBe(null);
    expect(lines(page.el(ID.message))).toEqual([["err", t.linkExpired]]);
    expect(disabled([page.el(ID.jacredCheck), page.el(ID.jacredCompare)])).toEqual([true, true]);
  });

  test("in Check: its line says the link expired, err", async () => {
    jest.useFakeTimers();
    const page = open("empty");
    page.replies.push(speed, FORBIDDEN);
    await page.click(ID.jacredCheck);
    await pause();
    await pause();
    expect(page.calls.length).toBe(2);
    expect(lines(page.el(ID.jacredResult))).toEqual([["err", "jacred.stream · " + page.init.i18n.linkExpired]]);
  });
});

describe("while a save goes", () => {
  test("every check disabled and a click starts nothing; Save and Reset disabled, Save aria-busy; all back after it", async () => {
    const page = open("filled");
    const buttons = checkButtons(page);
    expect(buttons.length).toBe(13);
    const answer = later();
    page.replies.push(answer.reply);
    saveButton(page).click();
    await flush();
    expect(disabled(buttons)).toEqual(all(13, true));
    expect([saveButton(page).disabled, page.el<HTMLButtonElement>(ID.resetConf).disabled]).toEqual([true, true]);
    expect(saveButton(page).getAttribute("aria-busy")).toBe("true");
    for (const button of buttons) button.click();
    await flush();
    expect(page.calls.map((call) => call.url)).toEqual([SAVE_URL]);
    answer.resolve(json({ ok: false, errors: [{ msg: "Bad", field: null }] }));
    await flush();
    expect(disabled(buttons)).toEqual(all(13, false));
    expect([saveButton(page).disabled, saveButton(page).getAttribute("aria-busy")]).toEqual([false, null]);
  });

  test("a check button enabled by hand (not by the lock): its click starts nothing either", async () => {
    const page = open("filled");
    const answer = later();
    page.replies.push(answer.reply);
    saveButton(page).click();
    await flush();
    for (const button of checkButtons(page)) {
      button.disabled = false;
      button.click();
    }
    await flush();
    expect(page.calls.map((call) => call.url)).toEqual([SAVE_URL]);
  });

  test("and the config after it (aio_sync): the checks stay disabled until it is done", async () => {
    const page = open("filled");
    const sync = later();
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "update" }), sync.reply);
    saveButton(page).click();
    await flush();
    expect(page.calls.length).toBe(2);
    expect(disabled(checkButtons(page))).toEqual(all(13, true));
    sync.resolve(json({ ok: true, msg: "Updated", skip: false, base: null, conf: null }));
    await flush();
    expect(disabled(checkButtons(page))).toEqual(all(13, false));
  });

  test("a check that went before: its result comes, its button back only after the save", async () => {
    const page = open("filled");
    const check = later(),
      answer = later();
    page.replies.push(check.reply, answer.reply);
    await page.click(ID.rdCheck);
    expect(page.el(ID.rdCheck).getAttribute("aria-busy")).toBe("true");
    saveButton(page).click();
    await flush();
    check.resolve(json({ ok: true, level: "ok", msg: "Works" }));
    await flush();
    expect(lines(page.el(ID.rdResult))).toEqual([["ok", "Works"]]);
    expect([page.el<HTMLButtonElement>(ID.rdCheck).disabled, page.el(ID.rdCheck).getAttribute("aria-busy")]).toEqual([true, null]);
    answer.resolve(json({ ok: false, errors: [{ msg: "Bad", field: null }] }));
    await flush();
    expect(page.el<HTMLButtonElement>(ID.rdCheck).disabled).toBe(false);
  });
});

describe("a save while JacRed is measured", () => {
  test("Compare all: Save goes at once, the probes stop before the next one, the rows not done say stopped", async () => {
    jest.useFakeTimers();
    const page = open("empty");
    const t = page.init.i18n;
    const answer = later();
    page.replies.push(speed, answer.reply);
    await page.click(ID.jacredCompare);
    expect(saveButton(page).disabled).toBe(false);
    saveButton(page).click();
    await flush();
    expect(page.calls.map((call) => call.url)).toEqual([JR_URL, SAVE_URL]);
    await pause();
    await pause();
    expect(page.calls.length).toBe(2);
    expect(rowStates(page)).toEqual([t.stopped, t.stopped, t.stopped, t.stopped]);
    expect(page.el<HTMLButtonElement>(ID.jacredCompare).disabled).toBe(true);
    answer.resolve(json({ ok: false, errors: [{ msg: "Bad", field: null }] }));
    await flush();
    expect(disabled([page.el(ID.jacredCheck), page.el(ID.jacredCompare)])).toEqual([false, false]);
  });

  test("Compare all: a quick save with an error within a pause stops it too (the settings may have changed)", async () => {
    jest.useFakeTimers();
    const page = open("empty");
    const t = page.init.i18n;
    page.replies.push(speed, speed, json({ ok: false, errors: [{ msg: "Bad", field: null }] }));
    await page.click(ID.jacredCompare);
    await pause();
    expect(rowStates(page)[0]).toBe(t.checking + " 2/3");
    saveButton(page).click();
    await flush();
    expect([saveButton(page).disabled, lines(page.el(ID.saveResult))]).toEqual([false, [["err", "Bad"]]]);
    for (let i = 0; i < 4; i++) await pause();
    expect(page.calls.map((call) => call.url)).toEqual([JR_URL, JR_URL, SAVE_URL]);
    expect(rowStates(page)).toEqual([t.stopped, t.stopped, t.stopped, t.stopped]);
    expect(disabled([page.el(ID.jacredCheck), page.el(ID.jacredCompare)])).toEqual([false, false]);
  });

  test("a save before the start does not stop the next Compare all", async () => {
    jest.useFakeTimers();
    const page = open("empty");
    page.replies.push(json({ ok: false, errors: [{ msg: "Bad", field: null }] }));
    saveButton(page).click();
    await flush();
    for (let i = 0; i < 12; i++) page.replies.push(speed);
    await page.click(ID.jacredCompare);
    for (let i = 0; i < 8; i++) await pause();
    expect(page.calls.length).toBe(13);
    expect(rowStates(page)).toEqual(Array(4).fill(page.init.i18n.yes));
  });

  test("Choose in the table: disabled while the save goes, back after it; a click starts nothing meanwhile", async () => {
    jest.useFakeTimers();
    const page = open("empty");
    const answer = later();
    page.replies.push(speed, answer.reply);
    await page.click(ID.jacredCompare);
    const choose = () => Array.from(page.el(ID.jacredTable).querySelectorAll<HTMLButtonElement>("button." + CLS.jacredChoose));
    expect(choose().map((button) => button.disabled)).toEqual(all(4, false));
    saveButton(page).click();
    await flush();
    expect(choose().map((button) => button.disabled)).toEqual(all(4, true));
    choose()[1]!.disabled = false;
    choose()[1]!.click();
    expect(page.el<HTMLInputElement>("jrb2").checked).toBe(false);
    answer.resolve(json({ ok: false, errors: [{ msg: "Bad", field: null }] }));
    await flush();
    expect(choose().map((button) => button.disabled)).toEqual(all(4, false));
    choose()[1]!.click();
    expect(page.el<HTMLInputElement>("jrb2").checked).toBe(true);
  });

  test("Check: its line says stopped", async () => {
    jest.useFakeTimers();
    const page = open("empty");
    page.replies.push(speed, later().reply);
    await page.click(ID.jacredCheck);
    saveButton(page).click();
    await flush();
    await pause();
    expect(page.calls.length).toBe(2);
    expect(lines(page.el(ID.jacredResult))).toEqual([["hint", "jacred.stream · " + page.init.i18n.stopped]]);
    expect(page.el(ID.jacredResult).getAttribute("aria-busy")).toBe(null);
  });
});
