// The screen of the rows of the catalog: the list by the Dune when it opens, the choice in the field cat_rows.

import { afterEach, describe, expect, jest, test } from "bun:test";
import type { CatRow } from "../src/api";
import { DATA, ID } from "../src/dom";
import { start } from "../src/init";
import { readFileSync } from "fs";
import { byGroups, chosenKeys, OWN, ownAt, ownKey } from "../src/rows";
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
// Movies of three groups, to move.
const GLIST = json({
  ok: true,
  stale: false,
  rows: [
    row("movie/t1", "T1", "top", true),
    row("movie/t2", "T2", "top"),
    row("movie/g1", "G1", "genres"),
    row("movie/g2", "G2", "genres"),
    row("movie/g3", "G3", "genres"),
    row("movie/o1", "O1", "other"),
    row("series/a", "A series", "top", true),
  ],
});
const PART = { movie: [ID.rowsMovies, ID.rowsMovieGroups, ID.rowsMoviePart], series: [ID.rowsSeries, ID.rowsSeriesGroups, ID.rowsSeriesPart] } as const;
type Tab = keyof typeof PART;
// The keys of the rows of Melange of a tab on by default (Continue, Favorites), of TorrServer hidden as the field has
// it after a change; the field as a save posts it.
const ON = (tab: Tab) => tab + "/@continue " + tab + "/@mylist";
const TS_OFF = (tab: Tab) => tab + "/@torrserver:off";
const posted = (field: string) => "cat_rows=" + encodeURIComponent(field) + "&";
// The field after a change with the rows of Melange as by default: those of the server of each tab between them.
const withOwn = (movie: string, series: string) => [ON("movie"), movie, TS_OFF("movie"), ON("series"), series, TS_OFF("series")].join(" ");
// The series of LIST after a change, nothing of them changed.
const SERIES = ON("series") + " series/a " + TS_OFF("series");

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
// The rows shown of a part (.cro, under the titles of their groups, .crh); the four buttons of the n-th: to the top, up,
// down, to the bottom.
const lineOf = (page: Page, n: number, tab: Tab) => page.el(PART[tab][0]).querySelectorAll(".cro")[n]!;
const shownRows = (page: Page, tab: Tab = "movie") => Array.from(page.el(PART[tab][0]).querySelectorAll(".cro span")).map((name) => name.textContent);
const shownCheck = (page: Page, n: number, tab: Tab = "movie") => lineOf(page, n, tab).querySelector<HTMLInputElement>("input")!;
const moves = (page: Page, n: number, tab: Tab = "movie") => lineOf(page, n, tab).querySelectorAll("button");
// The titles of the groups shown of a part, the buttons of the n-th.
const heads = (page: Page, tab: Tab = "movie") => Array.from(page.el(PART[tab][0]).querySelectorAll(".crh span")).map((name) => name.textContent);
const headMoves = (page: Page, n: number, tab: Tab = "movie") => page.el(PART[tab][0]).querySelectorAll(".crh")[n]!.querySelectorAll("button");
const off = (buttons: NodeListOf<HTMLButtonElement>) => Array.from(buttons).map((button) => button.disabled);
const TOP = 0,
  UP = 1,
  DOWN = 2,
  BOTTOM = 3;
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

// The cases of the rule of the groups, the same for mel_cat_by_groups (melange_catalog_test.php of the tests of the plugin).
const GROUP_CASES: { name: string; rows: [string, string][]; want: string[] }[] = JSON.parse(readFileSync(import.meta.dir + "/groups.json", "utf8"));

describe("the rule of the groups, as mel_cat_by_groups (groups.json)", () => {
  test.each(GROUP_CASES.map((c) => [c.name, c] as const))("%s", (_name, c) => {
    expect(byGroups(c.rows.map(([key, group]) => ({ key, group })))).toEqual(c.want);
  });
});

describe("the rule of the choice, as mel_cat_user_rows: by the tab", () => {
  test.each([
    ["nothing chosen: the start rows of the tab in the order of the list", "", ["movie/a", "movie/c"], ["series/a"]],
    ["a choice with the groups mixed: each group where its first row is", "movie/b movie/a movie/c", ["movie/b", "movie/c", "movie/a"], ["series/a"]],
    ["the order of the user; none of the tab: its start rows (a choice of before 0.54.0)", "movie/c movie/b", ["movie/c", "movie/b"], ["series/a"]],
    ["a key gone from the list goes, a new row does not come; anime with the series", "movie/gone anime/d", ["movie/a", "movie/c"], ["anime/d"]],
    ["a repeat once", "movie/b movie/b", ["movie/b"], ["series/a"]],
    ["all gone: the start rows", "movie/gone", ["movie/a", "movie/c"], ["series/a"]],
    ["mixed: each tab its own order", "series/a movie/c anime/d movie/a", ["movie/c", "movie/a"], ["series/a", "anime/d"]],
  ])("%s; the rows of Melange first", (_name, stored, movie, series) => {
    expect(chosenKeys(ROWS, stored, "movie")).toEqual([...ON("movie").split(" "), ...movie]);
    expect(chosenKeys(ROWS, stored, "series")).toEqual([...ON("series").split(" "), ...series]);
  });

  test("the start rows: the rows of a group together too", () => {
    const rows = [row("movie/t1", "T1", "top", true), row("movie/g1", "G1", "genres", true), row("movie/t2", "T2", "top", true), row("movie/x", "X", "genres")];
    expect(chosenKeys(rows, "", "movie")).toEqual(["movie/@continue", "movie/@mylist", "movie/t1", "movie/t2", "movie/g1"]);
  });
});

// The same cases as for mel_cat_tab_slots (melange_catalog_test.php of the tests of the plugin).
describe("the rows of Melange, as mel_cat_tab_slots: at their places by the field", () => {
  const C = "movie/@continue",
    M = "movie/@mylist",
    T = "movie/@torrserver";
  test.each([
    ["Continue after the rows of a group: at its place, Favorites with no key right after it", "movie/a movie/@continue movie/c", "movie", [
      "movie/a", C, M, "movie/c"]],
    ["a key between the rows of one group: after the group", "movie/b movie/@mylist movie/c movie/a movie/@continue", "movie", [
      "movie/b", "movie/c", M, "movie/a", C]],
    [":off - hidden, Favorites with no key first then", "movie/a movie/@continue:off", "movie", [M, "movie/a"]],
    [":off of Favorites: Continue at its place by default", "movie/c movie/@mylist:off", "movie", [C, "movie/c"]],
    ["both off: none", "movie/c movie/@mylist:off movie/@continue:off", "movie", ["movie/c"]],
    ["a name twice: the first key counts", "movie/@continue:off movie/a movie/@continue", "movie", [M, "movie/a"]],
    ["only the keys of Melange: the start rows after them", "movie/@mylist movie/@continue", "movie", [M, C, "movie/a", "movie/c"]],
    ["the keys of each tab its own: movies", "series/@continue:off movie/a movie/@continue series/a", "movie", ["movie/a", C, M]],
    ["the keys of each tab its own: series", "series/@continue:off movie/a movie/@continue series/a", "series", ["series/@mylist", "series/a"]],
    ["unknown and broken keys of Melange go", "movie/@foo movie/@continue:x movie/@ anime/@continue series/@continue:off movie/c", "movie", [C, M, "movie/c"]],
    ["TorrServer with a key: at its place", "movie/c movie/@mylist movie/@torrserver", "movie", [C, "movie/c", M, T]],
    ["TorrServer hidden: as no key", "movie/@torrserver:off movie/c", "movie", [C, M, "movie/c"]],
  ] as const)("%s", (_name, stored, tab, want) => {
    expect(chosenKeys(ROWS, stored, tab)).toEqual([...want]);
  });

  test("the place of a row of Melange, as mel_cat_pseudo_at: after the nearest one before it in OWN there, none - first", () => {
    expect([
      ownAt(["@mylist", "top", "@continue"], "@torrserver"),
      ownAt(["top", "@continue"], "@mylist"),
      ownAt(["@mylist", "top"], "@continue"),
      ownAt(["top"], "@torrserver"),
    ]).toEqual([1, 2, 0, 0]);
  });

  test("a key of a row of Melange, as mel_cat_pseudo_key: tab, name, hidden; else none", () => {
    expect([ownKey("movie/@continue"), ownKey("series/@torrserver:off")]).toEqual([
      { tab: "movie", name: "@continue", off: false },
      { tab: "series", name: "@torrserver", off: true },
    ]);
    for (const key of ["anime/@continue", "movie/@foo", "movie/@continue:x", "movie/@continue:off:off", "movie/@Continue", "movie/a@mylist", "movie/c"])
      expect([key, ownKey(key)]).toEqual([key, null]);
  });

  test("the rows of Melange of the page: those of mel_cat_pseudo (common.php), in its order, on by default as there", () => {
    const php = readFileSync(import.meta.dir + "/../../common.php", "utf8");
    const pseudo = /function mel_cat_pseudo\(\)\s*\{([^}]*)\}/.exec(php)![1]!;
    expect([...pseudo.matchAll(/'(@[a-z]+)' => array\('on' => (true|false)/g)].map((m) => [m[1], m[2] === "true"])).toEqual(OWN.map(([name, , on]) => [name, on]));
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
    expect(groupTitles(page, "movie")).toEqual([i18n.groupOwn + " · 2/3", i18n.groupTop + " · 1/1", i18n.groupGenres + " · 1/2", i18n.groupOther + " · 0/1"]);
    expect(groupTitles(page, "series")).toEqual([i18n.groupOwn + " · 2/3", i18n.groupTop + " · 1/1", i18n.groupOther + " · 0/1"]);
    expect([...groups(page, "movie"), ...groups(page, "series")].some((group) => group.open)).toBe(false);
    for (const tab of ["movie", "series"] as const)
      expect(Array.from(groups(page, tab)[0]!.querySelectorAll("span")).map((name) => name.textContent)).toEqual([i18n.rowContinue, i18n.rowMylist, i18n.rowTorrserver]);
    expect(Array.from(groups(page, "movie")[3]!.querySelectorAll("span")).map((name) => name.textContent)).toEqual(["New group"]);
    expect(Array.from(groups(page, "series")[2]!.querySelectorAll("span")).map((name) => name.textContent)).toEqual(["D"]);
    expect([groupCheck(page, "A").checked, groupCheck(page, "B").checked, groupCheck(page, "A series").checked]).toEqual([true, false, true]);
  });

  test("the group of fresh releases: right after Top, in each part", async () => {
    const rows = [
      row("movie/g", "G", "genres"),
      row("movie/f", "F", "fresh"),
      row("movie/a", "A", "top", true),
      row("series/f", "F series", "fresh"),
      row("series/a", "A series", "top", true),
    ];
    const page = await opened(json({ ok: true, stale: false, rows }));
    const { i18n } = page.init;
    expect(groupTitles(page, "movie")).toEqual([i18n.groupOwn + " · 2/3", i18n.groupTop + " · 1/1", i18n.groupFresh + " · 0/1", i18n.groupGenres + " · 0/1"]);
    expect(groupTitles(page, "series")).toEqual([i18n.groupOwn + " · 2/3", i18n.groupTop + " · 1/1", i18n.groupFresh + " · 0/1"]);
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
    headMoves(page, 3)[UP]!.click();
    tabButton(page, "series").click();
    click(page, groupCheck(page, "D"));
    tabButton(page, "movie").click();
    expect(shownRows(page)).toEqual(["C", "A"]);
    expect(shownRows(page, "series")).toEqual(["A series", "D"]);
    page.replies.push(json({ ok: true, next: "settings?saved=1", aio: "" }));
    page.form.dispatchEvent(new page.win.Event("submit", { cancelable: true }) as unknown as Event);
    await flush();
    expect(page.calls[1]!.body).toContain(posted(withOwn("movie/c movie/a", "series/a anime/d")));
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
    expect(value(page)).toBe(withOwn("movie/a movie/c movie/b", "series/a"));
    expect(groups(page)[2]!.querySelector("summary")!.textContent).toBe(page.init.i18n.groupGenres + " · 2/2");
    expect(page.el(ID.dirty).textContent).not.toBe("");
    click(page, groupCheck(page, "D"));
    expect(shownRows(page, "series")).toEqual(["A series", "D"]);
    expect(value(page)).toBe(withOwn("movie/a movie/c movie/b", "series/a anime/d"));
  });

  test("a row turned off in the list: gone, its box in the group off; off in a group: gone from the list", async () => {
    const page = await opened(LIST);
    click(page, shownCheck(page, 1));
    expect(shownRows(page)).toEqual(["A"]);
    expect(groupCheck(page, "C").checked).toBe(false);
    click(page, groupCheck(page, "B"));
    click(page, groupCheck(page, "A"));
    expect(shownRows(page)).toEqual(["B"]);
    expect(value(page)).toBe(withOwn("movie/b", "series/a"));
  });

  test("the rows shown by groups, each under its title, in the order of the part", async () => {
    const page = await opened(LIST, "movie/c movie/new movie/a movie/b");
    const { i18n } = page.init;
    expect(heads(page)).toEqual([i18n.rowContinue, i18n.rowMylist, i18n.groupGenres, i18n.groupOther, i18n.groupTop]);
    expect(shownRows(page)).toEqual(["C", "B", "New group", "A"]);
    // A row of Melange: its title, no line of a row under it.
    expect(Array.from(page.el(ID.rowsMovies).children).map((line) => line.className)).toEqual(["crh", "crh", "crh", "cro", "cro", "crh", "cro", "crh", "cro"]);
    // The field as it came till a change.
    expect(value(page)).toBe("movie/c movie/new movie/a movie/b");
  });

  test("a row moves within its group only: to the top, up, down, to the bottom; the ones at the ends off; the bar follows", async () => {
    const page = await opened(GLIST, "movie/t1 movie/g1 movie/g2 movie/g3 movie/t2");
    expect(shownRows(page)).toEqual(["T1", "T2", "G1", "G2", "G3"]);
    expect([off(moves(page, 0)), off(moves(page, 1)), off(moves(page, 2)), off(moves(page, 3)), off(moves(page, 4))]).toEqual([
      [true, true, false, false],
      [false, false, true, true],
      [true, true, false, false],
      [false, false, false, false],
      [false, false, true, true],
    ]);
    moves(page, 3)[UP]!.click();
    expect(shownRows(page)).toEqual(["T1", "T2", "G2", "G1", "G3"]);
    expect(value(page)).toBe(withOwn("movie/t1 movie/t2 movie/g2 movie/g1 movie/g3", "series/a"));
    expect(page.el(ID.dirty).textContent).not.toBe("");
    moves(page, 2)[BOTTOM]!.click();
    expect(shownRows(page)).toEqual(["T1", "T2", "G1", "G3", "G2"]);
    moves(page, 4)[TOP]!.click();
    expect(shownRows(page)).toEqual(["T1", "T2", "G2", "G1", "G3"]);
    moves(page, 2)[DOWN]!.click();
    moves(page, 0)[DOWN]!.click();
    expect(shownRows(page)).toEqual(["T2", "T1", "G1", "G2", "G3"]);
    expect(value(page)).toBe(withOwn("movie/t2 movie/t1 movie/g1 movie/g2 movie/g3", "series/a"));
  });

  test("a group moves among the groups with its rows: to the top, up, down, to the bottom; the ones at the ends off", async () => {
    const page = await opened(GLIST, "movie/t1 movie/g1 movie/o1 movie/t2");
    const { i18n } = page.init;
    const C = i18n.rowContinue,
      F = i18n.rowMylist;
    expect(heads(page)).toEqual([C, F, i18n.groupTop, i18n.groupGenres, i18n.groupOther]);
    expect([0, 1, 2, 3, 4].map((n) => off(headMoves(page, n)))).toEqual([
      [true, true, false, false],
      [false, false, false, false],
      [false, false, false, false],
      [false, false, false, false],
      [false, false, true, true],
    ]);
    headMoves(page, 4)[TOP]!.click();
    expect(heads(page)).toEqual([i18n.groupOther, C, F, i18n.groupTop, i18n.groupGenres]);
    expect(value(page)).toBe("movie/o1 " + ON("movie") + " movie/t1 movie/t2 movie/g1 " + TS_OFF("movie") + " " + SERIES);
    headMoves(page, 0)[BOTTOM]!.click();
    expect(value(page)).toBe(withOwn("movie/t1 movie/t2 movie/g1 movie/o1", "series/a"));
    headMoves(page, 2)[DOWN]!.click();
    expect(value(page)).toBe(withOwn("movie/g1 movie/t1 movie/t2 movie/o1", "series/a"));
    headMoves(page, 4)[UP]!.click();
    expect(value(page)).toBe(withOwn("movie/g1 movie/o1 movie/t1 movie/t2", "series/a"));
    expect(page.el(ID.dirty).textContent).not.toBe("");
  });

  test("one group: its buttons all off; one row in its group: its buttons all off", async () => {
    const page = await opened(GLIST, "movie/@continue:off movie/@mylist:off movie/g1");
    expect([off(headMoves(page, 0)), off(moves(page, 0))]).toEqual([
      [true, true, true, true],
      [true, true, true, true],
    ]);
  });

  test("the buttons for a screen reader: the move and what moves, an icon each", async () => {
    const page = await opened(GLIST, "movie/t1 movie/g1");
    const { i18n } = page.init;
    const labels = (buttons: NodeListOf<HTMLButtonElement>) => Array.from(buttons).map((button) => button.getAttribute("aria-label"));
    expect(labels(headMoves(page, 3))).toEqual([i18n.rowsTop, i18n.rowsUp, i18n.rowsDown, i18n.rowsBottom].map((text) => text + ": " + i18n.groupGenres));
    expect(labels(headMoves(page, 0))).toEqual([i18n.rowsTop, i18n.rowsUp, i18n.rowsDown, i18n.rowsBottom].map((text) => text + ": " + i18n.rowContinue));
    expect(labels(moves(page, 0))).toEqual([i18n.rowsTop, i18n.rowsUp, i18n.rowsDown, i18n.rowsBottom].map((text) => text + ": T1"));
    expect(Array.from(moves(page, 0)).every((button) => button.textContent === "" && button.querySelector("svg[aria-hidden=true] path") !== null)).toBe(true);
  });

  test("a row turned on: last in its group; the first of a group not shown: its group last in the part", async () => {
    const page = await opened(GLIST, "movie/g1 movie/t1");
    click(page, groupCheck(page, "G3"));
    expect(value(page)).toBe(withOwn("movie/g1 movie/g3 movie/t1", "series/a"));
    click(page, groupCheck(page, "O1"));
    expect(value(page)).toBe(withOwn("movie/g1 movie/g3 movie/t1 movie/o1", "series/a"));
    const { i18n } = page.init;
    expect(heads(page)).toEqual([i18n.rowContinue, i18n.rowMylist, i18n.groupGenres, i18n.groupTop, i18n.groupOther]);
  });

  test("the last row of a group off: its group goes", async () => {
    const page = await opened(GLIST, "movie/g1 movie/t1");
    click(page, shownCheck(page, 0));
    expect(heads(page)).toEqual([page.init.i18n.rowContinue, page.init.i18n.rowMylist, page.init.i18n.groupTop]);
    expect(value(page)).toBe(withOwn("movie/t1", "series/a"));
  });

  test("the last row of a part can't go, the rows of Melange shown or not: stays on, its text under the screen; the next change takes the text away", async () => {
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
    expect(value(page)).toBe(withOwn("movie/b movie/c", "series/a"));
    // The series: their own last row, their own text.
    click(page, shownCheck(page, 0, "series"));
    expect(shownCheck(page, 0, "series").checked).toBe(true);
    expect(lines(page.el(ID.saveResult))).toEqual([["warn", page.init.i18n.rowsLastSeries]]);
    expect(value(page)).toBe(withOwn("movie/b movie/c", "series/a"));
  });

  test("a choice of before 0.54.0, only movies: the series part with its start rows; the field as it came till a change", async () => {
    const page = await opened(LIST, "movie/c");
    expect(shownRows(page)).toEqual(["C"]);
    expect(shownRows(page, "series")).toEqual(["A series"]);
    expect(value(page)).toBe("movie/c");
    click(page, groupCheck(page, "D"));
    expect(value(page)).toBe(withOwn("movie/c", "series/a anime/d"));
  });

  test("a choice of movies and series mixed: each part its order; the field then by part", async () => {
    const page = await opened(LIST, "series/a movie/c anime/d movie/a");
    expect(shownRows(page)).toEqual(["C", "A"]);
    expect(shownRows(page, "series")).toEqual(["A series", "D"]);
    headMoves(page, 3, "series")[UP]!.click();
    expect(value(page)).toBe(withOwn("movie/c movie/a", "anime/d series/a"));
  });

  test("the chosen ones saved by the form: the keys by spaces, in order; a save that stays leaves no change", async () => {
    const page = await opened(LIST);
    headMoves(page, 3)[UP]!.click();
    page.replies.push(json({ ok: true, next: "settings?saved=1", aio: "update", notes: [{ level: "ok", msg: "x" }] }), json({ ok: true, msg: "ok", skip: true }));
    page.form.dispatchEvent(new page.win.Event("submit", { cancelable: true }) as unknown as Event);
    await flush();
    expect(page.calls[1]!.body).toContain(posted(withOwn("movie/c movie/a", "series/a")));
    expect(page.el(ID.dirty).textContent).toBe("");
  });
});

describe("the rows as the page came (cat_rows_was): the save keeps the stored ones when they are posted back", () => {
  // The page with the choice stored in the field and as it came.
  async function cameWith(stored: string): Promise<Page> {
    const page = open("filled", {
      before: (doc) => {
        (doc.getElementById(ID.rowsValue) as HTMLInputElement).setAttribute("value", stored);
        (doc.getElementById(ID.rowsWas) as HTMLInputElement).setAttribute("value", stored);
      },
    });
    page.replies.push(LIST);
    await go(page);
    return page;
  }
  async function submit(page: Page): Promise<void> {
    page.form.dispatchEvent(new page.win.Event("submit", { cancelable: true }) as unknown as Event);
    await flush();
  }
  const was = (page: Page) => page.field("cat_rows_was").value;

  test("untouched: both posted the same, right after the field; no change for the bar", async () => {
    const page = await cameWith("movie/c movie/b");
    expect(page.el(ID.dirty).textContent).toBe("");
    page.replies.push(json({ ok: true, next: "settings?saved=1", aio: "" }));
    await submit(page);
    expect(page.calls[1]!.body).toContain("&cat_rows=movie%2Fc%20movie%2Fb&cat_rows_was=movie%2Fc%20movie%2Fb&");
  });

  test("changed: the new rows posted, the ones the page came with as they were", async () => {
    const page = await cameWith("movie/c movie/b");
    moves(page, 1)[UP]!.click();
    expect(page.el(ID.dirty).textContent).not.toBe("");
    page.replies.push(json({ ok: true, next: "settings?saved=1", aio: "" }));
    await submit(page);
    expect(page.calls[1]!.body).toContain("&" + posted(withOwn("movie/b movie/c", "series/a")) + "cat_rows_was=movie%2Fc%20movie%2Fb&");
  });

  test("a save that stays: the rows posted are now the ones the page came with; a failed one: as they were", async () => {
    const page = await cameWith("movie/c movie/b");
    moves(page, 1)[UP]!.click();
    page.replies.push(json({ ok: false, errors: [{ msg: "No", field: null }] }));
    await submit(page);
    expect(was(page)).toBe("movie/c movie/b");
    page.replies.push(json({ ok: true, next: "settings?saved=1", aio: "", notes: [{ level: "ok", msg: "x" }] }));
    await submit(page);
    const saved = withOwn("movie/b movie/c", "series/a");
    expect([was(page), value(page), page.el(ID.dirty).textContent]).toEqual([saved, saved, ""]);
    moves(page, 1)[UP]!.click();
    page.replies.push(json({ ok: true, next: "settings?saved=1", aio: "", notes: [{ level: "ok", msg: "x" }] }));
    await submit(page);
    expect(page.calls[3]!.body).toContain("&" + posted(withOwn("movie/c movie/b", "series/a")) + "cat_rows_was=" + encodeURIComponent(saved) + "&");
  });
});

describe("the checkbox in the title of a group", () => {
  // The checkbox in the title of the n-th group of a part; of each group of movies: [checked, indeterminate].
  const groupAll = (page: Page, n: number, tab: Tab = "movie") => groups(page, tab)[n]!.querySelector<HTMLInputElement>("summary input")!;
  const state = (page: Page) => groups(page).map((_group, n) => [groupAll(page, n).checked, groupAll(page, n).indeterminate]);

  test("on: all the rows of the group on; some: indeterminate; none: off; it follows a row; its name for a screen reader", async () => {
    const page = await opened(LIST);
    expect(state(page)).toEqual([
      [false, true],
      [true, false],
      [false, true],
      [false, false],
    ]);
    click(page, groupCheck(page, "B"));
    click(page, groupCheck(page, "New group"));
    expect(state(page)).toEqual([
      [false, true],
      [true, false],
      [true, false],
      [true, false],
    ]);
    expect(groupAll(page, 2).getAttribute("aria-label")).toBe(page.init.i18n.rowsGroupAll + ": " + page.init.i18n.groupGenres);
    expect(groupAll(page, 0).getAttribute("aria-label")).toBe(page.init.i18n.rowsGroupAll + ": " + page.init.i18n.groupOwn);
  });

  test("not all on: the rest go on, a group not shown last in the part, in the order of the group; the group stays closed; the bar follows", async () => {
    const page = await opened(LIST, "movie/a");
    click(page, groupAll(page, 2));
    expect(value(page)).toBe(withOwn("movie/a movie/b movie/c", "series/a"));
    expect(shownRows(page)).toEqual(["A", "B", "C"]);
    expect(groups(page)[2]!.querySelector("summary")!.textContent).toBe(page.init.i18n.groupGenres + " · 2/2");
    expect(state(page)[2]).toEqual([true, false]);
    // A browser does not open the details on a click on a control of the summary; happy-dom would on a click, so
    // here only the change: the script itself does not open it.
    expect(groups(page)[2]!.open).toBe(false);
    expect(page.el(ID.dirty).textContent).not.toBe("");
  });

  test("some on: only the rest go on, after the rows of the group chosen before", async () => {
    const page = await opened(LIST, "movie/c movie/a");
    click(page, groupAll(page, 2));
    expect(value(page)).toBe(withOwn("movie/c movie/b movie/a", "series/a"));
  });

  test("all on: all go off, the other rows of the part stay in their order", async () => {
    const page = await opened(LIST, "movie/b movie/a movie/c");
    click(page, groupAll(page, 2));
    expect(value(page)).toBe(withOwn("movie/a", "series/a"));
    expect(shownRows(page)).toEqual(["A"]);
    expect([groupCheck(page, "B").checked, groupCheck(page, "C").checked]).toEqual([false, false]);
    expect(state(page)[2]).toEqual([false, false]);
  });

  test("all off would leave the part empty: nothing goes, the box on again, the text of the last row under the screen", async () => {
    const page = await opened(LIST, "movie/b movie/c anime/d");
    click(page, groupAll(page, 2));
    expect(state(page)[2]).toEqual([true, false]);
    expect(shownRows(page)).toEqual(["B", "C"]);
    expect(value(page)).toBe("movie/b movie/c anime/d");
    expect(lines(page.el(ID.saveResult))).toEqual([["warn", page.init.i18n.rowsLastMovie]]);
    expect(page.el(ID.bar).hidden).toBe(false);
    // The series: their own text.
    click(page, groupAll(page, 2, "series"));
    expect(groupAll(page, 2, "series").checked).toBe(true);
    expect(lines(page.el(ID.saveResult))).toEqual([["warn", page.init.i18n.rowsLastSeries]]);
    expect(value(page)).toBe("movie/b movie/c anime/d");
  });
});

describe("the rows of Melange: Continue, Favorites, On TorrServer", () => {
  // The checkbox of a row of Melange in "Melange rows" of the part.
  const ownCheck = (page: Page, n: number, tab: Tab = "movie") => groups(page, tab)[0]!.querySelectorAll<HTMLInputElement>("label input")[n]!;
  const ownAll = (page: Page, tab: Tab = "movie") => groups(page, tab)[0]!.querySelector<HTMLInputElement>("summary input")!;
  const CONTINUE = 0,
    FAVORITES = 1,
    TORRSERVER = 2;
  // "Running torrents" of the page: "No torrents" (nt2) or "Allow" (nt1); happy-dom leaves the other one checked.
  function noTorrents(page: Page, on: boolean): void {
    const [allow, none] = ["nt1", "nt2"].map((id) => page.doc.getElementById(id) as HTMLInputElement);
    allow!.checked = !on;
    none!.checked = on;
    (on ? none! : allow!).dispatchEvent(new page.win.Event("change", { bubbles: true }) as unknown as Event);
  }

  test("nothing chosen: Continue and Favorites first in Shown, a title each without rows, TorrServer not; the field untouched", async () => {
    const page = await opened(LIST);
    const { i18n } = page.init;
    expect(heads(page)).toEqual([i18n.rowContinue, i18n.rowMylist, i18n.groupTop, i18n.groupGenres]);
    expect(heads(page, "series")).toEqual([i18n.rowContinue, i18n.rowMylist, i18n.groupTop]);
    expect(Array.from(page.el(ID.rowsMovies).children).map((line) => line.className)).toEqual(["crh", "crh", "crh", "cro", "crh", "cro"]);
    expect([CONTINUE, FAVORITES, TORRSERVER].map((n) => ownCheck(page, n).checked)).toEqual([true, true, false]);
    expect(value(page)).toBe("");
    expect(page.el(ID.dirty).textContent).toBe("");
  });

  test("they move among the groups as a group does; the field then has them at their places", async () => {
    const page = await opened(LIST);
    const { i18n } = page.init;
    headMoves(page, 0)[DOWN]!.click();
    expect(heads(page)).toEqual([i18n.rowMylist, i18n.rowContinue, i18n.groupTop, i18n.groupGenres]);
    expect(value(page)).toBe("movie/@mylist movie/@continue movie/a movie/c " + TS_OFF("movie") + " " + SERIES);
    expect(page.el(ID.dirty).textContent).not.toBe("");
    headMoves(page, 0)[BOTTOM]!.click();
    expect(heads(page)).toEqual([i18n.rowContinue, i18n.groupTop, i18n.groupGenres, i18n.rowMylist]);
    expect(off(headMoves(page, 3))).toEqual([false, false, true, true]);
    expect(value(page)).toBe("movie/@continue movie/a movie/c movie/@mylist " + TS_OFF("movie") + " " + SERIES);
  });

  test("off in Melange rows: gone from Shown, :off at the end of its tab; on again: back at its place by default", async () => {
    const page = await opened(LIST, "movie/a movie/@continue movie/c");
    const { i18n } = page.init;
    expect(heads(page)).toEqual([i18n.groupTop, i18n.rowContinue, i18n.rowMylist, i18n.groupGenres]);
    click(page, ownCheck(page, CONTINUE));
    expect(heads(page)).toEqual([i18n.groupTop, i18n.rowMylist, i18n.groupGenres]);
    expect(groupTitles(page, "movie")[0]).toBe(i18n.groupOwn + " · 1/3");
    expect(value(page)).toBe("movie/a movie/@mylist movie/c movie/@continue:off " + TS_OFF("movie") + " " + SERIES);
    click(page, ownCheck(page, CONTINUE));
    expect(heads(page)).toEqual([i18n.rowContinue, i18n.groupTop, i18n.rowMylist, i18n.groupGenres]);
    expect(value(page)).toBe("movie/@continue movie/a movie/@mylist movie/c " + TS_OFF("movie") + " " + SERIES);
    // The other tab as it was.
    expect(heads(page, "series")).toEqual([i18n.rowContinue, i18n.rowMylist, i18n.groupTop]);
  });

  test("On TorrServer: off by default; on: right after Favorites wherever it is, Favorites off: after Continue, both: first", async () => {
    const page = await opened(LIST, "movie/a movie/@mylist movie/c");
    const { i18n } = page.init;
    const TS = i18n.rowTorrserver;
    click(page, ownCheck(page, TORRSERVER));
    expect(heads(page)).toEqual([i18n.rowContinue, i18n.groupTop, i18n.rowMylist, TS, i18n.groupGenres]);
    expect(value(page)).toBe("movie/@continue movie/a movie/@mylist movie/@torrserver movie/c " + SERIES);
    click(page, ownCheck(page, FAVORITES));
    click(page, ownCheck(page, TORRSERVER));
    click(page, ownCheck(page, TORRSERVER));
    expect(heads(page)).toEqual([i18n.rowContinue, TS, i18n.groupTop, i18n.groupGenres]);
    click(page, ownCheck(page, CONTINUE));
    click(page, ownCheck(page, TORRSERVER));
    click(page, ownCheck(page, TORRSERVER));
    expect(heads(page)).toEqual([TS, i18n.groupTop, i18n.groupGenres]);
    expect(value(page)).toBe("movie/@torrserver movie/a movie/c movie/@continue:off movie/@mylist:off " + SERIES);
    // Only the rows of the server in the count of the tab.
    expect(tabButton(page, "movie").textContent).toBe("Фильмы · 2");
  });

  test("the checkbox of Melange rows: the rest on, each at its place; all on: all off, never refused", async () => {
    const page = await opened(LIST);
    const { i18n } = page.init;
    expect([ownAll(page).checked, ownAll(page).indeterminate]).toEqual([false, true]);
    click(page, ownAll(page));
    expect(heads(page)).toEqual([i18n.rowContinue, i18n.rowMylist, i18n.rowTorrserver, i18n.groupTop, i18n.groupGenres]);
    expect([ownAll(page).checked, ownAll(page).indeterminate]).toEqual([true, false]);
    click(page, ownAll(page));
    expect(heads(page)).toEqual([i18n.groupTop, i18n.groupGenres]);
    expect(value(page)).toBe("movie/a movie/c movie/@continue:off movie/@mylist:off movie/@torrserver:off " + SERIES);
    expect(lines(page.el(ID.saveResult))).toEqual([]);
  });

  test("only the rows of Melange can't stay: the last row of the server stays on, the text of the last row", async () => {
    const page = await opened(LIST, "movie/c");
    click(page, groupCheck(page, "C"));
    expect(groupCheck(page, "C").checked).toBe(true);
    expect(lines(page.el(ID.saveResult))).toEqual([["warn", page.init.i18n.rowsLastMovie]]);
    expect(value(page)).toBe("movie/c");
  });

  test("No torrents: TorrServer gone from Melange rows and Shown, its key as it was; Allow: back", async () => {
    const page = await opened(LIST, "movie/a movie/@torrserver movie/c");
    const { i18n } = page.init;
    const TS = i18n.rowTorrserver;
    expect(heads(page)).toEqual([i18n.rowContinue, i18n.rowMylist, i18n.groupTop, TS, i18n.groupGenres]);
    noTorrents(page, true);
    expect(heads(page)).toEqual([i18n.rowContinue, i18n.rowMylist, i18n.groupTop, i18n.groupGenres]);
    expect(ownCheck(page, TORRSERVER).parentElement!.hidden).toBe(true);
    expect(groupTitles(page, "movie")[0]).toBe(i18n.groupOwn + " · 2/2");
    expect(off(headMoves(page, 3))).toEqual([false, false, true, true]);
    // A move and the checkbox of the group: the row of TorrServer stays right after the one it was after.
    headMoves(page, 0)[BOTTOM]!.click();
    expect(heads(page)).toEqual([i18n.rowMylist, i18n.groupTop, i18n.groupGenres, i18n.rowContinue]);
    // The series had no key of TorrServer: none.
    expect(value(page)).toBe("movie/@mylist movie/a movie/@torrserver movie/c movie/@continue " + ON("series") + " series/a");
    click(page, ownAll(page));
    expect(value(page)).toBe("movie/a movie/@torrserver movie/c movie/@continue:off movie/@mylist:off " + ON("series") + " series/a");
    noTorrents(page, false);
    expect(heads(page)).toEqual([i18n.groupTop, TS, i18n.groupGenres]);
    expect(ownCheck(page, TORRSERVER).parentElement!.hidden).toBe(false);
    expect(groupTitles(page, "movie")[0]).toBe(i18n.groupOwn + " · 1/3");
  });

  test("No torrents: the key of TorrServer as it was - hidden: :off last, none: none; Allow: :off for it again", async () => {
    const page = await opened(LIST, "movie/@torrserver:off movie/a movie/c");
    noTorrents(page, true);
    headMoves(page, 0)[DOWN]!.click();
    expect(value(page)).toBe("movie/@mylist movie/@continue movie/a movie/c movie/@torrserver:off " + ON("series") + " series/a");
    noTorrents(page, false);
    headMoves(page, 0)[DOWN]!.click();
    expect(value(page)).toBe("movie/@continue movie/@mylist movie/a movie/c movie/@torrserver:off " + SERIES);
  });

  // The keys of the same screens by "Group order" on the Dune (mel_catv_lcfg_order_apply of its items got, checked in
  // melange_catalogview_test.php) and by the page: both read to the same slots, as mel_cat_tab_slots reads them there.
  const OWN_CASES: {
    rows: [string, string, boolean][];
    cases: { name: string; stored: string; torrents: boolean; got: string[]; dune: string; page: string; want: string[] }[];
  } = JSON.parse(readFileSync(import.meta.dir + "/own.json", "utf8"));
  test.each(OWN_CASES.cases.map((c) => [c.name, c] as const))("the keys of the Dune and of the page, one screen (own.json): %s", async (_name, c) => {
    const rows = OWN_CASES.rows.map(([key, group, home]) => row(key, key, group, home));
    expect([chosenKeys(rows, c.dune, "movie"), chosenKeys(rows, c.page, "movie")]).toEqual([c.want, c.want]);
    // The page on the keys of the Dune: a move there and back, the field as the page writes that screen.
    const page = await opened(json({ ok: true, stale: false, rows }), c.dune);
    if (!c.torrents) noTorrents(page, true);
    headMoves(page, 0)[DOWN]!.click();
    headMoves(page, 1)[UP]!.click();
    expect(value(page).split(" ").filter((key) => key.startsWith("movie/")).join(" ")).toBe(c.page);
  });

  test("On TorrServer on, not in the order of OWN: right after the nearest one before it there (Favorites), as on the Dune", async () => {
    const page = await opened(LIST, "movie/@mylist movie/a movie/@continue");
    const { i18n } = page.init;
    expect(heads(page)).toEqual([i18n.rowMylist, i18n.groupTop, i18n.rowContinue]);
    click(page, ownCheck(page, TORRSERVER));
    expect(heads(page)).toEqual([i18n.rowMylist, i18n.rowTorrserver, i18n.groupTop, i18n.rowContinue]);
    expect(value(page)).toBe("movie/@mylist movie/@torrserver movie/a movie/@continue " + SERIES);
  });
});
