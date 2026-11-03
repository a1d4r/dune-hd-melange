// The screen of the rows of the catalog: the list by the Dune when it opens, the choice in the field cat_rows.

import { afterEach, describe, expect, jest, test } from "bun:test";
import type { CatRow } from "../src/api";
import { ID } from "../src/dom";
import { start } from "../src/init";
import { chosenKeys } from "../src/rows";
import { cleanup, flush, HANG, json, later, lines, NET, open, type Page, type Reply, TOKEN } from "./harness";

afterEach(cleanup);

const row = (key: string, name: string, group: string, home = false): CatRow => ({ key, name, group, home });
const ROWS: CatRow[] = [
  row("movie/a", "A", "top", true),
  row("series/a", "A series", "top", true),
  row("movie/b", "B", "genres"),
  row("movie/c", "C", "genres", true),
  row("movie/new", "New group", "something-new"),
  row("anime/d", "D", "other"),
];
const LIST = json({ ok: true, stale: false, rows: ROWS });

// The page with the screen open and the reply to its request taken.
async function opened(reply: Reply, stored = ""): Promise<Page> {
  const page = open("filled", { before: (doc) => (doc.getElementById(ID.rowsValue) as HTMLInputElement).setAttribute("value", stored) });
  page.replies.push(reply);
  await go(page);
  return page;
}
async function go(page: Page): Promise<void> {
  page.win.location.hash = "#rows";
  page.win.dispatchEvent(new page.win.Event("hashchange"));
  await flush();
}
const value = (page: Page) => page.field("cat_rows").value;
const shownRows = (page: Page) => Array.from(page.el(ID.rowsChosen).children).map((line) => line.querySelector("span")!.textContent);
const shownCheck = (page: Page, n: number) => page.el(ID.rowsChosen).children[n]!.querySelector<HTMLInputElement>("input")!;
const moves = (page: Page, n: number) => page.el(ID.rowsChosen).children[n]!.querySelectorAll("button");
const groups = (page: Page) => Array.from(page.el(ID.rowsGroups).querySelectorAll("details"));
const groupCheck = (page: Page, name: string) =>
  Array.from(page.el(ID.rowsGroups).querySelectorAll("label")).find((label) => label.querySelector("span")!.textContent === name)!.querySelector("input")!;
function click(page: Page, check: HTMLInputElement): void {
  check.checked = !check.checked;
  check.dispatchEvent(new page.win.Event("change", { bubbles: true }) as unknown as Event);
}

describe("the rule of the choice, as aio_cat_user_rows", () => {
  test.each([
    ["nothing chosen: the start rows in the order of the list", "", ["movie/a", "series/a", "movie/c"]],
    ["the order of the user", "movie/c movie/b", ["movie/c", "movie/b"]],
    ["a key gone from the list goes, a new row does not come", "movie/gone anime/d", ["anime/d"]],
    ["a repeat once", "movie/b movie/b", ["movie/b"]],
    ["all gone: the start rows", "movie/gone", ["movie/a", "series/a", "movie/c"]],
  ])("%s", (_name, stored, want) => expect(chosenKeys(ROWS, stored)).toEqual(want));
});

describe("the list", () => {
  test("no request till the screen opens; once: not again on a second visit", async () => {
    const page = open("filled");
    expect(page.calls).toEqual([]);
    page.replies.push(LIST);
    await go(page);
    expect(page.calls).toEqual([{ method: "POST", url: TOKEN + "&a=cat_rows&ajax=1", body: "", contentType: "application/x-www-form-urlencoded" }]);
    expect(page.el(ID.rowsBox).hidden).toBe(false);
    await go(page);
    expect(page.calls.length).toBe(1);
  });

  test("the address already on the screen: asked at the start", async () => {
    const page = open("filled", { start: false });
    page.win.location.hash = "#rows";
    page.replies.push(LIST);
    start(page.doc);
    await flush();
    expect(page.calls.length).toBe(1);
  });

  test("while it goes: the line of loading, the box hidden, no second request", async () => {
    const reply = later();
    const page = await opened(reply.reply);
    expect(lines(page.el(ID.rowsStatus))).toEqual([["hint", page.init.i18n.rowsLoading]]);
    expect(page.el(ID.rowsBox).hidden).toBe(true);
    await go(page);
    expect(page.calls.length).toBe(1);
    reply.resolve(LIST);
    await flush();
    expect(lines(page.el(ID.rowsStatus))).toEqual([]);
    expect(page.el(ID.rowsBox).hidden).toBe(false);
  });

  test("nothing chosen: the start rows shown; groups in the order of the screen, closed, an unknown one in Other", async () => {
    const page = await opened(LIST);
    expect(shownRows(page)).toEqual(["A", "A series", "C"]);
    const { i18n } = page.init;
    expect(groups(page).map((group) => group.querySelector("summary")!.textContent)).toEqual([
      i18n.groupTop + " · 2/2",
      i18n.groupGenres + " · 1/2",
      i18n.groupOther + " · 0/2",
    ]);
    expect(groups(page).some((group) => group.open)).toBe(false);
    expect(Array.from(groups(page)[2]!.querySelectorAll("span")).map((name) => name.textContent)).toEqual(["New group", "D"]);
    expect([groupCheck(page, "A").checked, groupCheck(page, "B").checked]).toEqual([true, false]);
  });

  test("names go as text", async () => {
    const page = await opened(json({ ok: true, stale: false, rows: [row("movie/x", "<b>x</b>", "top", true)] }));
    expect(shownRows(page)).toEqual(["<b>x</b>"]);
    expect(page.el(ID.rowsChosen).querySelector("b")).toBeNull();
  });

  test("a last good copy: the list works, the line says it", async () => {
    const page = await opened(json({ ok: true, stale: true, rows: ROWS }));
    expect(lines(page.el(ID.rowsStatus))).toEqual([["warn", page.init.i18n.rowsStale]]);
    expect(shownRows(page).length).toBe(3);
  });
});

describe("no list: said, the saved choice not touched", () => {
  const STORED = "movie/c movie/b";
  test.each([
    ["no list on the Dune", json({ ok: false, stale: false, rows: [] }), "rowsNoList"],
    ["an empty list", json({ ok: true, stale: false, rows: [] }), "rowsNoList"],
  ] as const)("%s: the text, Try again, then it comes", async (_name, reply, text) => {
    const page = await opened(reply, STORED);
    expect(lines(page.el(ID.rowsStatus))).toEqual([["err", page.init.i18n[text]]]);
    expect(page.el(ID.rowsBox).hidden).toBe(true);
    expect(page.el(ID.rowsRetry).hidden).toBe(false);
    expect(value(page)).toBe(STORED);
    page.replies.push(LIST);
    await page.click(ID.rowsRetry);
    expect(page.el(ID.rowsRetry).hidden).toBe(true);
    expect(shownRows(page)).toEqual(["C", "B"]);
    expect(value(page)).toBe(STORED);
  });

  test("no connection to the Dune: its text; going back to the screen asks again", async () => {
    const page = await opened(NET, STORED);
    expect(lines(page.el(ID.rowsStatus))).toEqual([["err", page.init.i18n.noConnection]]);
    page.replies.push(LIST);
    await go(page);
    expect(shownRows(page)).toEqual(["C", "B"]);
  });

  test("a 403: the link out of date, the whole page knows", async () => {
    const page = await opened(json({}, 403));
    expect(lines(page.el(ID.message))).toEqual([["err", page.init.i18n.linkExpired]]);
  });

  test("no answer in time: the timeout said", async () => {
    const page = open("filled");
    page.replies.push(HANG);
    jest.useFakeTimers();
    page.win.location.hash = "#rows";
    page.win.dispatchEvent(new page.win.Event("hashchange"));
    jest.advanceTimersByTime(40000);
    await flush();
    expect(lines(page.el(ID.rowsStatus))).toEqual([["err", page.init.i18n.timeout.replace("%d", "40")]]);
  });

  test("the save posts the field as it came: the choice not lost", async () => {
    const page = await opened(NET, STORED);
    page.replies.push(json({ ok: true, next: "settings?saved=1", aio: "" }));
    page.form.dispatchEvent(new page.win.Event("submit", { cancelable: true }) as unknown as Event);
    await flush();
    expect(page.calls[1]!.body).toContain("cat_rows=movie%2Fc%20movie%2Fb&");
  });
});

describe("the choice", () => {
  test("untouched: the field empty, no change for the bar", async () => {
    const page = await opened(LIST);
    expect(value(page)).toBe("");
    expect(page.el(ID.dirty).textContent).toBe("");
  });

  test("a row turned on in its group: last in the list, the field, the group's count and the bar follow", async () => {
    const page = await opened(LIST);
    click(page, groupCheck(page, "B"));
    expect(shownRows(page)).toEqual(["A", "A series", "C", "B"]);
    expect(value(page)).toBe("movie/a series/a movie/c movie/b");
    expect(groups(page)[1]!.querySelector("summary")!.textContent).toBe(page.init.i18n.groupGenres + " · 2/2");
    expect(page.el(ID.dirty).textContent).not.toBe("");
  });

  test("a row turned off in the list: gone, its box in the group off; off in a group: gone from the list", async () => {
    const page = await opened(LIST);
    click(page, shownCheck(page, 1));
    expect(shownRows(page)).toEqual(["A", "C"]);
    expect(groupCheck(page, "A series").checked).toBe(false);
    click(page, groupCheck(page, "A"));
    expect(shownRows(page)).toEqual(["C"]);
    expect(value(page)).toBe("movie/c");
  });

  test("moved up and down; the first can't go up, the last can't go down; the bar follows a move", async () => {
    const page = await opened(LIST);
    expect([moves(page, 0)[0]!.disabled, moves(page, 0)[1]!.disabled, moves(page, 2)[0]!.disabled, moves(page, 2)[1]!.disabled]).toEqual([true, false, false, true]);
    moves(page, 2)[0]!.click();
    expect(value(page)).toBe("movie/a movie/c series/a");
    expect(page.el(ID.dirty).textContent).not.toBe("");
    moves(page, 0)[1]!.click();
    expect(shownRows(page)).toEqual(["C", "A", "A series"]);
    expect(moves(page, 0)[0]!.getAttribute("aria-label")).toBe(page.init.i18n.rowsUp);
    expect(moves(page, 0)[1]!.getAttribute("aria-label")).toBe(page.init.i18n.rowsDown);
  });

  test("the last row can't go: stays on, the text shown under the screen, no empty choice; the next change takes the text away", async () => {
    const page = await opened(LIST, "movie/b");
    const before = value(page);
    click(page, shownCheck(page, 0));
    expect(shownCheck(page, 0).checked).toBe(true);
    expect(value(page)).toBe(before);
    expect(lines(page.el(ID.saveResult))).toEqual([["warn", page.init.i18n.rowsLast]]);
    expect(page.el(ID.bar).hidden).toBe(false);
    // In its group too, once more: one line.
    click(page, groupCheck(page, "B"));
    expect(groupCheck(page, "B").checked).toBe(true);
    expect(lines(page.el(ID.saveResult)).length).toBe(1);
    click(page, groupCheck(page, "C"));
    expect(lines(page.el(ID.saveResult))).toEqual([]);
    expect(value(page)).toBe("movie/b movie/c");
  });

  test("the chosen ones saved by the form: the keys by spaces, in order; a save that stays leaves no change", async () => {
    const page = await opened(LIST);
    moves(page, 1)[0]!.click();
    page.replies.push(json({ ok: true, next: "settings?saved=1", aio: "update", notes: [{ level: "ok", msg: "x" }] }), json({ ok: true, msg: "ok", skip: true }));
    page.form.dispatchEvent(new page.win.Event("submit", { cancelable: true }) as unknown as Event);
    await flush();
    expect(page.calls[1]!.body).toContain("cat_rows=series%2Fa%20movie%2Fa%20movie%2Fc&");
    expect(page.el(ID.dirty).textContent).toBe("");
  });
});
