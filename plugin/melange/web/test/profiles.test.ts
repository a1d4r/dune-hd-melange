// The editor of the source profiles (#pe, 0.58.0): PHP makes it with "Source profiles" on (prof: the page of the one
// in use, profd: of one not in use, profnew: of a new one), the CSS shows it while that is on. The list of #init - a
// link each to the page of the profile, "+ New profile"; a link not followed while the form has something to save;
// "Make it current" (a=profile_use), "Delete" (a=profile_del, asked first) - held then too; done: the page anew; a
// save of the profile: its place, name and "in use" in the fields of the save, the list of the answer, aio_sync of it.

import { afterEach, describe, expect, test } from "bun:test";
import { readFileSync } from "fs";
import { CLS, EDITOR, ID, NAME } from "../src/dom";
import { cleanup, flush, json, later, lines, NET, open, type Page, TOKEN } from "./harness";

afterEach(cleanup);

const POST = { method: "POST", contentType: "application/x-www-form-urlencoded" };
const url = (a: string) => TOKEN + "&a=" + a + "&ajax=1";
const pageOf = (p: string) => TOKEN + "&p=" + p + "#src";
const OWN = "Свой & <конфиг>";
// The list of the fixtures, as PHP sends it.
const LIST = [
  { name: "Debrid", line: "Debrid · aiostreams.12312023.xyz · jacred.kompotik.com", now: false, ready: true },
  { name: "NAS", line: "Торренты · 192.0.2.10 · jacred.kompotik.com", now: false, ready: false },
  { name: OWN, line: "Свой конфиг · aio.example · jacred.kompotik.com", now: true, ready: true },
];
const answer = (ok: boolean, msg: string, list: unknown[] = LIST) => json({ ok: ok, msg: msg, profiles: { list: list, max: 8 } });

const rows = (page: Page) => Array.from(page.el(EDITOR.list).children) as HTMLElement[];
// A row as seen: its title with the marks, the line under it, the link, the one of the page.
const seen = (row: HTMLElement) => [
  Array.from(row.querySelectorAll("." + CLS.cardTitle + ", ." + CLS.cardTitle + " > span")).map((span) => span.childNodes[0]!.textContent),
  (row.querySelector("." + CLS.rowText + " > ." + CLS.value) || { textContent: null }).textContent,
  row.getAttribute("href"),
  row.getAttribute("aria-current"),
];
const field = (page: Page, id: string) => page.el<HTMLInputElement>(id).value;
// A click as a browser dispatches it -> false: the link is not followed (its default prevented).
const follow = (page: Page, el: HTMLElement) => el.dispatchEvent(new page.win.MouseEvent("click", { bubbles: true, cancelable: true }) as unknown as Event);

describe("the editor", () => {
  test("none without the setting (a page of before): nothing of it, the script as before", () => {
    const page = open("filled");
    expect([page.doc.getElementById(EDITOR.block), page.doc.querySelector("[name=prof]")]).toEqual([null, null]);
  });

  test("the CSS: the block hidden by the radio of off, not by that of on", () => {
    const css = readFileSync(import.meta.dir + "/../../cgi/settings.css", "utf8").split("\n");
    const hides = css.find((line) => line.includes("#nt2:checked~* .xa"))!.replace(/\{.*/, "").split(",");
    const page = open("prof", { start: false });
    const block = page.el(EDITOR.block);
    expect(page.doc.querySelector<HTMLInputElement>("#spf2")!.checked).toBe(true);
    expect(hides.some((selector) => block.matches(selector))).toBe(false);
    page.choose(NAME.srcProfiles, "off");
    expect(hides.some((selector) => block.matches(selector))).toBe(true);
  });

  test("a new one: the line by the kind chosen (the CSS) - the own link makes no config on the server", () => {
    const css = readFileSync(import.meta.dir + "/../../cgi/settings.css", "utf8").split("\n");
    const hides = css.find((line) => line.includes("#nt2:checked~* .xa"))!.replace(/\{.*/, "").split(",");
    const page = open("profnew", { start: false });
    const shown = () =>
      Array.from(page.doc.getElementById("pen")!.children)
        .filter((span) => !hides.some((selector) => span.matches(selector)))
        .map((span) => span.textContent);
    // The fixture: a new one of the source in use, the own link.
    expect(shown()).toEqual(["Новый профиль со своей ссылкой: конфиг на сервере не создаётся."]);
    page.choose(NAME.watch, "p2p");
    expect(shown()).toEqual(["Новый профиль: на сервере создастся свой конфиг."]);
    page.choose(NAME.watch, "debrid");
    expect(shown()).toEqual(["Новый профиль: на сервере создастся свой конфиг."]);
    page.choose(NAME.watch, "own");
    expect(shown()).toEqual(["Новый профиль со своей ссылкой: конфиг на сервере не создаётся."]);
  });

  test("the list: a link each to the page of the profile, the one of the page marked, now and no config; + New profile", () => {
    const page = open("prof");
    const t = page.init.i18n;
    expect(rows(page).map(seen)).toEqual([
      [["Debrid"], LIST[0]!.line, pageOf("0"), null],
      [["NAS", t.profileNoConf], LIST[1]!.line, pageOf("1"), null],
      [[OWN, t.profileNow], LIST[2]!.line, pageOf("2"), "page"],
      [[t.profileNew], null, pageOf("new"), null],
    ]);
    // The name as text, never as markup.
    expect(rows(page)[2]!.querySelector("." + CLS.cardTitle)!.innerHTML.startsWith("Свой &amp; &lt;конфиг&gt;")).toBe(true);
  });

  test("the one in use: Delete, no Make it current; the fields of the save as PHP put them", () => {
    const page = open("prof");
    expect([page.el(EDITOR.buttons).hidden, page.el(EDITOR.use).hidden, page.el(EDITOR.del).hidden]).toEqual([false, true, false]);
    expect([field(page, EDITOR.sel), field(page, EDITOR.was), field(page, EDITOR.now), field(page, EDITOR.name)]).toEqual(["2", OWN, "1", OWN]);
  });

  test("one not in use (profd): both buttons; its row marked", () => {
    const page = open("profd");
    expect([page.el(EDITOR.use).hidden, page.el(EDITOR.del).hidden, rows(page).map((row) => row.getAttribute("aria-current"))])
      .toEqual([false, false, ["page", null, null, null]]);
    expect([field(page, EDITOR.sel), field(page, EDITOR.now)]).toEqual(["0", ""]);
  });

  test("a new one (profnew): no buttons, + New profile marked, no name", () => {
    const page = open("profnew");
    expect([page.el(EDITOR.buttons).hidden, rows(page).map((row) => row.getAttribute("aria-current")), field(page, EDITOR.name)])
      .toEqual([true, [null, null, null, "page"], ""]);
  });

  test("the profiles full: no + New profile, the line of it", () => {
    const page = open("prof", {
      before: (doc) => {
        const el = doc.getElementById(ID.init)!;
        const init = JSON.parse(el.textContent || "");
        init.profiles.max = 3;
        el.textContent = JSON.stringify(init);
      },
    });
    const last = rows(page)[3]!;
    expect([rows(page).length, last.tagName, last.textContent]).toEqual([4, "P", page.init.i18n.profilesFull]);
  });
});

describe("something to save", () => {
  test("a link not followed, the line why; the buttons disabled; no change - followed again, the line gone", () => {
    const page = open("profd");
    const link = rows(page)[1]!;
    expect(follow(page, link)).toBe(true);
    page.type(EDITOR.name, "Debrid 2");
    expect(follow(page, link)).toBe(false);
    expect([page.el(EDITOR.dirty).hidden, page.el(EDITOR.dirty).textContent]).toEqual([false, page.init.i18n.profileDirty]);
    expect([page.el<HTMLButtonElement>(EDITOR.use).disabled, page.el<HTMLButtonElement>(EDITOR.del).disabled]).toEqual([true, true]);
    page.type(EDITOR.name, "Debrid");
    expect([follow(page, link), page.el(EDITOR.dirty).hidden, page.el<HTMLButtonElement>(EDITOR.use).disabled]).toEqual([true, true, false]);
  });

  test("the name of the profile is a change of the bar", () => {
    const page = open("profd");
    expect(page.el(ID.bar).hidden).toBe(true);
    page.type(EDITOR.name, "Другое");
    expect([page.el(ID.bar).hidden, page.el(ID.dirty).textContent]).toEqual([false, "1 изменение"]);
  });
});

describe("Make it current, Delete", () => {
  test("Make it current -> a=profile_use of its place and name, done: its page anew", async () => {
    const page = open("profd");
    page.replies.push(answer(true, "Профиль «Debrid» теперь текущий"));
    await page.click(EDITOR.use);
    expect(page.calls).toEqual([{ ...POST, url: url("profile_use"), body: "i=0&was=Debrid" }]);
    expect(page.replaced).toEqual([pageOf("0")]);
  });

  test("refused: its line, the list of the answer, the page stays", async () => {
    const page = open("profd");
    const list = LIST.slice(1);
    page.replies.push(answer(false, "Профили изменились в другой вкладке — обновите страницу", list));
    await page.click(EDITOR.use);
    expect([lines(page.el(EDITOR.result)), page.replaced, rows(page).length]).toEqual([
      [["err", "Профили изменились в другой вкладке — обновите страницу"]], [], 3]);
  });

  test("no answer: the line of it; while it goes - held, Save too", async () => {
    const page = open("profd");
    const going = later();
    page.replies.push(going.reply);
    page.el(EDITOR.use).click();
    await flush();
    expect([page.el<HTMLButtonElement>(EDITOR.use).disabled, page.el<HTMLButtonElement>(EDITOR.del).disabled,
      page.doc.querySelector<HTMLButtonElement>("." + CLS.save)!.disabled]).toEqual([true, true, true]);
    going.resolve(NET);
    await flush();
    expect([lines(page.el(EDITOR.result)), page.el<HTMLButtonElement>(EDITOR.use).disabled]).toEqual([[["err", page.init.i18n.noConnection]], false]);
  });

  test("Delete: asked with its name ($& as it is); no - nothing; yes - a=profile_del, the page of the one in use", async () => {
    const page = open("prof");
    await page.click(EDITOR.del);
    expect([page.confirms, page.calls]).toEqual([[page.init.i18n.profileDeleteConfirm.replace("%s", () => OWN)], []]);
    page.confirmAnswer = true;
    page.replies.push(answer(true, "Профиль удалён", LIST.slice(0, 2)));
    await page.click(EDITOR.del);
    expect(page.calls).toEqual([{ ...POST, url: url("profile_del"), body: "i=2&was=" + encodeURIComponent(OWN) }]);
    expect(page.replaced).toEqual([TOKEN + "#src"]);
  });
});

describe("the save of the profile", () => {
  const save = async (page: Page) => {
    page.doc.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
  };
  const sync = (more: Record<string, unknown> = {}) =>
    json({ ok: true, msg: "Конфиг AIOStreams создан на aiostreams.12312023.xyz", skip: false, base: "https://aiostreams.12312023.xyz", mode: "debrid",
      conf: null, ...more });

  test("a new one: posted with prof=new and its name; then aio_sync of it (prof=<name>); its place in the fields, the list anew, the page stays", async () => {
    const page = open("profnew");
    page.type(EDITOR.name, "Debrid & 2");
    const list = [...LIST, { name: "Debrid & 2", line: LIST[0]!.line, now: false, ready: false }];
    page.replies.push(json({ ok: true, next: TOKEN + "&p=3&saved=1", aio: "create", notes: [], prof: { i: 3, name: "Debrid & 2", now: false },
      profiles: { list: list, max: 8 } }), sync());
    await save(page);
    const body = page.calls[0]!.body!;
    expect([body.includes("&prof=new&"), body.includes("&prof_name=Debrid%20%26%202"), body.includes("&prof_was=&")]).toEqual([true, true, true]);
    expect(page.calls[1]).toEqual({ ...POST, url: url("aio_sync"), body: "prof=" + encodeURIComponent("Debrid & 2") });
    expect([field(page, EDITOR.sel), field(page, EDITOR.was), field(page, EDITOR.now), page.replaced]).toEqual(["3", "Debrid & 2", "", []]);
    expect(rows(page).map((row) => row.getAttribute("aria-current"))).toEqual([null, null, null, "page", null]);
    expect(page.el(EDITOR.buttons).hidden).toBe(false);
  });

  test("the reset of its config: reset=1 and prof", async () => {
    const page = open("profd");
    page.replies.push(json({ ok: true, next: TOKEN + "&p=0&saved=1", aio: "reset", notes: [], prof: { i: 0, name: "Debrid", now: false } }),
      sync());
    page.confirmAnswer = true;
    await page.click(ID.resetConf);
    expect(page.calls[1]).toEqual({ ...POST, url: url("aio_sync"), body: "reset=1&prof=Debrid" });
  });

  test("kept by the profile (the page showed it in use, MENU switched since): the line, the page stays", async () => {
    const page = open("prof");
    page.replies.push(json({ ok: true, next: TOKEN + "&p=2&saved=1&kept=1", aio: "", kept: true, notes: [], prof: { i: 2, name: OWN, now: false },
      profiles: { list: LIST.map((p) => ({ ...p, now: p.name === "Debrid" })), max: 8 } }));
    await save(page);
    expect([lines(page.el(ID.saveResult)), page.replaced, field(page, EDITOR.now)]).toEqual([
      [["ok", page.init.i18n.saved], ["warn", page.init.i18n.sourceKept]], [], ""]);
    expect(page.el(EDITOR.use).hidden).toBe(false);
  });

  test("after aio_sync: the list of its answer (its config made: ready), not that of the save", async () => {
    const page = open("profnew");
    page.type(EDITOR.name, "Debrid & 2");
    const made = { name: "Debrid & 2", line: LIST[0]!.line, now: false, ready: false };
    const after = later();
    page.replies.push(json({ ok: true, next: TOKEN + "&p=3&saved=1", aio: "create", notes: [], prof: { i: 3, name: made.name, now: false },
      profiles: { list: [...LIST, made], max: 8 } }), after.reply);
    await save(page);
    expect(seen(rows(page)[3]!)[0]).toEqual([made.name, page.init.i18n.profileNoConf]);
    after.resolve(sync({ profiles: { list: [...LIST, { ...made, ready: true }], max: 8 } }));
    await flush();
    expect(seen(rows(page)[3]!)[0]).toEqual([made.name]);
  });

  test("was of the answer: src_was of the page (the next save compares with it); none - as it was", async () => {
    const page = open("prof");
    const before = field(page, ID.sourceWas);
    page.replies.push(json({ ok: true, next: TOKEN + "&p=2&saved=1", aio: "", notes: [], prof: { i: 2, name: OWN, now: true } }));
    await save(page);
    expect(field(page, ID.sourceWas)).toBe(before);
    page.type(EDITOR.name, OWN + " 2");
    page.replies.push(json({ ok: true, next: TOKEN + "&p=2&saved=1", aio: "", notes: [], was: "own x  y 0123456789abcdef",
      prof: { i: 2, name: OWN + " 2", now: true } }));
    await save(page);
    expect(field(page, ID.sourceWas)).toBe("own x  y 0123456789abcdef");
  });

  test("the setting turned on or off: next after aio_sync", async () => {
    const page = open("prof");
    page.choose(NAME.srcProfiles, "off");
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "update", reload: true, notes: [], prof: { i: 2, name: OWN, now: true } }), sync());
    await save(page);
    expect(page.replaced).toEqual([TOKEN + "&saved=1"]);
  });
});
