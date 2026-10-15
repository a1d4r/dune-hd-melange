// The marks of the errors of a save (marks.ts): the field or server block of each, the top one on its screen (the
// address #<screen>, details around it open), in view and focused, a mark gone on editing or at the next save.

import { afterEach, describe, expect, test } from "bun:test";
import { CLS, ID } from "../src/dom";
import { cleanup, flush, json, later, lines, NET, open, type Page } from "./harness";

afterEach(cleanup);

// The hash the script set (location of the harness: no hash until then).
const hash = () => (globalThis as unknown as { location: { hash?: string } }).location.hash;

async function save(page: Page): Promise<void> {
  page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
  await flush();
}

describe("errors lead to the field", () => {
  const block = (page: Page, n: number) => page.doc.querySelectorAll<HTMLDetailsElement>("details." + CLS.serverBlock)[n - 1]!;
  // An element by its name= or, a server block, "block <n>" (elements are not compared deeply).
  function label(page: Page, el: Element): string {
    const blocks = Array.from(page.doc.querySelectorAll("details." + CLS.serverBlock));
    return el.getAttribute("name") || (blocks.indexOf(el) >= 0 ? "block " + (blocks.indexOf(el) + 1) : el.tagName);
  }
  // The scrollIntoView() calls on the fields and blocks of the form: [label, its options].
  function spyScroll(page: Page): [string, unknown][] {
    const calls: [string, unknown][] = [];
    page.form.querySelectorAll<HTMLElement>("input,select,details").forEach((el) => {
      el.scrollIntoView = (options?: boolean | ScrollIntoViewOptions) => void calls.push([label(page, el), options]);
    });
    return calls;
  }
  // In the order of the page.
  const marked = (page: Page) => Array.from(page.form.querySelectorAll("." + CLS.bad)).map((el) => label(page, el));
  const focused = (page: Page) => (page.doc.activeElement ? label(page, page.doc.activeElement) : null);
  const KEY_ERROR = { msg: "Нужен ключ RD или TorBox", field: "rd_key" };

  test("the key: marked, brought to the middle of the screen, focused; the list under the button stays", async () => {
    const page = open("filled");
    const scrolls = spyScroll(page);
    page.replies.push(json({ ok: false, errors: [KEY_ERROR] }));
    await save(page);
    expect(marked(page)).toEqual(["rd_key"]);
    expect(page.field("rd_key").getAttribute("aria-invalid")).toBe("true");
    expect(scrolls).toEqual([["rd_key", { block: "center" }]]);
    expect(focused(page)).toBe("rd_key");
    expect(hash()).toBe("#src");
    expect(lines(page.el(ID.saveResult))).toEqual([["err", KEY_ERROR.msg]]);
    // The bar with the errors stays.
    expect(page.el(ID.bar).hidden).toBe(false);
  });

  test("the key of TorBox hidden (Real-Debrid shown by the switch): shown for its error", async () => {
    const page = open("empty");
    expect(page.el(ID.tbBox).hidden).toBe(true);
    page.replies.push(json({ ok: false, errors: [{ msg: "TorBox", field: "tb_key" }] }));
    await save(page);
    expect([page.el(ID.tbBox).hidden, focused(page), hash()]).toEqual([false, "tb_key", "#src"]);
  });

  test("own JacRed and TorrServer: both marked, the first one brought into view", async () => {
    const page = open("filled");
    const scrolls = spyScroll(page);
    page.replies.push(json({ ok: false, errors: [{ msg: "No dir", field: null }, { msg: "JacRed", field: "jacred_own_url" }, { msg: "TS", field: "ts" }] }));
    await save(page);
    expect(marked(page)).toEqual(["jacred_own_url", "ts"]);
    expect(scrolls.map(([el]) => el)).toEqual(["jacred_own_url"]);
    expect(focused(page)).toBe("jacred_own_url");
    // Its screen, "Own JacRed…" around it open.
    expect([hash(), page.el<HTMLDetailsElement>("jrownd").open]).toEqual(["#voices", true]);
  });

  test("the address of own AIOStreams in the closed details of the server: opened, on #src", async () => {
    const page = open("filled");
    page.el<HTMLDetailsElement>("asd").open = false;
    page.replies.push(json({ ok: false, errors: [{ msg: "AIOStreams", field: "aio_own_url" }] }));
    await save(page);
    expect([hash(), page.el<HTMLDetailsElement>("asd").open, focused(page)]).toEqual(["#src", true, "aio_own_url"]);
  });

  test("TorrServer in Torrents (moved to #src): the screen of where it is now", async () => {
    const page = open("p2p");
    page.replies.push(json({ ok: false, errors: [{ msg: "TS", field: "ts" }] }));
    await save(page);
    expect([hash(), focused(page)]).toEqual(["#src", "ts"]);
  });

  test("a server block (s3_url for any of its fields): the block marked, not the address; its screen, the block in view, no focus", async () => {
    const page = open("filled");
    const scrolls = spyScroll(page);
    const before = focused(page);
    page.replies.push(json({ ok: false, errors: [{ msg: "Сервер 3: bad", field: "s3_url" }] }));
    await save(page);
    expect(marked(page)).toEqual(["block 3"]);
    expect(page.field("s3_url").classList.contains(CLS.bad)).toBe(false);
    expect([hash(), block(page, 3).open, block(page, 3).style.display]).toEqual(["#dl", true, ""]);
    expect(scrolls).toEqual([["block 3", { block: "center" }]]);
    expect(focused(page)).toBe(before);
  });

  test.each([
    ["s3_url", "rd_key", "rd_key"],
    ["s2_url", "ts", "ts"],
  ])("errors %s, %s: both marked, the top one on the page in view and focused (%s), not the first of the answer", async (first, second, top) => {
    const page = open("filled");
    const scrolls = spyScroll(page);
    page.replies.push(json({ ok: false, errors: [{ msg: "Server", field: first }, { msg: "Field", field: second }] }));
    await save(page);
    expect(marked(page)).toEqual([second, "block " + first.charAt(1)]);
    expect(scrolls).toEqual([[top, { block: "center" }]]);
    expect(focused(page)).toBe(top);
  });

  test("typing into a marked field takes its mark off; a field of a marked block, the block's; others stay", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: false, errors: [KEY_ERROR, { msg: "TS", field: "ts" }, { msg: "Сервер 1", field: "s1_url" }] }));
    await save(page);
    expect(marked(page).length).toBe(3);
    page.type("rd_key", "RDTESTKEY00000000000000002");
    expect(marked(page)).toEqual(["ts", "block 1"]);
    page.type("s1_name", "qB");
    expect(marked(page)).toEqual(["ts"]);
    page.type("s1_cache", "rd");
    page.type("tb_key", "x");
    expect(marked(page)).toEqual(["ts"]);
    page.type("ts", "");
    expect(marked(page)).toEqual([]);
  });

  test("a new save: every mark off from its start", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: false, errors: [KEY_ERROR, { msg: "Сервер 1", field: "s1_url" }] }));
    await save(page);
    expect(marked(page).length).toBe(2);
    const answer = later();
    page.replies.push(answer.reply);
    await save(page);
    expect(marked(page)).toEqual([]);
    answer.resolve(json({ ok: false, errors: [{ msg: "TS", field: "ts" }] }));
    await flush();
    expect(marked(page)).toEqual(["ts"]);
  });

  test("no field, a field not in the form, a block not there, no answer: nothing marked or scrolled, no screen opened", async () => {
    const page = open("filled");
    const scrolls = spyScroll(page);
    page.replies.push(
      json({ ok: false, errors: [{ msg: "a", field: null }, { msg: "b", field: "nope" }, { msg: "d", field: "s7_url" }, { msg: "e" }] }),
      NET,
    );
    await save(page);
    expect(lines(page.el(ID.saveResult)).length).toBe(4);
    expect([hash(), page.replaced]).toEqual([undefined, []]);
    await save(page);
    expect(lines(page.el(ID.saveResult))).toEqual([["err", page.init.i18n.noConnection]]);
    expect([marked(page), scrolls]).toEqual([[], []]);
  });

  test("after a save without the script (errors drawn by PHP): they go at the next save, its errors marked", async () => {
    const page = open("err");
    const scrolls = spyScroll(page);
    expect(page.el(ID.message).querySelectorAll("p.err").length).toBe(4);
    expect([marked(page), page.field("rd_key").getAttribute("value")]).toEqual([[], "bad key"]);
    // Server block 2 with a value posted: shown.
    expect(block(page, 2).style.display).toBe("");
    page.replies.push(json({ ok: false, errors: [{ msg: "Сервер 2: bad", field: "s2_url" }, KEY_ERROR] }));
    await save(page);
    expect(page.el(ID.message).textContent).toBe("");
    expect(lines(page.el(ID.saveResult))).toEqual([["err", "Сервер 2: bad"], ["err", KEY_ERROR.msg]]);
    expect(marked(page)).toEqual(["rd_key", "block 2"]);
    expect(scrolls.map(([el]) => el)).toEqual(["rd_key"]);
    expect(page.calls[0]!.body).toContain("rd_key=bad%20key");
  });

  test.each([
    ["watch", "wd", "#src"],
    ["jacred", "jrb2", "#voices"],
  ])("radios %s: the cards of their group marked and in view on the screen, not focused; choosing one takes the mark off", async (name, other, screen) => {
    const page = open("p2p");
    const group = page.doc.querySelector("label[for=" + page.form.querySelector("input[name=" + name + "]")!.id + "]")!.closest(".choice") as HTMLElement;
    let scrolled: unknown = null;
    group.scrollIntoView = (options?: boolean | ScrollIntoViewOptions) => void (scrolled = options);
    const before = focused(page);
    page.replies.push(json({ ok: false, errors: [{ msg: "Режим: нет такого режима", field: name }] }));
    await save(page);
    expect([group.closest("section")!.id, group.classList.contains(CLS.bad), page.form.querySelectorAll("." + CLS.bad).length]).toEqual(
      [screen.slice(1), true, 1]);
    expect([scrolled, focused(page), hash()]).toEqual([{ block: "center" }, before, screen]);
    page.choose(name, page.el<HTMLInputElement>(other).value);
    expect(group.classList.contains(CLS.bad)).toBe(false);
  });

  // Saved, then aio_sync refused with the field it is about: marked as of a save error, its text under the button.
  const SYNC_NO_KEY = { ok: false, msg: "Конфиг на сервере AIOStreams не обновлён: нужен хотя бы один ключ Debrid", skip: false, base: null, mode: "debrid", conf: null };

  test("aio_sync with a field (no Debrid key): marked, in view, focused; the mark goes on typing", async () => {
    const page = open("filled");
    const scrolls = spyScroll(page);
    page.replies.push(json({ ok: true, next: "x", aio: "update" }), json({ ...SYNC_NO_KEY, field: "rd_key" }));
    await save(page);
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.savedOnDune], ["err", SYNC_NO_KEY.msg]]);
    expect(marked(page)).toEqual(["rd_key"]);
    expect(scrolls).toEqual([["rd_key", { block: "center" }]]);
    expect(focused(page)).toBe("rd_key");
    page.type("rd_key", "RDTESTKEY00000000000000002");
    expect(marked(page)).toEqual([]);
  });

  test("aio_sync with a field (the own server): its address marked; gone at the next save", async () => {
    const page = open("filled");
    page.type(ID.aioServer, "own");
    page.type(ID.aioOwnUrl, "http://192.168.1.10:3000");
    page.replies.push(json({ ok: true, next: "x", aio: "create" }), json({ ok: false, msg: "Down", skip: false, base: "own", mode: "debrid", conf: null, field: "aio_own_url" }));
    await save(page);
    expect([marked(page), focused(page)]).toEqual([["aio_own_url"], "aio_own_url"]);
    page.replies.push(later().reply);
    await save(page);
    expect(marked(page)).toEqual([]);
  });

  test("aio_sync without a field, or ok with one: nothing marked or scrolled", async () => {
    const page = open("filled");
    const scrolls = spyScroll(page);
    page.replies.push(json({ ok: true, next: "x", aio: "update" }), json(SYNC_NO_KEY));
    await save(page);
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.savedOnDune], ["err", SYNC_NO_KEY.msg]]);
    page.replies.push(json({ ok: true, next: "x", aio: "update" }), json({ ok: true, msg: "Updated", skip: false, base: null, mode: "debrid", conf: null, field: "rd_key" }));
    await save(page);
    expect([marked(page), scrolls]).toEqual([[], []]);
  });
});
