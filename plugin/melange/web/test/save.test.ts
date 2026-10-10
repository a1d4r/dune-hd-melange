// §2.6 The save: the form as the browser posts it, the answer (next, notes, the config), errors (their marks:
// marks.test.ts), the reset with confirm, aio_sync after it.

import { afterEach, describe, expect, jest, test } from "bun:test";
import { CLS, ID } from "../src/dom";
import { cleanup, flush, HANG, json, later, lines, NET, open, type Page, TOKEN } from "./harness";

afterEach(cleanup);

const SAVE = { method: "POST", url: TOKEN + "&ajax=1", contentType: "application/x-www-form-urlencoded" };
const SYNC = { method: "POST", url: TOKEN + "&a=aio_sync&ajax=1", contentType: "application/x-www-form-urlencoded" };
const MADE = "https://aiostreams.12312023.xyz";
const ATB = "https://aio.atbphosting.com";
const NEXT = TOKEN + "&saved=1";

const button = (page: Page) => page.form.querySelector<HTMLButtonElement>("." + CLS.save)!;
const encode = (pairs: [string, string][]) => pairs.map(([k, v]) => encodeURIComponent(k) + "=" + encodeURIComponent(v)).join("&");
const pairs = (body: string | null) => (body || "").split("&").map((pair) => pair.split("=").map(decodeURIComponent));
async function save(page: Page): Promise<void> {
  button(page).click();
  await flush();
}
function servers(first: [string, string][]): [string, string][] {
  const out: [string, string][] = [];
  for (let n = 1; n <= 5; n++)
    for (const key of ["name", "url", "user", "pass", "category", "tags", "cache"]) {
      const given = n === 1 ? first.find(([k]) => k === key) : undefined;
      out.push(["s" + n + "_" + key, given ? given[1] : ""]);
    }
  return out;
}
const FILLED: [string, string][] = [
  ["source", "made"], ["aio_server", MADE], ["aio_own_url", ""], ["aio_tpl", "addons"], ["aio_tpl_seen", "addons"],
  ["rd_key", "RDTESTKEY00000000000000001"], ["tb_key", "tbtest-0000000000000000001"], ["tmdb_key", "TMDBTESTKEY000000000001"],
  ["manifest", ""], ["jacred", "own"], ["jacred_own_url", "http://192.168.1.10:9117"], ["jacred_own_key", "testkey01"],
  ["ts", "http://192.168.1.10:8090"],
  ...servers([["name", "qBittorrent"], ["url", "http://192.168.1.10:8080"], ["user", "admin"], ["pass", "test-pass"], ["category", "movies"]]),
];
const conf = (n: string, tpl: string | null) => ({
  cfg: "https://cfg.example/" + n, login: "00000000-0000-4000-8000-00000000000" + n, pass: "fedcba9876543210fedcba9" + n, tpl: tpl,
});
// The config of the chosen server of filled.html, as aio_sync sends it.
const FILLED_CONF = (tpl: string) => ({
  cfg: MADE + "/stremio/00000000-0000-4000-8000-000000000001/dGVzdA/configure", login: "00000000-0000-4000-8000-000000000001",
  pass: "0123456789abcdef01234567", tpl: tpl,
});

describe("the form posted", () => {
  test("every named field in order, as the browser: no buttons, the checked radio only", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: true, next: NEXT, aio: "" }));
    await save(page);
    expect(page.calls).toEqual([{ ...SAVE, body: encode(FILLED) }]);
  });

  test("a disabled field skipped, a mark of the template never posted, values encoded", async () => {
    const page = open("ownconf");
    page.replies.push(json({ ok: true, next: NEXT, aio: "" }));
    page.field<HTMLInputElement>("ts").disabled = true;
    page.type("s1_name", "a&b=c +ё");
    await save(page);
    const posted = pairs(page.calls[0]!.body);
    const names = posted.map(([k]) => k);
    expect(names).not.toContain("ts");
    expect(names).not.toContain("aio_tpl");
    expect(names).not.toContain("aio_reset");
    expect(posted).toContainEqual(["aio_tpl_seen", "none"]);
    expect(posted).toContainEqual(["aio_server", "own"]);
    expect(posted).toContainEqual(["jacred", "jr.maxvol.pro"]);
    expect(posted).toContainEqual(["s1_name", "a&b=c +ё"]);
    expect(page.calls[0]!.body).toContain("s1_name=a%26b%3Dc%20%2B%D1%91");
  });

  test("while it goes: the button disabled, Saving…, the message and the results cleared; a 2nd submit nothing", async () => {
    const page = open("filled");
    page.el(ID.message).textContent = "Сохранено";
    page.el(ID.saveResult).textContent = "old";
    const answer = later();
    page.replies.push(answer.reply);
    await save(page);
    expect([button(page).disabled, button(page).textContent]).toEqual([true, page.init.i18n.saving]);
    expect([page.el(ID.message).textContent, page.el(ID.saveResult).textContent]).toEqual(["", ""]);
    await save(page);
    page.form.dispatchEvent(new page.win.Event("submit", { cancelable: true }) as unknown as Event);
    await flush();
    expect(page.calls.length).toBe(1);
    answer.resolve(json({ ok: false, errors: [{ msg: "Bad", field: null }] }));
    await flush();
    expect([button(page).disabled, button(page).textContent]).toEqual([false, page.init.i18n.save]);
  });

  test("Enter in a field: the same save", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: true, next: NEXT, aio: "" }));
    const submit = new page.win.Event("submit", { cancelable: true }) as unknown as Event;
    expect(page.form.dispatchEvent(submit)).toBe(false);
    await flush();
    expect(page.calls).toEqual([{ ...SAVE, body: encode(FILLED) }]);
  });

  test("a lone surrogate in a field: nothing posted or locked, the broken character said; fixed, it goes", async () => {
    const page = open("filled");
    page.type("s1_name", "qB\ud83d");
    await save(page);
    expect(page.calls).toEqual([]);
    expect(lines(page.el(ID.saveResult))).toEqual([["err", page.init.i18n.badChars]]);
    expect([button(page).disabled, button(page).getAttribute("aria-busy"), page.el<HTMLButtonElement>(ID.resetConf).disabled]).toEqual([false, null, false]);
    page.type("s1_name", "qBittorrent");
    page.replies.push(json({ ok: true, next: NEXT, aio: "" }));
    await save(page);
    expect(page.calls).toEqual([{ ...SAVE, body: encode(FILLED) }]);
  });
});

describe("saved", () => {
  test("no notes, no config to make: to next", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: true, next: NEXT, aio: "", notes: [] }));
    await save(page);
    expect(page.replaced).toEqual([NEXT]);
    expect(page.el(ID.saveResult).textContent).toBe("");
  });

  test.each(["toString", "constructor", "CREATE", 1, true])("aio %p (none PHP sends): as \"\", to next, no aio_sync", async (aio) => {
    const page = open("filled");
    page.replies.push(json({ ok: true, next: NEXT, aio: aio, notes: [] }));
    await save(page);
    expect(page.replaced).toEqual([NEXT]);
    expect(page.calls.length).toBe(1);
  });

  test("notes: Saved and the notes (level ok -> ok, else warn), no leaving; notes not objects skipped", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: true, next: NEXT, aio: "", notes: [{ level: "ok", msg: "Fine" }, { level: "warn", msg: "Hm" }, { level: "x", msg: "Other" }, null] }));
    await save(page);
    expect(page.replaced).toEqual([]);
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.saved], ["ok", "Fine"], ["warn", "Hm"], ["warn", "Other"]]);
  });

  test("the template saved: the one for a new config and the one shown when there is no config", async () => {
    const page = open("filled");
    page.type(ID.aioServer, ATB);
    page.type(ID.template, "jacred");
    page.replies.push(json({ ok: true, next: NEXT, aio: "", notes: [{ level: "ok", msg: "Fine" }] }));
    await save(page);
    expect(page.confirms).toEqual([]);
    expect(page.el<HTMLInputElement>(ID.templateShown).value).toBe("jacred");
    // Back to the server with a config (its template), then to one without: the new one.
    page.type(ID.aioServer, MADE);
    expect(page.el<HTMLSelectElement>(ID.template).value).toBe("addons");
    page.type(ID.aioServer, "https://aiostreams.stremio.ru");
    expect(page.el<HTMLSelectElement>(ID.template).value).toBe("jacred");
  });
});

describe("the config after the save (aio_sync)", () => {
  test("create: Creating… on the button and as a line, the result replaces it; the config shown with its template", async () => {
    const page = open("filled");
    const t = page.init.i18n;
    page.type(ID.aioServer, ATB);
    const sync = later();
    page.replies.push(json({ ok: true, next: NEXT, aio: "create" }), sync.reply);
    await save(page);
    expect(page.calls).toEqual([{ ...SAVE, body: expect.any(String) }, { ...SYNC, body: "" }]);
    expect([button(page).disabled, button(page).textContent]).toEqual([true, t.creating]);
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", t.saved], ["hint", t.creating]]);
    sync.resolve(json({ ok: true, msg: "Created", skip: false, base: ATB, conf: conf("7", "jacred") }));
    await flush();
    expect(page.replaced).toEqual([]);
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", t.saved], ["ok", "Created"]]);
    expect([button(page).disabled, button(page).textContent]).toEqual([false, t.save]);
    expect([page.el(ID.confNone).hidden, page.el(ID.confHave).hidden]).toEqual([true, false]);
    expect(page.el<HTMLAnchorElement>(ID.confLink).getAttribute("href")).toBe("https://cfg.example/7");
    expect(page.el<HTMLInputElement>(ID.confLogin).value).toBe(conf("7", null).login);
    expect([page.el<HTMLSelectElement>(ID.template).value, page.el<HTMLInputElement>(ID.templateShown).value]).toEqual(["jacred", "jacred"]);
  });

  test.each([["update", "updating"], ["reset", "resetting"]] as const)("%s: its text; skip: the line gone", async (how, text) => {
    const page = open("filled");
    const sync = later();
    page.replies.push(json({ ok: true, next: NEXT, aio: how }), sync.reply);
    await save(page);
    expect(button(page).textContent).toBe(page.init.i18n[text]);
    expect(page.calls[1]!.body).toBe(how === "reset" ? "reset=1" : "");
    sync.resolve(json({ ok: true, msg: "", skip: true, base: MADE, conf: conf("1", "addons") }));
    await flush();
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.saved]]);
  });

  test("not ok: err; Saved says the settings are saved on the Dune, the notes stay", async () => {
    const page = open("filled");
    const sync = later();
    page.replies.push(json({ ok: true, next: NEXT, aio: "update", notes: [{ level: "warn", msg: "Hm" }] }), sync.reply);
    await save(page);
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.saved], ["warn", "Hm"], ["hint", page.init.i18n.updating]]);
    sync.resolve(json({ ok: false, msg: "Server down", skip: false, base: MADE, conf: conf("1", "addons") }));
    await flush();
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.savedOnDune], ["warn", "Hm"], ["err", "Server down"]]);
  });

  test("the line instead of Saved when the config failed", () => {
    const { init } = open("filled", { start: false });
    expect(init.i18n.savedOnDune).toBe("Настройки Melange сохранены на Дюне.");
  });

  test("the config gone (conf null): none shown", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: true, next: NEXT, aio: "update" }), json({ ok: false, msg: "Gone", skip: false, base: MADE, conf: null }));
    await save(page);
    expect([page.el(ID.confNone).hidden, page.el(ID.confHave).hidden]).toEqual([false, true]);
  });

  test("a base not of the choice: nothing changed", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: true, next: NEXT, aio: "update" }), json({ ok: true, msg: "x", skip: false, base: "https://elsewhere.example", conf: null }));
    await save(page);
    expect([page.el(ID.confNone).hidden, page.el(ID.confHave).hidden]).toEqual([true, false]);
  });

  test("own server: the config is of the address typed", async () => {
    const page = open("filled");
    page.type(ID.aioServer, "own");
    page.type(ID.aioOwnUrl, "http://192.168.1.10:3000/");
    page.replies.push(json({ ok: true, next: NEXT, aio: "create" }), json({ ok: true, msg: "Created", skip: false, base: "own", conf: conf("2", null) }));
    await save(page);
    expect(page.el(ID.confHave).hidden).toBe(false);
    expect(page.el<HTMLSelectElement>(ID.template).value).toBe("none");
    page.type(ID.aioOwnUrl, "http://192.168.1.10:3001");
    expect(page.el(ID.confHave).hidden).toBe(true);
    page.type(ID.aioOwnUrl, "http://192.168.1.10:3000");
    expect(page.el(ID.confHave).hidden).toBe(false);
  });

  test("create, no answer in 60 s (not 40): saved, no config made, press Save again; the button back", async () => {
    jest.useFakeTimers();
    const page = open("filled");
    page.replies.push(json({ ok: true, next: NEXT, aio: "create" }), HANG);
    await save(page);
    jest.advanceTimersByTime(59999);
    await flush();
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.saved], ["hint", page.init.i18n.creating]]);
    jest.advanceTimersByTime(1);
    await flush();
    expect(lines(page.el(ID.saveResult))).toEqual([
      ["ok", page.init.i18n.savedOnDune], ["err", "Конфиг на сервере AIOStreams не создан — нажмите «Сохранить» ещё раз"],
    ]);
    expect([button(page).disabled, button(page).textContent]).toEqual([false, page.init.i18n.save]);
  });

  test.each([["create", "notCreated"], ["update", "notUpdated"], ["reset", "notReset"]] as const)(
    "%s, no connection: its text of the config not done",
    async (how, text) => {
      const page = open("filled");
      page.replies.push(json({ ok: true, next: NEXT, aio: how }), NET);
      await save(page);
      expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.savedOnDune], ["err", page.init.i18n[text]]]);
    },
  );

  test("the texts of the config not done: update says Save, reset says Reset to the template", () => {
    const { init } = open("filled", { start: false });
    expect([init.i18n.notUpdated, init.i18n.notReset]).toEqual([
      "Конфиг на сервере AIOStreams не обновлён — нажмите «Сохранить» ещё раз",
      "Конфиг на сервере AIOStreams не сброшен — нажмите «Сбросить к шаблону» ещё раз",
    ]);
  });

  test("not JSON, 403: as of any request (the HTTP error, the link)", async () => {
    const page = open("filled");
    const t = page.init.i18n;
    page.replies.push(json({ ok: true, next: NEXT, aio: "update" }), { text: "oops", status: 500 });
    await save(page);
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", t.savedOnDune], ["err", t.httpError.replace("%d", "500")]]);
    page.replies.push(json({ ok: true, next: NEXT, aio: "create" }), { text: "403 Forbidden\n", status: 403 });
    await save(page);
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", t.savedOnDune], ["err", t.linkExpired]]);
  });
});

describe("errors", () => {
  test("not JSON (500), no connection, no answer in 40 s, JSON of another shape, 403: one err line", async () => {
    jest.useFakeTimers();
    const page = open("filled");
    const t = page.init.i18n;
    const cases: [Parameters<typeof page.replies.push>[0], string][] = [
      [{ text: "Internal error", status: 500 }, t.httpError.replace("%d", "500")],
      [NET, t.noConnection],
      [HANG, "Дюна не ответила за 40 с — повторите"],
      [json({ ok: false, errors: [] }), t.httpError.replace("%d", "200")],
      [json({ ok: true }), t.httpError.replace("%d", "200")],
      // The last one: the page is locked after it.
      [{ text: "403 Forbidden\n", status: 403 }, t.linkExpired],
    ];
    for (const [reply, text] of cases) {
      page.replies.push(reply);
      await save(page);
      jest.advanceTimersByTime(40000);
      await flush();
      expect(lines(page.el(ID.saveResult))).toEqual([["err", text]]);
    }
    expect(page.replaced).toEqual([]);
  });
});

describe("the reset", () => {
  test("another template over a config: confirm with the host and the template; no: nothing; yes: aio_reset=1", async () => {
    const page = open("filled");
    page.type(ID.template, "jacred");
    await save(page);
    const text = page.init.i18n.resetConfirm.replace("%s", "aiostreams.12312023.xyz").replace("%s", "Только JacRed");
    expect(page.confirms).toEqual([text]);
    expect(page.calls).toEqual([]);
    page.confirmAnswer = true;
    page.replies.push(json({ ok: true, next: NEXT, aio: "" }));
    await save(page);
    expect(page.confirms.length).toBe(2);
    const body = page.calls[0]!.body!;
    expect(body).toBe(encode(FILLED).replace("aio_tpl=addons", "aio_tpl=jacred") + "&aio_reset=1");
  });

  const noConfirm: [string, (page: Page) => void][] = [
    ["source own", (page) => {
      page.type(ID.template, "jacred");
      page.choose("source", "own");
    }],
    ["no config there", (page) => {
      page.type(ID.aioServer, ATB);
      page.type(ID.template, "jacred");
    }],
    ["the template unchanged", () => {}],
  ];
  test.each(noConfirm)("no confirm: %s", async (_case, set) => {
    const page = open("filled");
    set(page);
    page.replies.push(json({ ok: true, next: NEXT, aio: "" }));
    await save(page);
    expect(page.confirms).toEqual([]);
    expect(page.calls.length).toBe(1);
    expect(page.calls[0]!.body).not.toContain("aio_reset");
  });

  test("Reset to the template: confirm; yes: the save with aio_reset=1, no: nothing", async () => {
    const page = open("filled");
    await page.click(ID.resetConf);
    expect(page.confirms.length).toBe(1);
    expect(page.calls).toEqual([]);
    page.confirmAnswer = true;
    page.replies.push(json({ ok: true, next: NEXT, aio: "reset" }), json({ ok: true, msg: "Reset", skip: false, base: MADE, conf: conf("1", "addons") }));
    await page.click(ID.resetConf);
    expect(page.calls.map((call) => call.url)).toEqual([SAVE.url, SYNC.url]);
    expect(page.calls[0]!.body).toBe(encode(FILLED) + "&aio_reset=1");
    expect(page.calls[1]!.body).toBe("reset=1");
  });

  test("Reset under a mark (template unknown): the template for a new config in the confirm, the host of own server", async () => {
    const page = open("ownconf");
    await page.click(ID.resetConf);
    expect(page.confirms).toEqual([page.init.i18n.resetConfirm.replace("%s", "192.168.1.10").replace("%s", "JacRed + аддоны")]);
  });

  test("Reset while a save goes: nothing", async () => {
    const page = open("filled");
    page.confirmAnswer = true;
    page.replies.push(later().reply);
    await save(page);
    await page.click(ID.resetConf);
    expect(page.confirms).toEqual([]);
    expect(page.calls.length).toBe(1);
  });

  test.each([false, true])("ok %p: a failed reset keeps the template chosen; a done one shows the template of the config", async (ok) => {
    const page = open("filled");
    page.confirmAnswer = true;
    page.type(ID.template, "jacred");
    page.replies.push(json({ ok: true, next: NEXT, aio: "reset" }),
      json({ ok: ok, msg: "m", skip: false, base: MADE, conf: FILLED_CONF(ok ? "jacred" : "addons") }));
    await save(page);
    expect([page.el<HTMLSelectElement>(ID.template).value, page.el<HTMLInputElement>(ID.templateShown).value]).toEqual(
      ok ? ["jacred", "jacred"] : ["jacred", "addons"],
    );
  });
});
