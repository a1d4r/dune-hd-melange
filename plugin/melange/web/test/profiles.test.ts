// The editor of the sources (#pe, v2): the page is of one source (prof: the main one, Debrid; profd: Торренты "Домашний",
// not the main one, no config - "Save and create" at once; proflp: Lampac, nothing to save; profnew: a new one, the copy of Debrid), three of them in the fixtures. The
// list of #init - a link each to the page of the source (by its id), "+ Add source" (the copy of the one of the page),
// followed even while the form has something to save; "Make it the main one" (a=profile_main), "Delete" (a=profile_del,
// asked first) - held then; done: the page anew; a save of the source: its id in the fields of the save, the list of the
// answer, aio_sync of it. One source alone (filled): no list, no name, "+ Add source" at the end of #src.

import { afterEach, describe, expect, test } from "bun:test";
import { readFileSync } from "fs";
import { CLS, EDITOR, ID, NAME } from "../src/dom";
import { cleanup, flush, json, later, lines, NET, open, type Page, TOKEN } from "./harness";

afterEach(cleanup);

const POST = { method: "POST", contentType: "application/x-www-form-urlencoded" };
const url = (a: string) => TOKEN + "&a=" + a + "&ajax=1";
const pageOf = (p: string, from = "") => TOKEN + "&p=" + p + (from ? "&from=" + from : "") + "#src";
// The list of the fixtures, as PHP sends it.
const LIST = [
  { id: "dddd0001", name: "Debrid", line: "Debrid · aiostreams.12312023.xyz · jacred.kompotik.com", main: true, ready: true },
  { id: "nnnn0001", name: "Домашний", line: "Торренты · 192.0.2.10 · jacred.kompotik.com", main: false, ready: false },
  { id: "llll0001", name: "Lampac", line: "Lampac · lampac.example", main: false, ready: true },
];
const answer = (ok: boolean, msg: string, list: unknown[] = LIST) => json({ ok: ok, msg: msg, profiles: { list: list, max: 8 } });

const rows = (page: Page, box: string = EDITOR.list) => Array.from(page.el(box).children) as HTMLElement[];
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
// The selectors of the CSS rule that hides by the answers (one rule, one line).
const hiders = () =>
  readFileSync(import.meta.dir + "/../../cgi/settings.css", "utf8").split("\n").find((line) => line.includes("#nt2:checked~* .xa"))!
    .replace(/\{.*/, "").split(",");
const hidden = (el: Element) => hiders().some((selector) => el.matches(selector));

describe("the editor", () => {
  test("one source alone (filled): no list, no name, no buttons; + Add source at the end of #src (the copy of it)", () => {
    const page = open("filled");
    const add = rows(page, EDITOR.add);
    expect([page.doc.getElementById(EDITOR.list), page.doc.getElementById(EDITOR.name), page.doc.getElementById(EDITOR.buttons)])
      .toEqual([null, null, null]);
    expect(add.map(seen)).toEqual([[[page.init.i18n.profileNew], null, pageOf("new", field(page, EDITOR.sel)), null]]);
    expect(/^[a-z0-9]{8}$/.test(field(page, EDITOR.sel))).toBe(true);
  });

  test("no source yet (empty): the first one, no + Add source", () => {
    const page = open("empty");
    expect([field(page, EDITOR.sel), rows(page, EDITOR.add).length, page.doc.getElementById(EDITOR.list)]).toEqual(["new", 0, null]);
  });

  test("the list: a link each to the page of the source, the one of the page marked, main and no config; + Add source - the copy of it", () => {
    const page = open("prof");
    const t = page.init.i18n;
    expect(rows(page).map(seen)).toEqual([
      [["Debrid", t.profileMain], LIST[0]!.line, pageOf("dddd0001"), "page"],
      [["Домашний", t.profileNoConf], LIST[1]!.line, pageOf("nnnn0001"), null],
      [["Lampac"], LIST[2]!.line, pageOf("llll0001"), null],
      [[t.profileNew], null, pageOf("new", "dddd0001"), null],
    ]);
    expect(page.doc.getElementById(EDITOR.add)).toBe(null);
  });

  test("a name as text, never as markup", () => {
    const page = open("prof", {
      before: (doc) => {
        const el = doc.getElementById(ID.init)!;
        const init = JSON.parse(el.textContent || "");
        init.profiles.list[1].name = "Свой & <конфиг>";
        el.textContent = JSON.stringify(init);
      },
    });
    expect(rows(page)[1]!.querySelector("." + CLS.cardTitle)!.innerHTML.startsWith("Свой &amp; &lt;конфиг&gt;")).toBe(true);
  });

  test("the main one: Delete, no Make it the main one; the fields of the save as PHP put them", () => {
    const page = open("prof");
    expect([page.el(EDITOR.buttons).hidden, page.el(EDITOR.main).hidden, page.el(EDITOR.del).hidden]).toEqual([false, true, false]);
    expect([field(page, EDITOR.sel), field(page, EDITOR.from), field(page, EDITOR.name)]).toEqual(["dddd0001", "", "Debrid"]);
  });

  test("not the main one (profd): both buttons; its row marked", () => {
    const page = open("profd");
    expect([page.el(EDITOR.main).hidden, page.el(EDITOR.del).hidden, rows(page).map((row) => row.getAttribute("aria-current"))])
      .toEqual([false, false, [null, "page", null, null]]);
    expect(field(page, EDITOR.sel)).toBe("nnnn0001");
  });

  test("a new one (profnew): the copy of Debrid - its name «Debrid 2», no buttons, + Add source marked", () => {
    const page = open("profnew");
    expect([page.el(EDITOR.buttons).hidden, rows(page).map((row) => row.getAttribute("aria-current")), field(page, EDITOR.name),
      field(page, EDITOR.sel), field(page, EDITOR.from)]).toEqual([true, [null, null, null, "page"], "Debrid 2", "new", "dddd0001"]);
    expect(seen(rows(page)[3]!)[2]).toBe(pageOf("new", "dddd0001"));
  });

  test("the copy: the key of Debrid as of the source (not typed again), no config of its own (Save and create)", () => {
    const page = open("profnew");
    expect([field(page, ID.rdKey), page.el(ID.confNone).hidden, page.doc.querySelector("." + CLS.save)!.textContent])
      .toEqual(["RDTESTKEY00000000000000001", false, page.init.i18n.saveAndCreate]);
  });

  test("Lampac (proflp): its answer, its fields shown (address, token, as Lampa), those of AIOStreams not; no config to make", () => {
    const page = open("proflp");
    const css = (id: string) => hidden(page.el(id)) || hidden(page.el(id).closest(".xl, .xd, .xp, .xo, .xm, .xr")!);
    expect(page.doc.querySelector<HTMLInputElement>("#wl")!.checked).toBe(true);
    expect([field(page, ID.lampacUrl), field(page, ID.lampacToken)]).toEqual(["http://lampac.example:9118", "LpTestToken_01"]);
    expect([css(ID.lampacUrl), css("mdeb"), css("sown"), css("rcfg"), css("rvoices")]).toEqual([false, true, true, true, true]);
    expect(page.doc.querySelector("." + CLS.save)!.textContent).toBe(page.init.i18n.save);
    page.choose(NAME.watch, "debrid");
    expect([css(ID.lampacUrl), css("mdeb"), css("rcfg")]).toEqual([true, false, false]);
  });

  test("the sources full: no + Add source, the line of it", () => {
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
  test("a link and + Add source followed (the changes lost); the buttons disabled; no change - enabled again", () => {
    const page = open("proflp");
    const links = rows(page);
    page.type(EDITOR.name, "Lampac 2");
    expect(page.el(ID.bar).hidden).toBe(false);
    expect([follow(page, links[0]!), follow(page, links[3]!)]).toEqual([true, true]);
    expect([page.el<HTMLButtonElement>(EDITOR.main).disabled, page.el<HTMLButtonElement>(EDITOR.del).disabled]).toEqual([true, true]);
    page.type(EDITOR.name, "Lampac");
    expect(page.el<HTMLButtonElement>(EDITOR.main).disabled).toBe(false);
  });

  test("a new one with a config to create (no change, Save and create): the links followed", () => {
    const page = open("profnew");
    expect([page.el(ID.bar).hidden, page.el(ID.dirty).textContent, page.doc.querySelector("." + CLS.save)!.textContent])
      .toEqual([false, "", page.init.i18n.saveAndCreate]);
    expect(rows(page).map((row) => follow(page, row))).toEqual([true, true, true, true]);
  });

  test("the name of the source is a change of the bar", () => {
    const page = open("proflp");
    expect(page.el(ID.bar).hidden).toBe(true);
    page.type(EDITOR.name, "Другое");
    expect([page.el(ID.bar).hidden, page.el(ID.dirty).textContent]).toEqual([false, "1 изменение"]);
  });
});

describe("Make it the main one, Delete", () => {
  test("Make it the main one -> a=profile_main of its id, done: its page anew", async () => {
    const page = open("proflp");
    page.replies.push(answer(true, "Основной источник — «Lampac»"));
    await page.click(EDITOR.main);
    expect(page.calls).toEqual([{ ...POST, url: url("profile_main"), body: "i=llll0001" }]);
    expect(page.replaced).toEqual([pageOf("llll0001")]);
  });

  test("refused: its line, the list of the answer, the page stays", async () => {
    const page = open("proflp");
    page.replies.push(answer(false, "Источник удалён в другой вкладке — обновите страницу", LIST.slice(0, 2)));
    await page.click(EDITOR.main);
    expect([lines(page.el(EDITOR.result)), page.replaced, rows(page).length]).toEqual([
      [["err", "Источник удалён в другой вкладке — обновите страницу"]], [], 3]);
  });

  test("no answer: the line of it; while it goes - held, Save too", async () => {
    const page = open("proflp");
    const going = later();
    page.replies.push(going.reply);
    page.el(EDITOR.main).click();
    await flush();
    expect([page.el<HTMLButtonElement>(EDITOR.main).disabled, page.el<HTMLButtonElement>(EDITOR.del).disabled,
      page.doc.querySelector<HTMLButtonElement>("." + CLS.save)!.disabled]).toEqual([true, true, true]);
    going.resolve(NET);
    await flush();
    expect([lines(page.el(EDITOR.result)), page.el<HTMLButtonElement>(EDITOR.main).disabled]).toEqual([[["err", page.init.i18n.noConnection]], false]);
  });

  test("Delete of one not the main one: asked with its name; no - nothing; yes - a=profile_del, the page of the main one", async () => {
    const page = open("proflp");
    await page.click(EDITOR.del);
    expect([page.confirms, page.calls]).toEqual([[page.init.i18n.profileDeleteConfirm.replace("%s", () => "Lampac")], []]);
    page.confirmAnswer = true;
    page.replies.push(answer(true, "Источник «Lampac» удалён", LIST.slice(0, 2)));
    await page.click(EDITOR.del);
    expect(page.calls).toEqual([{ ...POST, url: url("profile_del"), body: "i=llll0001" }]);
    expect(page.replaced).toEqual([TOKEN + "#src"]);
  });

  test("Delete of the main one: asked with the one that becomes it ($& as it is)", async () => {
    const page = open("prof", {
      before: (doc) => {
        const el = doc.getElementById(ID.init)!;
        const init = JSON.parse(el.textContent || "");
        init.profiles.list[1].name = "A$&B";
        el.textContent = JSON.stringify(init);
      },
    });
    const t = page.init.i18n;
    await page.click(EDITOR.del);
    expect(page.confirms).toEqual([t.profileDeleteConfirm.replace("%s", () => "Debrid") + " " + t.profileDeleteMain.replace("%s", () => "A$&B")]);
  });

  test("the last one: no Delete", () => {
    const page = open("prof", {
      before: (doc) => {
        const el = doc.getElementById(ID.init)!;
        const init = JSON.parse(el.textContent || "");
        init.profiles.list = init.profiles.list.slice(0, 1);
        el.textContent = JSON.stringify(init);
      },
    });
    expect([page.el(EDITOR.main).hidden, page.el(EDITOR.del).hidden]).toEqual([true, true]);
  });
});

describe("the save of the source", () => {
  const save = async (page: Page) => {
    page.doc.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
  };
  const sync = (more: Record<string, unknown> = {}) =>
    json({ ok: true, msg: "Конфиг AIOStreams создан на aiostreams.12312023.xyz", skip: false, base: "https://aiostreams.12312023.xyz", mode: "debrid",
      conf: null, ...more });

  test("a new one: posted with prof=new, prof_from and its name; then aio_sync of it (prof=<id>); its id in the fields, the list anew, the page stays", async () => {
    const page = open("profnew");
    page.type(EDITOR.name, "Debrid & 2");
    const made = { id: "aaaa0002", name: "Debrid & 2", line: LIST[0]!.line, main: false, ready: false };
    page.replies.push(json({ ok: true, next: TOKEN + "&p=aaaa0002&saved=1", aio: "create", notes: [], prof: { id: "aaaa0002", name: made.name, main: false },
      profiles: { list: [...LIST, made], max: 8 } }), sync());
    await save(page);
    const body = page.calls[0]!.body!;
    expect([body.includes("&prof=new&"), body.includes("&prof_from=dddd0001"), body.includes("&prof_name=Debrid%20%26%202")]).toEqual([true, true, true]);
    expect(page.calls[1]).toEqual({ ...POST, url: url("aio_sync"), body: "prof=aaaa0002" });
    expect([field(page, EDITOR.sel), field(page, EDITOR.from), page.replaced]).toEqual(["aaaa0002", "", []]);
    expect(rows(page).map((row) => row.getAttribute("aria-current"))).toEqual([null, null, null, "page", null]);
    expect(seen(rows(page)[4]!)[2]).toBe(pageOf("new", "aaaa0002"));
    expect([page.el(EDITOR.buttons).hidden, page.el(EDITOR.main).hidden]).toEqual([false, false]);
  });

  test("the reset of its config: reset=1 and prof", async () => {
    const page = open("prof");
    page.replies.push(json({ ok: true, next: TOKEN + "&p=dddd0001&saved=1", aio: "reset", notes: [], prof: { id: "dddd0001", name: "Debrid", main: true } }),
      sync());
    page.confirmAnswer = true;
    await page.click(ID.resetConf);
    expect(page.calls[1]).toEqual({ ...POST, url: url("aio_sync"), body: "reset=1&prof=dddd0001" });
  });

  test("Lampac: no aio_sync, the page stays", async () => {
    const page = open("proflp");
    page.type(ID.lampacToken, "Other_1");
    page.replies.push(json({ ok: true, next: TOKEN + "&p=llll0001&saved=1", aio: "", notes: [], prof: { id: "llll0001", name: "Lampac", main: false },
      profiles: { list: LIST, max: 8 } }));
    await save(page);
    expect([page.calls.length, page.replaced, lines(page.el(ID.saveResult))]).toEqual([1, [], [["ok", page.init.i18n.saved]]]);
    expect(/(^|&)watch=lampac(&|$)/.test(page.calls[0]!.body!)).toBe(true);
  });

  test("after aio_sync: the list of its answer (its config made: ready), not that of the save", async () => {
    const page = open("profnew");
    const made = { id: "aaaa0002", name: "Debrid 2", line: LIST[0]!.line, main: false, ready: false };
    const after = later();
    page.replies.push(json({ ok: true, next: TOKEN + "&p=aaaa0002&saved=1", aio: "create", notes: [], prof: { id: made.id, name: made.name, main: false },
      profiles: { list: [...LIST, made], max: 8 } }), after.reply);
    await save(page);
    expect(seen(rows(page)[3]!)[0]).toEqual([made.name, page.init.i18n.profileNoConf]);
    after.resolve(sync({ profiles: { list: [...LIST, { ...made, ready: true }], max: 8 } }));
    await flush();
    expect(seen(rows(page)[3]!)[0]).toEqual([made.name]);
  });

  test("the only one saved first (empty): its + Add source after the save", async () => {
    const page = open("empty");
    page.choose(NAME.watch, "p2p");
    const made = { id: "aaaa0001", name: "Торренты", line: "Торренты · aiostreams.kompotik.com · jacred.kompotik.com", main: true, ready: false };
    page.replies.push(json({ ok: true, next: TOKEN + "&p=aaaa0001&saved=1", aio: "create", notes: [], prof: { id: made.id, name: made.name, main: true },
      profiles: { list: [made], max: 8 } }), sync());
    await save(page);
    expect(rows(page, EDITOR.add).map(seen)).toEqual([[[page.init.i18n.profileNew], null, pageOf("new", "aaaa0001"), null]]);
  });
});

describe("the main screen", () => {
  test("two sources or more: the line of the source - the main one and the count of the others, kept while typing", () => {
    const page = open("prof");
    expect(page.el("vsrc").textContent).toBe("Debrid и ещё 2");
    page.type(ID.rdKey, "");
    expect([page.el("vsrc").textContent, page.el(ID.state).getAttribute("data-st")]).toEqual(["Debrid и ещё 2", "key"]);
  });

  test("Lampac: its line by the address; none - the state lp", () => {
    const page = open("filled");
    page.choose(NAME.watch, "lampac");
    expect([page.el("vsrc").textContent, page.el(ID.state).getAttribute("data-st")]).toEqual(["Lampac · " + page.init.i18n.needAddress, "lp"]);
    page.type(ID.lampacUrl, "http://lampac.example:9118");
    expect([page.el("vsrc").textContent, page.el(ID.state).getAttribute("data-st"), page.el("dcfg").className])
      .toEqual(["Lampac · lampac.example", "ok", "dot"]);
  });
});
