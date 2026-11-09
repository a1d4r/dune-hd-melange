// The screen of the rows of the catalog: the list by the Dune when it opens, the choice in the field cat_rows.

import { afterEach, describe, expect, jest, test } from "bun:test";
import type { CatRow } from "../src/api";
import { DATA, ID } from "../src/dom";
import { start } from "../src/init";
import { chosenKeys } from "../src/rows";
import { cleanup, flush, HANG, json, later, lines, NET, open, type Page, type Reply, TOKEN } from "./harness";

afterEach(cleanup);

// tab: as the CGI sends it; here by the type of the key, unless given.
const row = (key: string, name: string, group: string, home = false, tab: CatRow["tab"] = key.startsWith("movie/") ? "movie" : "series"): CatRow => ({
  key,
  name,
  group,
  home,
  tab,
});
const ROWS: CatRow[] = [
  row("movie/a", "A", "top", true),
  row("series/a", "A series", "top", true),
  row("movie/b", "B", "genres"),
  row("movie/c", "C", "genres", true),
  row("movie/new", "New group", "something-new"),
  row("anime/d", "D", "other"),
];
const LIST = json({ ok: true, stale: false, rows: ROWS });
const PART = { movie: [ID.rowsMovies, ID.rowsMovieGroups, ID.rowsMoviePart], series: [ID.rowsSeries, ID.rowsSeriesGroups, ID.rowsSeriesPart] } as const;
type Tab = keyof typeof PART;

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
const shownRows = (page: Page, tab: Tab = "movie") =>
  Array.from(page.el(PART[tab][0]).children).map((line) => line.querySelector("span")!.textContent);
const shownCheck = (page: Page, n: number, tab: Tab = "movie") => page.el(PART[tab][0]).children[n]!.querySelector<HTMLInputElement>("input")!;
const moves = (page: Page, n: number, tab: Tab = "movie") => page.el(PART[tab][0]).children[n]!.querySelectorAll("button");
const groups = (page: Page, tab: Tab = "movie") => Array.from(page.el(PART[tab][1]).querySelectorAll("details"));
const groupTitles = (page: Page, tab: Tab) => groups(page, tab).map((group) => group.querySelector("summary")!.textContent);
const groupCheck = (page: Page, name: string) =>
  Array.from(page.el(ID.rowsBox).querySelectorAll("details label"))
    .find((label) => label.querySelector("span")!.textContent === name)!
    .querySelector("input")!;
// The button of a part in the switch; the parts by what is seen: [movie hidden, series hidden], the buttons pressed.
const tabButton = (page: Page, tab: Tab) => page.el(ID.rowsSwitch).querySelector<HTMLButtonElement>("[" + DATA.key + "=" + tab + "]")!;
const partsHidden = (page: Page) => [page.el(PART.movie[2]).hidden, page.el(PART.series[2]).hidden];
const pressed = (page: Page) => [tabButton(page, "movie").getAttribute("aria-pressed"), tabButton(page, "series").getAttribute("aria-pressed")];
function click(page: Page, check: HTMLInputElement): void {
  check.checked = !check.checked;
  check.dispatchEvent(new page.win.Event("change", { bubbles: true }) as unknown as Event);
}

describe("the rule of the choice, as aio_cat_user_rows: by the tab", () => {
  test.each([
    ["nothing chosen: the start rows of the tab in the order of the list", "", ["movie/a", "movie/c"], ["series/a"]],
    ["the order of the user; none of the tab: its start rows (a choice of before 0.54.0)", "movie/c movie/b", ["movie/c", "movie/b"], ["series/a"]],
    ["a key gone from the list goes, a new row does not come; anime with the series", "movie/gone anime/d", ["movie/a", "movie/c"], ["anime/d"]],
    ["a repeat once", "movie/b movie/b", ["movie/b"], ["series/a"]],
    ["all gone: the start rows", "movie/gone", ["movie/a", "movie/c"], ["series/a"]],
    ["mixed: each tab its own order", "series/a movie/c anime/d movie/a", ["movie/c", "movie/a"], ["series/a", "anime/d"]],
  ])("%s", (_name, stored, movie, series) => {
    expect(chosenKeys(ROWS, stored, "movie")).toEqual(movie);
    expect(chosenKeys(ROWS, stored, "series")).toEqual(series);
  });
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

  test("nothing chosen: the start rows of each part; groups by part in the order of the screen, closed, an unknown one in Other", async () => {
    const page = await opened(LIST);
    expect(shownRows(page, "movie")).toEqual(["A", "C"]);
    expect(shownRows(page, "series")).toEqual(["A series"]);
    const { i18n } = page.init;
    expect(groupTitles(page, "movie")).toEqual([i18n.groupTop + " · 1/1", i18n.groupGenres + " · 1/2", i18n.groupOther + " · 0/1"]);
    expect(groupTitles(page, "series")).toEqual([i18n.groupTop + " · 1/1", i18n.groupOther + " · 0/1"]);
    expect([...groups(page, "movie"), ...groups(page, "series")].some((group) => group.open)).toBe(false);
    expect(Array.from(groups(page, "movie")[2]!.querySelectorAll("span")).map((name) => name.textContent)).toEqual(["New group"]);
    expect(Array.from(groups(page, "series")[1]!.querySelectorAll("span")).map((name) => name.textContent)).toEqual(["D"]);
    expect([groupCheck(page, "A").checked, groupCheck(page, "B").checked, groupCheck(page, "A series").checked]).toEqual([true, false, true]);
  });

  test("the part of a row: its tab as the Dune sends it, not the key", async () => {
    const page = await opened(json({ ok: true, stale: false, rows: [row("movie/x", "X", "top", true, "series"), row("series/y", "Y", "top", true, "movie")] }));
    expect(shownRows(page, "movie")).toEqual(["Y"]);
    expect(shownRows(page, "series")).toEqual(["X"]);
  });

  test("names go as text", async () => {
    const page = await opened(json({ ok: true, stale: false, rows: [row("movie/x", "<b>x</b>", "top", true)] }));
    expect(shownRows(page)).toEqual(["<b>x</b>"]);
    expect(page.el(ID.rowsMovies).querySelector("b")).toBeNull();
  });

  test("a last good copy: the list works, the line says it", async () => {
    const page = await opened(json({ ok: true, stale: true, rows: ROWS }));
    expect(lines(page.el(ID.rowsStatus))).toEqual([["warn", page.init.i18n.rowsStale]]);
    expect(shownRows(page).length).toBe(2);
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

describe("the switch of the parts", () => {
  test("Movies open first; a button shows its part, the other hidden; its count of rows shown", async () => {
    const page = await opened(LIST);
    expect(partsHidden(page)).toEqual([false, true]);
    expect(pressed(page)).toEqual(["true", "false"]);
    expect([tabButton(page, "movie").textContent, tabButton(page, "series").textContent]).toEqual(["Фильмы · 2", "Сериалы · 1"]);
    tabButton(page, "series").click();
    expect(partsHidden(page)).toEqual([true, false]);
    expect(pressed(page)).toEqual(["false", "true"]);
    tabButton(page, "movie").click();
    expect(partsHidden(page)).toEqual([false, true]);
    // A switch is no change.
    expect(page.el(ID.dirty).textContent).toBe("");
  });

  test("the count follows a row on and off; the part stays as it was", async () => {
    const page = await opened(LIST);
    tabButton(page, "series").click();
    click(page, groupCheck(page, "D"));
    expect(tabButton(page, "series").textContent).toBe("Сериалы · 2");
    click(page, groupCheck(page, "B"));
    click(page, shownCheck(page, 0));
    expect(tabButton(page, "movie").textContent).toBe("Фильмы · 2");
    expect(partsHidden(page)).toEqual([true, false]);
  });

  test("the hidden part keeps its choice and order; the save posts both", async () => {
    const page = await opened(LIST);
    moves(page, 1)[0]!.click();
    tabButton(page, "series").click();
    click(page, groupCheck(page, "D"));
    tabButton(page, "movie").click();
    expect(shownRows(page)).toEqual(["C", "A"]);
    expect(shownRows(page, "series")).toEqual(["A series", "D"]);
    page.replies.push(json({ ok: true, next: "settings?saved=1", aio: "" }));
    page.form.dispatchEvent(new page.win.Event("submit", { cancelable: true }) as unknown as Event);
    await flush();
    expect(page.calls[1]!.body).toContain("cat_rows=movie%2Fc%20movie%2Fa%20series%2Fa%20anime%2Fd&");
  });
});

describe("the choice", () => {
  test("untouched: the field empty, no change for the bar", async () => {
    const page = await opened(LIST);
    expect(value(page)).toBe("");
    expect(page.el(ID.dirty).textContent).toBe("");
  });

  test("a row turned on in its group: last in its part, the field (movies, then series), the group's count and the bar follow", async () => {
    const page = await opened(LIST);
    click(page, groupCheck(page, "B"));
    expect(shownRows(page)).toEqual(["A", "C", "B"]);
    expect(shownRows(page, "series")).toEqual(["A series"]);
    expect(value(page)).toBe("movie/a movie/c movie/b series/a");
    expect(groups(page)[1]!.querySelector("summary")!.textContent).toBe(page.init.i18n.groupGenres + " · 2/2");
    expect(page.el(ID.dirty).textContent).not.toBe("");
    click(page, groupCheck(page, "D"));
    expect(shownRows(page, "series")).toEqual(["A series", "D"]);
    expect(value(page)).toBe("movie/a movie/c movie/b series/a anime/d");
  });

  test("a row turned off in the list: gone, its box in the group off; off in a group: gone from the list", async () => {
    const page = await opened(LIST);
    click(page, shownCheck(page, 1));
    expect(shownRows(page)).toEqual(["A"]);
    expect(groupCheck(page, "C").checked).toBe(false);
    click(page, groupCheck(page, "B"));
    click(page, groupCheck(page, "A"));
    expect(shownRows(page)).toEqual(["B"]);
    expect(value(page)).toBe("movie/b series/a");
  });

  test("moved up and down within its part; the first can't go up, the last can't go down; the bar follows a move", async () => {
    const page = await opened(LIST);
    expect([moves(page, 0)[0]!.disabled, moves(page, 0)[1]!.disabled, moves(page, 1)[0]!.disabled, moves(page, 1)[1]!.disabled]).toEqual([true, false, false, true]);
    expect([moves(page, 0, "series")[0]!.disabled, moves(page, 0, "series")[1]!.disabled]).toEqual([true, true]);
    moves(page, 1)[0]!.click();
    expect(shownRows(page)).toEqual(["C", "A"]);
    expect(value(page)).toBe("movie/c movie/a series/a");
    expect(page.el(ID.dirty).textContent).not.toBe("");
    moves(page, 0)[1]!.click();
    expect(shownRows(page)).toEqual(["A", "C"]);
    expect(moves(page, 0)[0]!.getAttribute("aria-label")).toBe(page.init.i18n.rowsUp);
    expect(moves(page, 0)[1]!.getAttribute("aria-label")).toBe(page.init.i18n.rowsDown);
  });

  test("the last row of a part can't go: stays on, its text under the screen; the next change takes the text away", async () => {
    const page = await opened(LIST, "movie/b");
    const before = value(page);
    click(page, shownCheck(page, 0));
    expect(shownCheck(page, 0).checked).toBe(true);
    expect(value(page)).toBe(before);
    expect(lines(page.el(ID.saveResult))).toEqual([["warn", page.init.i18n.rowsLastMovie]]);
    expect(page.el(ID.bar).hidden).toBe(false);
    // In its group too, once more: one line.
    click(page, groupCheck(page, "B"));
    expect(groupCheck(page, "B").checked).toBe(true);
    expect(lines(page.el(ID.saveResult)).length).toBe(1);
    click(page, groupCheck(page, "C"));
    expect(lines(page.el(ID.saveResult))).toEqual([]);
    expect(value(page)).toBe("movie/b movie/c series/a");
    // The series: their own last row, their own text.
    click(page, shownCheck(page, 0, "series"));
    expect(shownCheck(page, 0, "series").checked).toBe(true);
    expect(lines(page.el(ID.saveResult))).toEqual([["warn", page.init.i18n.rowsLastSeries]]);
    expect(value(page)).toBe("movie/b movie/c series/a");
  });

  test("a choice of before 0.54.0, only movies: the series part with its start rows; the field as it came till a change", async () => {
    const page = await opened(LIST, "movie/c");
    expect(shownRows(page)).toEqual(["C"]);
    expect(shownRows(page, "series")).toEqual(["A series"]);
    expect(value(page)).toBe("movie/c");
    click(page, groupCheck(page, "D"));
    expect(value(page)).toBe("movie/c series/a anime/d");
  });

  test("a choice of movies and series mixed: each part its order; the field then by part", async () => {
    const page = await opened(LIST, "series/a movie/c anime/d movie/a");
    expect(shownRows(page)).toEqual(["C", "A"]);
    expect(shownRows(page, "series")).toEqual(["A series", "D"]);
    moves(page, 1, "series")[0]!.click();
    expect(value(page)).toBe("movie/c movie/a anime/d series/a");
  });

  test("the chosen ones saved by the form: the keys by spaces, in order; a save that stays leaves no change", async () => {
    const page = await opened(LIST);
    moves(page, 1)[0]!.click();
    page.replies.push(json({ ok: true, next: "settings?saved=1", aio: "update", notes: [{ level: "ok", msg: "x" }] }), json({ ok: true, msg: "ok", skip: true }));
    page.form.dispatchEvent(new page.win.Event("submit", { cancelable: true }) as unknown as Event);
    await flush();
    expect(page.calls[1]!.body).toContain("cat_rows=movie%2Fc%20movie%2Fa%20series%2Fa&");
    expect(page.el(ID.dirty).textContent).toBe("");
  });
});

describe("the checkbox in the title of a group", () => {
  // The checkbox in the title of the n-th group of a part; of each group of movies: [checked, indeterminate].
  const groupAll = (page: Page, n: number, tab: Tab = "movie") => groups(page, tab)[n]!.querySelector<HTMLInputElement>("summary input")!;
  const state = (page: Page) => groups(page).map((_group, n) => [groupAll(page, n).checked, groupAll(page, n).indeterminate]);

  test("on: all the rows of the group on; some: indeterminate; none: off; it follows a row; its name for a screen reader", async () => {
    const page = await opened(LIST);
    expect(state(page)).toEqual([
      [true, false],
      [false, true],
      [false, false],
    ]);
    click(page, groupCheck(page, "B"));
    click(page, groupCheck(page, "New group"));
    expect(state(page)).toEqual([
      [true, false],
      [true, false],
      [true, false],
    ]);
    expect(groupAll(page, 1).getAttribute("aria-label")).toBe(page.init.i18n.rowsGroupAll + ": " + page.init.i18n.groupGenres);
  });

  test("not all on: the rest go on, last in the part in the order of the group; the group stays closed; the bar follows", async () => {
    const page = await opened(LIST, "movie/a");
    click(page, groupAll(page, 1));
    expect(value(page)).toBe("movie/a movie/b movie/c series/a");
    expect(shownRows(page)).toEqual(["A", "B", "C"]);
    expect(groups(page)[1]!.querySelector("summary")!.textContent).toBe(page.init.i18n.groupGenres + " · 2/2");
    expect(state(page)[1]).toEqual([true, false]);
    // A browser does not open the details on a click on a control of the summary; happy-dom would on a click, so
    // here only the change: the script itself does not open it.
    expect(groups(page)[1]!.open).toBe(false);
    expect(page.el(ID.dirty).textContent).not.toBe("");
  });

  test("some on: only the rest go on, after the rows chosen before", async () => {
    const page = await opened(LIST, "movie/c movie/a");
    click(page, groupAll(page, 1));
    expect(value(page)).toBe("movie/c movie/a movie/b series/a");
  });

  test("all on: all go off, the other rows of the part stay in their order", async () => {
    const page = await opened(LIST, "movie/b movie/a movie/c");
    click(page, groupAll(page, 1));
    expect(value(page)).toBe("movie/a series/a");
    expect(shownRows(page)).toEqual(["A"]);
    expect([groupCheck(page, "B").checked, groupCheck(page, "C").checked]).toEqual([false, false]);
    expect(state(page)[1]).toEqual([false, false]);
  });

  test("all off would leave the part empty: nothing goes, the box on again, the text of the last row under the screen", async () => {
    const page = await opened(LIST, "movie/b movie/c anime/d");
    click(page, groupAll(page, 1));
    expect(state(page)[1]).toEqual([true, false]);
    expect(shownRows(page)).toEqual(["B", "C"]);
    expect(value(page)).toBe("movie/b movie/c anime/d");
    expect(lines(page.el(ID.saveResult))).toEqual([["warn", page.init.i18n.rowsLastMovie]]);
    expect(page.el(ID.bar).hidden).toBe(false);
    // The series: their own text.
    click(page, groupAll(page, 1, "series"));
    expect(groupAll(page, 1, "series").checked).toBe(true);
    expect(lines(page.el(ID.saveResult))).toEqual([["warn", page.init.i18n.rowsLastSeries]]);
    expect(value(page)).toBe("movie/b movie/c anime/d");
  });
});
