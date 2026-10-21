// §2.6 The save: the form as the browser posts it, the answer (next, notes, the config), errors (their marks:
// marks.test.ts), the reset with confirm, aio_sync after it.

import { afterEach, describe, expect, jest, test } from "bun:test";
import { CLS, ID, NAME } from "../src/dom";
import { cleanup, flush, HANG, json, later, lines, NET, open, type Page, templateOf, TOKEN } from "./harness";

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
  ["watch", "debrid"], ["rd_key", "RDTESTKEY00000000000000001"], ["tb_key", "tbtest-0000000000000000001"], ["aio_tpl", "addons"],
  ["aio_tpl_seen", "addons"], ["aio_tpl_p2p", "p2p_jacred"], ["aio_tpl_p2p_seen", "p2p_jacred"], ["manifest", ""], ["aio_server", MADE], ["aio_own_url", ""], ["tmdb_key", "TMDBTESTKEY000000000001"],
  ["jacred", "own"], ["jacred_own_url", "http://192.168.1.10:9117"], ["jacred_own_key", "testkey01"],
  ["ts", "http://192.168.1.10:8090"], ["ts_user", "tsuser"], ["ts_pass", "ts p@ss&x"],
  ...servers([["name", "qBittorrent"], ["url", "http://192.168.1.10:8080"], ["user", "admin"], ["pass", "test-pass"], ["category", "movies"]]),
];
// Torrents: as the page came but the answer (0.44.0: the TorrServer field stays on #torr).
const P2P_BODY = encode([["watch", "p2p"], ...FILLED.slice(1)]);
const conf = (n: string, tpl: string | null) => ({
  cfg: "https://cfg.example/" + n, login: "00000000-0000-4000-8000-00000000000" + n, pass: "fedcba9876543210fedcba9" + n, tpl: tpl,
});
// The config of Debrid of the chosen server of filled.html (of P2P there in p2p.html), as aio_sync sends it.
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
    page.choose("aio_server", ATB);
    page.choose(NAME.template, "jacred");
    page.replies.push(json({ ok: true, next: NEXT, aio: "", notes: [{ level: "ok", msg: "Fine" }] }));
    await save(page);
    expect(page.confirms).toEqual([]);
    expect(page.el<HTMLInputElement>(ID.templateShown).value).toBe("jacred");
    // Back to the server with a config (its template), then to one without: the new one.
    page.choose("aio_server", MADE);
    expect(templateOf(page, "debrid")).toBe("addons");
    page.choose("aio_server", "https://aiostreams.stremio.ru");
    expect(templateOf(page, "debrid")).toBe("jacred");
  });

  test("the mode saved: kept for every server; the template shown stays that of Debrid", async () => {
    const page = open("filled");
    const mode = () => page.form.querySelector<HTMLInputElement>("input[name=watch]:checked")!.value;
    page.choose("aio_server", ATB);
    page.choose("watch", "p2p");
    page.replies.push(json({ ok: true, next: NEXT, aio: "", notes: [{ level: "ok", msg: "Fine" }] }));
    await save(page);
    expect([page.confirms, page.el<HTMLInputElement>(ID.templateShown).value]).toEqual([[], "addons"]);
    page.choose("aio_server", MADE);
    expect([mode(), page.el<HTMLInputElement>(ID.templateShown).value]).toEqual(["p2p", "addons"]);
  });
});

describe("a login and password in the TorrServer address (0.45.0)", () => {
  const dirty = (page: Page) => (page.el(ID.bar).hidden ? null : page.el(ID.dirty).textContent);
  const ts = (page: Page) => [ID.ts, ID.tsUser, ID.tsPass].map((id) => page.el<HTMLInputElement>(id).value);
  const STAY = json({ ok: true, next: NEXT, aio: "", notes: [{ level: "ok", msg: "Fine" }] });

  test("saved on the page: to their fields, the address without them, no change; a new password goes from its field", async () => {
    const page = open("filled");
    page.type(ID.ts, " HTTP://user:p@ss:w@rd@192.0.2.5:8090 ");
    page.replies.push(STAY);
    await save(page);
    expect([ts(page), dirty(page)]).toEqual([["HTTP://192.0.2.5:8090", "user", "p@ss:w@rd"], ""]);
    page.type(ID.tsPass, "secret");
    expect(dirty(page)).toBe("1 изменение");
    page.replies.push(STAY);
    await save(page);
    const posted = pairs(page.calls[1]!.body);
    expect([["ts", "HTTP://192.0.2.5:8090"], ["ts_user", "user"], ["ts_pass", "secret"]].every((p) => posted.some((q) => q[0] === p[0] && q[1] === p[1]))).toBe(true);
    expect(dirty(page)).toBe("");
  });

  test("none in it, a '/' before the last '@', a failed save: the fields as typed", async () => {
    const page = open("filled");
    page.type(ID.ts, "http://u:p@192.0.2.5:8090/@x");
    page.replies.push(STAY);
    await save(page);
    expect(ts(page)).toEqual(["http://u:p@192.0.2.5:8090/@x", "tsuser", "ts p@ss&x"]);
    page.type(ID.ts, "user:secret@192.0.2.5");
    page.replies.push(json({ ok: false, errors: [{ msg: "Bad", field: null }] }));
    await save(page);
    expect(ts(page)).toEqual(["user:secret@192.0.2.5", "tsuser", "ts p@ss&x"]);
  });

  test("the address typed again while it went: stays as typed (a change), the saved ones the base", async () => {
    const page = open("filled");
    page.type(ID.ts, "user:secret@192.0.2.5");
    const answer = later();
    page.replies.push(answer.reply);
    await save(page);
    page.type(ID.ts, "192.0.2.6");
    answer.resolve(STAY);
    await flush();
    expect([ts(page), dirty(page)]).toEqual([["192.0.2.6", "tsuser", "ts p@ss&x"], "3 изменения"]);
    page.type(ID.ts, "192.0.2.5");
    page.type(ID.tsUser, "user");
    page.type(ID.tsPass, "secret");
    expect(dirty(page)).toBe("");
  });
});

describe("the config after the save (aio_sync)", () => {
  test("create: Creating… on the button and as a line, the result replaces it; the config shown with its template", async () => {
    const page = open("filled");
    const t = page.init.i18n;
    page.choose("aio_server", ATB);
    const sync = later();
    page.replies.push(json({ ok: true, next: NEXT, aio: "create" }), sync.reply);
    await save(page);
    expect(page.calls).toEqual([{ ...SAVE, body: expect.any(String) }, { ...SYNC, body: "" }]);
    expect([button(page).disabled, button(page).textContent]).toEqual([true, t.creating]);
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", t.saved], ["hint", t.creating]]);
    sync.resolve(json({ ok: true, msg: "Created", skip: false, base: ATB, mode: "debrid", conf: conf("7", "jacred") }));
    await flush();
    expect(page.replaced).toEqual([]);
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", t.saved], ["ok", "Created"]]);
    expect([button(page).disabled, button(page).textContent]).toEqual([false, t.save]);
    expect([page.el(ID.confNone).hidden, page.el(ID.confHave).hidden]).toEqual([true, false]);
    expect(page.el<HTMLAnchorElement>(ID.confLink).getAttribute("href")).toBe("https://cfg.example/7");
    expect(page.el<HTMLInputElement>(ID.confLogin).value).toBe(conf("7", null).login);
    expect([templateOf(page, "debrid"), page.el<HTMLInputElement>(ID.templateShown).value]).toEqual(["jacred", "jacred"]);
  });

  test.each([["update", "updating"], ["reset", "resetting"]] as const)("%s: its text; skip: the line gone", async (how, text) => {
    const page = open("filled");
    const sync = later();
    page.replies.push(json({ ok: true, next: NEXT, aio: how }), sync.reply);
    await save(page);
    expect(button(page).textContent).toBe(page.init.i18n[text]);
    expect(page.calls[1]!.body).toBe(how === "reset" ? "reset=1" : "");
    sync.resolve(json({ ok: true, msg: "", skip: true, base: MADE, mode: "debrid", conf: conf("1", "addons") }));
    await flush();
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.saved]]);
  });

  test("not ok: err; Saved says the settings are saved on the Dune, the notes stay", async () => {
    const page = open("filled");
    const sync = later();
    page.replies.push(json({ ok: true, next: NEXT, aio: "update", notes: [{ level: "warn", msg: "Hm" }] }), sync.reply);
    await save(page);
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.saved], ["warn", "Hm"], ["hint", page.init.i18n.updating]]);
    sync.resolve(json({ ok: false, msg: "Server down", skip: false, base: MADE, mode: "debrid", conf: conf("1", "addons") }));
    await flush();
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.savedOnDune], ["warn", "Hm"], ["err", "Server down"]]);
  });

  test("the line instead of Saved when the config failed", () => {
    const { init } = open("filled", { start: false });
    expect(init.i18n.savedOnDune).toBe("Настройки Melange сохранены на Дюне.");
  });

  test("the config gone (conf null): none shown", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: true, next: NEXT, aio: "update" }), json({ ok: false, msg: "Gone", skip: false, base: MADE, mode: "debrid", conf: null }));
    await save(page);
    expect([page.el(ID.confNone).hidden, page.el(ID.confHave).hidden]).toEqual([false, true]);
  });

  test("a base not of the choice: nothing changed", async () => {
    const page = open("filled");
    page.replies.push(json({ ok: true, next: NEXT, aio: "update" }), json({ ok: true, msg: "x", skip: false, base: "https://elsewhere.example", mode: "debrid", conf: null }));
    await save(page);
    expect([page.el(ID.confNone).hidden, page.el(ID.confHave).hidden]).toEqual([true, false]);
  });

  test("own server: the config is of the address typed", async () => {
    const page = open("filled");
    page.choose("aio_server", "own");
    page.type(ID.aioOwnUrl, "http://192.168.1.10:3000/");
    page.replies.push(json({ ok: true, next: NEXT, aio: "create" }), json({ ok: true, msg: "Created", skip: false, base: "own", mode: "debrid", conf: conf("2", null) }));
    await save(page);
    expect(page.el(ID.confHave).hidden).toBe(false);
    expect(templateOf(page, "debrid")).toBe("none");
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
    page.choose(NAME.template, "jacred");
    await save(page);
    const text = page.init.i18n.resetConfirm.replace("%s", "aiostreams.12312023.xyz").replace("%s", "Только русские трекеры");
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
    ["own config", (page) => {
      page.choose(NAME.template, "jacred");
      page.choose("watch", "own");
    }],
    ["no config there", (page) => {
      page.choose("aio_server", ATB);
      page.choose(NAME.template, "jacred");
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
    page.replies.push(json({ ok: true, next: NEXT, aio: "reset" }), json({ ok: true, msg: "Reset", skip: false, base: MADE, mode: "debrid", conf: conf("1", "addons") }));
    await page.click(ID.resetConf);
    expect(page.calls.map((call) => call.url)).toEqual([SAVE.url, SYNC.url]);
    expect(page.calls[0]!.body).toBe(encode(FILLED) + "&aio_reset=1");
    expect(page.calls[1]!.body).toBe("reset=1");
  });

  test("Reset under a mark (template unknown): the template for a new config in the confirm, the host of own server", async () => {
    const page = open("ownconf");
    await page.click(ID.resetConf);
    expect(page.confirms).toEqual([page.init.i18n.resetConfirm.replace("%s", "192.168.1.10").replace("%s", "Везде")]);
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

  test("P2P chosen over a config of Debrid: no confirm, no reset; the config of P2P made, that of Debrid kept", async () => {
    const page = open("filled");
    page.choose("watch", "p2p");
    page.replies.push(json({ ok: true, next: NEXT, aio: "create" }),
      json({ ok: true, msg: "Created", skip: false, base: MADE, mode: "p2p", conf: conf("5", "p2p") }));
    await save(page);
    expect(page.confirms).toEqual([]);
    expect(page.calls.map((call) => call.body)).toEqual([P2P_BODY, ""]);
    expect(page.el<HTMLInputElement>(ID.confLogin).value).toBe(conf("5", null).login);
    expect([templateOf(page, "debrid"), page.el<HTMLInputElement>(ID.templateShown).value]).toEqual(["addons", "addons"]);
    page.choose("watch", "debrid");
    expect(page.el<HTMLInputElement>(ID.confLogin).value).toBe(FILLED_CONF("addons").login);
  });

  test("Debrid chosen over a config of P2P: no confirm, the template of the config of Debrid posted", async () => {
    const page = open("p2p");
    page.choose("watch", "debrid");
    page.replies.push(json({ ok: true, next: NEXT, aio: "" }));
    await save(page);
    expect([page.confirms, pairs(page.calls[0]!.body).filter(([k]) => /^(watch|aio_(tpl|reset))/.test(k!))]).toEqual([
      [], [["watch", "debrid"], ["aio_tpl", "addons"], ["aio_tpl_seen", "addons"], ["aio_tpl_p2p", "p2p"], ["aio_tpl_p2p_seen", "p2p"]],
    ]);
  });

  test("P2P: another template of Debrid chosen - no confirm, no reset (it is of the config of Debrid)", async () => {
    const page = open("p2p");
    page.choose(NAME.template, "jacred");
    page.replies.push(json({ ok: true, next: NEXT, aio: "" }));
    await save(page);
    expect([page.confirms, pairs(page.calls[0]!.body).filter(([k]) => /^(watch|aio_(tpl|reset))/.test(k!))]).toEqual([
      [], [["watch", "p2p"], ["aio_tpl", "jacred"], ["aio_tpl_seen", "addons"], ["aio_tpl_p2p", "p2p"], ["aio_tpl_p2p_seen", "p2p"]],
    ]);
  });

  test("Reset to the template in P2P: the template of P2P chosen (that of the config)", async () => {
    const page = open("p2p");
    await page.click(ID.resetConf);
    expect(page.confirms).toEqual([page.init.i18n.resetConfirm.replace("%s", "aiostreams.12312023.xyz").replace("%s", "Везде")]);
  });

  test("P2P: another template of P2P over its config: confirm with its name; yes: aio_reset=1 with it", async () => {
    const page = open("p2p");
    page.choose(NAME.templateP2p, "p2p_jacred");
    await save(page);
    expect(page.confirms).toEqual([page.init.i18n.resetConfirm.replace("%s", "aiostreams.12312023.xyz").replace("%s", "Только русские трекеры")]);
    expect(page.calls).toEqual([]);
    page.confirmAnswer = true;
    page.replies.push(json({ ok: true, next: NEXT, aio: "" }));
    await save(page);
    expect(pairs(page.calls[0]!.body).filter(([k]) => /^aio_(tpl_p2p|reset)/.test(k!))).toEqual([
      ["aio_tpl_p2p", "p2p_jacred"], ["aio_tpl_p2p_seen", "p2p"], ["aio_reset", "1"],
    ]);
  });

  test("Debrid: another template of P2P chosen - no confirm, no reset (it is of the config of P2P)", async () => {
    const page = open("p2p");
    page.choose("watch", "debrid");
    page.choose(NAME.templateP2p, "p2p_jacred");
    page.replies.push(json({ ok: true, next: NEXT, aio: "" }));
    await save(page);
    expect([page.confirms, pairs(page.calls[0]!.body).filter(([k]) => /^aio_(tpl_p2p|reset)/.test(k!))]).toEqual([
      [], [["aio_tpl_p2p", "p2p_jacred"], ["aio_tpl_p2p_seen", "p2p"]],
    ]);
  });

  test("the template of P2P saved: the one for a new config of P2P, that of Debrid as it was", async () => {
    const page = open("filled");
    page.choose("watch", "p2p");
    page.choose(NAME.templateP2p, "p2p");
    page.replies.push(json({ ok: true, next: NEXT, aio: "", notes: [{ level: "ok", msg: "Fine" }] }));
    await save(page);
    expect([page.confirms, page.el<HTMLInputElement>(ID.templateP2pShown).value]).toEqual([[], "p2p"]);
    page.choose("aio_server", ATB);
    expect([templateOf(page, "p2p"), templateOf(page, "debrid")]).toEqual(["p2p", "addons"]);
  });

  test.each(["p2p", "debrid"])("%s: saved as shown over a config of P2P everywhere (of 0.43) - the one for a new config of P2P stays", async (watch) => {
    const page = open("p2p");
    page.choose("watch", watch);
    page.replies.push(json({ ok: true, next: NEXT, aio: "", notes: [{ level: "ok", msg: "Fine" }] }));
    await save(page);
    expect(pairs(page.calls[0]!.body).filter(([k]) => /^aio_tpl_p2p/.test(k!))).toEqual([["aio_tpl_p2p", "p2p"], ["aio_tpl_p2p_seen", "p2p"]]);
    page.choose("aio_server", ATB);
    page.choose("watch", "p2p");
    expect([templateOf(page, "p2p"), page.el<HTMLInputElement>(ID.templateP2pShown).value]).toEqual([
      "p2p_jacred", "p2p_jacred",
    ]);
  });

  test("P2P: another template of Debrid saved, the sync of P2P - that of Debrid back to its config: no question in Debrid", async () => {
    const page = open("p2p");
    page.choose(NAME.template, "jacred");
    page.replies.push(json({ ok: true, next: NEXT, aio: "update" }),
      json({ ok: true, msg: "Updated", skip: false, base: MADE, mode: "p2p", conf: FILLED_CONF("p2p") }));
    await save(page);
    expect([templateOf(page, "debrid"), page.el<HTMLInputElement>(ID.templateShown).value]).toEqual(["addons", "addons"]);
    // The one chosen is that for a new config of Debrid, as the save stored it.
    page.choose("aio_server", "https://aiostreams.kompotik.com");
    expect(templateOf(page, "debrid")).toBe("jacred");
    page.choose("aio_server", MADE);
    page.choose("watch", "debrid");
    page.replies.push(json({ ok: true, next: NEXT, aio: "", notes: [{ level: "ok", msg: "Fine" }] }));
    await save(page);
    expect([page.confirms, pairs(page.calls[2]!.body).filter(([k]) => /^aio_(tpl|reset)/.test(k!) && !/p2p/.test(k!))]).toEqual([
      [], [["aio_tpl", "addons"], ["aio_tpl_seen", "addons"]],
    ]);
  });

  test.each([false, true])("ok %p: a failed reset in P2P keeps its template chosen; a done one shows that of the config", async (ok) => {
    const page = open("p2p");
    page.confirmAnswer = true;
    page.choose(NAME.templateP2p, "p2p_jacred");
    page.replies.push(json({ ok: true, next: NEXT, aio: "reset" }),
      json({ ok: ok, msg: "m", skip: false, base: MADE, mode: "p2p", conf: FILLED_CONF(ok ? "p2p_jacred" : "p2p") }));
    await save(page);
    expect([templateOf(page, "p2p"), page.el<HTMLInputElement>(ID.templateP2pShown).value]).toEqual(
      ok ? ["p2p_jacred", "p2p_jacred"] : ["p2p_jacred", "p2p"],
    );
    expect(templateOf(page, "debrid")).toBe("addons");
  });

  test("a sync of the config of P2P (forgotten): that of Debrid of the server stays", async () => {
    const page = open("p2p");
    page.replies.push(json({ ok: true, next: NEXT, aio: "update" }),
      json({ ok: false, msg: "Gone", skip: false, base: MADE, mode: "p2p", conf: null }));
    await save(page);
    expect([page.el(ID.confNone).hidden, page.el(ID.confHave).hidden]).toEqual([false, true]);
    page.choose("watch", "debrid");
    expect(page.el<HTMLInputElement>(ID.confLogin).value).toBe("00000000-0000-4000-8000-000000000004");
  });

  test.each([false, true])("ok %p: a failed reset keeps the template chosen; a done one shows the template of the config", async (ok) => {
    const page = open("filled");
    page.confirmAnswer = true;
    page.choose(NAME.template, "jacred");
    page.replies.push(json({ ok: true, next: NEXT, aio: "reset" }),
      json({ ok: ok, msg: "m", skip: false, base: MADE, mode: "debrid", conf: FILLED_CONF(ok ? "jacred" : "addons") }));
    await save(page);
    expect([templateOf(page, "debrid"), page.el<HTMLInputElement>(ID.templateShown).value]).toEqual(
      ok ? ["jacred", "jacred"] : ["jacred", "addons"],
    );
  });
});
