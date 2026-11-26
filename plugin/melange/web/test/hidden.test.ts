// "Скрытые" of "Фильтр каталога" (#hide, 0.65.0): the list of #init.hidden (filled: a movie and a series, the other
// fixtures none) - a row each with "Unhide" (a=title_unhide: the key posted, the list of the answer shown), an empty
// list - its line, no list (a file of a later Melange) - why; held while a save goes; the failures of a request.

import { afterEach, describe, expect, test } from "bun:test";
import { CLS, ID } from "../src/dom";
import { cleanup, flush, json, later, lines, NET, open, type Page, TOKEN } from "./harness";

afterEach(cleanup);

const MATRIX = { k: "movie:tt0133093", name: "Матрица", line: "Фильм, 1999" };
const BREAKING = { k: "series:tt0903747", name: "Во все тяжкие", line: "Сериал, 2008" };

// The rows as seen: the name, the line, the button.
const rows = (page: Page) =>
  Array.from(page.el(ID.hiddenList).children).map((row) => [
    row.querySelector("." + CLS.cardTitle)!.textContent,
    row.querySelector("." + CLS.value)!.textContent,
    row.querySelector("button")!.textContent,
  ]);
const buttons = (page: Page) => Array.from(page.el(ID.hiddenList).querySelectorAll<HTMLButtonElement>("button"));
// #init.hidden replaced before the script.
const withHidden = (hidden: unknown) => ({
  before: (doc: Document) => {
    const el = doc.getElementById(ID.init)!;
    const init = JSON.parse(el.textContent || "");
    init.hidden = hidden;
    el.textContent = JSON.stringify(init);
  },
});

describe("hidden titles", () => {
  test("filled: a row each in order, its line, Unhide; no line under them", () => {
    const page = open("filled");
    const t = page.init.i18n;
    expect(rows(page)).toEqual([["Матрица", "Фильм, 1999", t.hiddenReturn], ["Во все тяжкие", "Сериал, 2008", t.hiddenReturn]]);
    expect([page.el(ID.hiddenList).hidden, lines(page.el(ID.hiddenResult))]).toEqual([false, []]);
    for (const button of buttons(page)) expect([button.type, button.className]).toEqual(["button", CLS.check + " " + CLS.inline]);
  });

  test("none hidden: no rows, the line of none (the line of PHP without the script gone)", () => {
    const page = open("empty");
    expect([rows(page), page.el(ID.hiddenList).hidden, lines(page.el(ID.hiddenResult))]).toEqual([[], true,
      [["hint", page.init.i18n.hiddenNone]]]);
  });

  test("without the script: the line of PHP stays", () => {
    const page = open("filled", { start: false });
    expect([rows(page), lines(page.el(ID.hiddenResult)).length]).toEqual([[], 1]);
  });

  test("no list (a file of a later Melange): why, no rows", () => {
    const page = open("filled", withHidden({ items: [], msg: "Список скрытых записан более новой версией Melange: обновите плагин" }));
    expect([rows(page), lines(page.el(ID.hiddenResult))]).toEqual([[],
      [["warn", "Список скрытых записан более новой версией Melange: обновите плагин"]]]);
  });

  test("a name as text, never as markup", () => {
    const page = open("filled", withHidden({ items: [{ k: "movie:tt1", name: "<b>Фильм</b> & ко", line: "Фильм" }], msg: "" }));
    expect(page.el(ID.hiddenList).querySelector("." + CLS.cardTitle)!.innerHTML).toBe("&lt;b&gt;Фильм&lt;/b&gt; &amp; ко");
  });

  test("Unhide: the key posted as a save; while it goes - the line, every button held; done - the line of the answer, its list", async () => {
    const page = open("filled");
    const reply = later();
    page.replies.push(reply.reply);
    buttons(page)[0]!.click();
    expect(page.calls.map((call) => [call.method, call.url, call.body, call.contentType])).toEqual([
      ["POST", TOKEN + "&a=title_unhide&ajax=1", "k=movie%3Att0133093", "application/x-www-form-urlencoded"]]);
    expect([lines(page.el(ID.hiddenResult)), buttons(page).map((button) => button.disabled)]).toEqual([
      [["hint", page.init.i18n.saving]], [true, true]]);
    expect(page.el(ID.bar).querySelector<HTMLButtonElement>("." + CLS.save)!.disabled).toBe(true);
    // A second click while it goes: nothing.
    buttons(page)[1]!.click();
    expect(page.calls.length).toBe(1);
    reply.resolve(json({ ok: true, msg: "«Матрица» снова в каталоге", items: [BREAKING] }));
    await flush();
    expect([rows(page), lines(page.el(ID.hiddenResult)), buttons(page).map((button) => button.disabled)]).toEqual([
      [["Во все тяжкие", "Сериал, 2008", page.init.i18n.hiddenReturn]], [["ok", "«Матрица» снова в каталоге"]], [false]]);
  });

  test("Unhide of the last one: the list empty, the line of the answer and of none", async () => {
    const page = open("filled", withHidden({ items: [MATRIX], msg: "" }));
    page.replies.push(json({ ok: true, msg: "«Матрица» снова в каталоге", items: [] }));
    page.el(ID.hiddenList).querySelector("button")!.click();
    await flush();
    expect([rows(page), page.el(ID.hiddenList).hidden, lines(page.el(ID.hiddenResult))]).toEqual([[], true,
      [["ok", "«Матрица» снова в каталоге"], ["hint", page.init.i18n.hiddenNone]]]);
  });

  test("no such title any more (ok, no line): the list of the answer", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: true, msg: "", items: [BREAKING] }));
    buttons(page)[0]!.click();
    await flush();
    expect([rows(page).length, lines(page.el(ID.hiddenResult))]).toEqual([1, []]);
  });

  test("refused: the line why; with a list - it shown, without - the rows as they were", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: false, msg: "Не сохранено: ошибка записи на Дюне", items: [MATRIX, BREAKING] }));
    buttons(page)[0]!.click();
    await flush();
    expect([rows(page).length, lines(page.el(ID.hiddenResult))]).toEqual([2, [["err", "Не сохранено: ошибка записи на Дюне"]]]);
    page.replies.push(json({ ok: false, msg: "Список скрытых на Дюне не читается" }));
    buttons(page)[1]!.click();
    await flush();
    expect([rows(page).length, lines(page.el(ID.hiddenResult)), buttons(page).map((button) => button.disabled)]).toEqual([2,
      [["err", "Список скрытых на Дюне не читается"]], [false, false]]);
  });

  test("no connection, 403: the lines of the page; after 403 every button held", async () => {
    const page = open("filled");
    page.replies.push(NET);
    buttons(page)[0]!.click();
    await flush();
    expect(lines(page.el(ID.hiddenResult))).toEqual([["err", page.init.i18n.noConnection]]);
    page.replies.push({ text: "403 Forbidden", status: 403 });
    buttons(page)[0]!.click();
    await flush();
    expect([lines(page.el(ID.hiddenResult)), buttons(page).map((button) => button.disabled), rows(page).length]).toEqual([
      [["err", page.init.i18n.linkExpired]], [true, true], 2]);
  });
});
