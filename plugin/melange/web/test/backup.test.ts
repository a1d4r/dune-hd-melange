// The backup (#bak): "Save to a file" (a link of PHP, none on the first run), "Load from a file" - the file read by
// FileReader, the question, its text posted (a=import), the page again or why not.

import { afterEach, describe, expect, test } from "bun:test";
import { CLS, ID } from "../src/dom";
import { cleanup, flush, HANG, json, lines, NET, open, type Page, setGlobal, TOKEN } from "./harness";

afterEach(cleanup);

const IMPORT = { method: "POST", url: TOKEN + "&a=import&ajax=1", contentType: "application/x-www-form-urlencoded" };
const NEXT = TOKEN + "&imported=1";
// Neutral values only: the repository is public.
const TEXT = '{"v":2,"rd_key":"RDTESTKEY00000000000000009","servers":[{"name":"Сервер & co"}],"saved_at":1}';

// A file as the script reads it: its size and, by the FileReader below, its text (fail: a read error).
type FakeFile = { size: number; text: string; fail?: boolean };

// FileReader of the browser: the text of a FakeFile, after a turn (as a real one, never at once).
class FakeReader {
  result: string | null = null;
  onload: (() => void) | null = null;
  onerror: (() => void) | null = null;
  readAsText(file: FakeFile): void {
    Promise.resolve().then(() => {
      if (file.fail) this.onerror!();
      else {
        this.result = file.text;
        this.onload!();
      }
    });
  }
}

// The page with FileReader (reader: false - none); picks: the clicks on the file input (its dialog).
function opened(name = "filled", reader = true): Page & { picks: number } {
  const page = open(name, { before: () => setGlobal("FileReader", reader ? FakeReader : undefined) }) as Page & { picks: number };
  page.picks = 0;
  page.el<HTMLInputElement>(ID.backupFile).click = () => {
    page.picks++;
  };
  return page;
}
// A file chosen in the dialog of the file input.
async function choose(page: Page, file: FakeFile | null): Promise<void> {
  const input = page.el<HTMLInputElement>(ID.backupFile);
  Object.defineProperty(input, "files", { value: file ? [file] : [], configurable: true });
  input.dispatchEvent(new page.win.Event("change", { bubbles: true }) as unknown as Event);
  await flush();
}
const file = (text: string): FakeFile => ({ size: new TextEncoder().encode(text).length, text: text });
const results = (page: Page) => lines(page.el(ID.backupResult));
const button = (page: Page) => page.el<HTMLButtonElement>(ID.backupButton);
const save = (page: Page) => page.form.querySelector<HTMLButtonElement>("." + CLS.save)!;

describe("the screen", () => {
  test("saved settings: the link of the export with the token, no line of the first run; the import shown", () => {
    const page = opened();
    expect(page.el(ID.backupExport).getAttribute("href")).toBe(TOKEN + "&a=export");
    expect([page.el(ID.backupExport).hidden, page.el(ID.backupNone).hidden, page.el(ID.backupImport).hidden]).toEqual([false, true, false]);
  });

  test("the first run: no link of the export, its line instead; the import shown", () => {
    const page = opened("empty");
    expect([page.el(ID.backupExport).hidden, page.el(ID.backupNone).hidden, page.el(ID.backupImport).hidden]).toEqual([true, false, false]);
  });

  test("without the script or FileReader: no import", () => {
    expect(open("filled", { start: false }).el(ID.backupImport).hidden).toBe(true);
    cleanup();
    expect(opened("filled", false).el(ID.backupImport).hidden).toBe(true);
  });

  test("the first save that stays on the page: the link of the export instead of the line", async () => {
    const page = opened("empty");
    page.type(ID.rdKey, "RDKEY");
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "create", notes: [] }),
      json({ ok: true, msg: "Created", skip: false, base: "https://aiostreams.kompotik.com", mode: "debrid",
        conf: { cfg: "https://cfg.example/1", login: "l", pass: "p", tpl: "addons" } }));
    save(page).click();
    await flush();
    expect([page.el(ID.backupExport).hidden, page.el(ID.backupNone).hidden]).toEqual([false, true]);
  });
});

describe("the import", () => {
  test("the button opens the dialog of the file input, emptied (the same file again is a change)", async () => {
    const page = opened();
    await page.click(ID.backupButton);
    expect(page.picks).toBe(1);
    expect(page.el<HTMLInputElement>(ID.backupFile).value).toBe("");
  });

  test("no file chosen: nothing", async () => {
    const page = opened();
    await choose(page, null);
    expect([page.confirms, page.calls, results(page)]).toEqual([[], [], []]);
  });

  test("asked first; not confirmed: nothing posted", async () => {
    const page = opened();
    await choose(page, file(TEXT));
    expect(page.confirms).toEqual([page.init.i18n.importConfirm]);
    expect(page.calls).toEqual([]);
  });

  test("confirmed: the text posted as read; while it goes the button and Save are held; written: the page again", async () => {
    const page = opened();
    page.confirmAnswer = true;
    page.replies.push(HANG);
    await choose(page, file(TEXT));
    expect(page.calls).toEqual([{ ...IMPORT, body: "settings=" + encodeURIComponent(TEXT) }]);
    expect([button(page).disabled, save(page).disabled, results(page)]).toEqual([true, true, [["hint", page.init.i18n.importing]]]);
    cleanup();
    const next = opened();
    next.confirmAnswer = true;
    next.replies.push(json({ ok: true, next: NEXT }));
    await choose(next, file(TEXT));
    expect(next.replaced).toEqual([NEXT]);
  });

  // web_token of the file (0.51.0): another token of the page - the question says the link goes back to it, the page
  // goes where PHP says (the link of the file once written); none, that of this page or not of the plugin - as before.
  const OTHER = "fedcba9876543210fedcba9876543210";
  const withToken = (token: unknown) => JSON.stringify({ ...JSON.parse(TEXT), web_token: token });

  test("a file with another link of the page: asked so; written: the page by the link PHP gives", async () => {
    const page = opened();
    page.confirmAnswer = true;
    page.replies.push(json({ ok: true, next: "settings?t=" + OTHER + "&imported=1" }));
    const text = withToken(OTHER);
    await choose(page, file(text));
    expect(page.confirms).toEqual([page.init.i18n.importConfirmLink]);
    expect(page.calls).toEqual([{ ...IMPORT, body: "settings=" + encodeURIComponent(text) }]);
    expect(page.replaced).toEqual(["settings?t=" + OTHER + "&imported=1"]);
  });

  test("the link of the file not written (imported=2): the page by its own link, as PHP says", async () => {
    const page = opened();
    page.confirmAnswer = true;
    page.replies.push(json({ ok: true, next: TOKEN + "&imported=2" }));
    await choose(page, file(withToken(OTHER)));
    expect(page.replaced).toEqual([TOKEN + "&imported=2"]);
  });

  test.each([
    ["the link of this page", TOKEN.slice(TOKEN.indexOf("=") + 1)],
    ["upper case", OTHER.toUpperCase()],
    ["31 characters", OTHER.slice(1)],
    ["33 characters", OTHER + "0"],
    ["a newline after", OTHER + "\n"],
    ["a UUID", "0f1e2d3c-4b5a-6978-8a9b-0c1d2e3f4a5b"],
    ["a number", 123],
    ["a list", [OTHER]],
    ["null", null],
  ])("web_token %s: the question as before", async (_name, token) => {
    const page = opened();
    await choose(page, file(withToken(token)));
    expect(page.confirms).toEqual([page.init.i18n.importConfirm]);
  });

  test.each([["not JSON", "{" + JSON.stringify(OTHER)], ["a list", JSON.stringify([{ web_token: OTHER }])]])(
    "%s: the question as before",
    async (_name, text) => {
      const page = opened();
      await choose(page, file(text));
      expect(page.confirms).toEqual([page.init.i18n.importConfirm]);
    },
  );

  test("refused: its reason, the button free again, Save too", async () => {
    const page = opened();
    page.confirmAnswer = true;
    page.replies.push(json({ ok: false, msg: "Это не файл настроек Melange" }));
    await choose(page, file("[1]"));
    expect([results(page), button(page).disabled, save(page).disabled, page.replaced]).toEqual([
      [["err", "Это не файл настроек Melange"]], false, false, []]);
  });

  test("no answer: the reason of the request (no connection, an old link)", async () => {
    const page = opened();
    page.confirmAnswer = true;
    page.replies.push(NET, { text: "403 Forbidden\n", status: 403 });
    await choose(page, file(TEXT));
    expect(results(page)).toEqual([["err", page.init.i18n.noConnection]]);
    await choose(page, file(TEXT));
    expect(results(page)).toEqual([["err", page.init.i18n.linkExpired]]);
  });

  test("a file over data-max: refused unread, not asked, nothing posted", async () => {
    const page = opened();
    const max = Number(page.el(ID.backupFile).getAttribute("data-max"));
    expect(max).toBeGreaterThan(8192);
    await choose(page, { size: max + 1, text: TEXT });
    expect([results(page), page.confirms, page.calls]).toEqual([[["err", page.init.i18n.importTooBig]], [], []]);
  });

  test("the limit is of the body posted (encoded): one at data-max goes, one over it is refused, not asked", async () => {
    const page = opened();
    page.confirmAnswer = true;
    const max = Number(page.el(ID.backupFile).getAttribute("data-max"));
    // Cyrillic: 6 characters of the body a letter (%D0%B6), 2 bytes of the file.
    const text = (n: number) => '{"v":2,"x":"' + "ж".repeat(n) + "a".repeat((max - 9 - encodeURIComponent('{"v":2,"x":""}').length) % 6) + '"}';
    let n = 0;
    while (("settings=" + encodeURIComponent(text(n + 1))).length <= max) n++;
    expect(("settings=" + encodeURIComponent(text(n))).length).toBe(max);
    expect(file(text(n)).size).toBeLessThan(max / 2);
    // Over: the file itself small enough to read.
    await choose(page, file(text(n) + " "));
    expect([results(page), page.confirms, page.calls]).toEqual([[["err", page.init.i18n.importTooBig]], [], []]);
    page.replies.push(json({ ok: true, next: NEXT }));
    await choose(page, file(text(n)));
    expect([page.calls.length, page.calls[0]!.body!.length, page.replaced]).toEqual([1, max, [NEXT]]);
  });

  test("a file the browser could not read: said so, nothing posted", async () => {
    const page = opened();
    await choose(page, { size: 10, text: "", fail: true });
    expect([results(page), page.confirms, page.calls]).toEqual([[["err", page.init.i18n.importUnread]], [], []]);
  });

  test("a save going: the button does nothing", async () => {
    const page = opened();
    page.replies.push(HANG);
    save(page).click();
    await flush();
    await page.click(ID.backupButton);
    expect([page.picks, button(page).disabled]).toEqual([0, true]);
  });
});
