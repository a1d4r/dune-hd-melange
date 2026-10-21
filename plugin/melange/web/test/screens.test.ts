// The page of screens (0.43.0): the main screen recounted by the rules of PHP, the switch of the Debrid keys and
// the bar of the save with the count of the changes.

import { afterEach, describe, expect, test } from "bun:test";
import { CLS, ID, NAME, ROWS } from "../src/dom";
import { plural } from "../src/i18n";
import { cleanup, FIXTURES, flush, HANG, json, later, lines, NET, open, type Page, templateOf, TOKEN } from "./harness";

afterEach(cleanup);

// What the main screen says: the state (null while hidden: the first run) and [dot, value] of every line.
function home(page: Page) {
  const state = page.el(ID.state);
  const shownLines = Array.from(state.querySelectorAll<HTMLElement>("p[data-st]")).filter((line) => !line.hidden);
  return {
    state: state.hidden ? null : [state.className, state.getAttribute("data-st"), shownLines.map((line) => line.getAttribute("data-st"))],
    rows: ROWS.map((row) => [row, page.el("d" + row).className, page.el("v" + row).textContent]),
  };
}

describe("the main screen", () => {
  test.each([...FIXTURES])("%s: the script says what PHP said (the same rules)", (name) => {
    const php = home(open(name, { start: false }));
    cleanup();
    expect(home(open(name))).toEqual(php);
  });

  test("follows the form: the answer, the keys, the link of own config, JacRed, TorrServer, the servers", () => {
    const page = open("filled");
    const t = page.init.i18n;
    const st = () => home(page).state;
    const value = (row: string) => [page.el("d" + row).className, page.el("v" + row).textContent];
    expect(st()).toEqual(["state ok", "ok", ["ok"]]);
    page.type(ID.rdKey, "");
    expect(value("src")).toEqual(["dot ok", "Debrid · TorBox · везде"]);
    page.type(ID.tbKey, "  ");
    expect(st()).toEqual(["state warn", "key", ["key"]]);
    expect(value("src")).toEqual(["dot warn", "Debrid · " + t.needKey + " · везде"]);
    page.type(ID.rdKey, "x");
    page.choose(NAME.template, "jacred");
    expect(value("src")).toEqual(["dot ok", "Debrid · Real-Debrid · только русские"]);
    // Another server: no config there.
    page.choose("aio_server", "https://aio.atbphosting.com");
    expect(st()).toEqual(["state warn", "conf", ["conf"]]);
    page.choose("watch", "own");
    expect([st(), value("src")]).toEqual([["state warn", "man", ["man"]], ["dot warn", t.watchOwn + " · " + t.needLink]]);
    page.type(ID.manifest, "https://aio.example/manifest.json");
    expect([st(), value("src")]).toEqual([["state ok", "ok", ["ok"]], ["dot ok", t.watchOwn]]);
    expect(value("torr")).toEqual(["dot", "http://192.168.1.10:8090"]);
    page.choose("watch", "p2p");
    expect([st(), value("src"), value("torr")]).toEqual([
      ["state warn", "conf", ["conf"]], ["dot warn", t.modeP2p + " · " + t.viaTs + " · только русские"], ["dot ok", "http://192.168.1.10:8090"],
    ]);
    // The template of P2P (0.44.0): its short name.
    page.choose(NAME.templateP2p, "p2p");
    expect(value("src")).toEqual(["dot warn", t.modeP2p + " · " + t.viaTs + " · везде"]);
    // A login and password typed in the address (0.45.0): never on the main screen.
    page.type(ID.ts, "HTTP://user:p@ss@192.0.2.10:8090");
    expect(value("torr")).toEqual(["dot ok", "HTTP://192.0.2.10:8090"]);
    // Nothing before the "@" or a "/" there: no login (as aio_ts_userinfo), the address as typed (a bad one).
    for (const typed of ["@h", "http://@h", "http://u:p@h:8090/@x"]) {
      page.type(ID.ts, typed);
      expect(value("torr")).toEqual(["dot ok", typed]);
    }
    page.type(ID.ts, "");
    expect(value("torr")).toEqual(["dot ok", t.tsDune]);
    // Own JacRed: the host of its address; none: warn.
    expect(value("voices")).toEqual(["dot ok", "192.168.1.10"]);
    page.type(ID.jacredOwnUrl, "nonsense");
    expect(value("voices")).toEqual(["dot warn", t.ownJacred]);
    page.choose("jacred", "jac.red");
    expect(value("voices")).toEqual(["dot ok", "jac.red"]);
    // A server block: its name, else its address.
    page.type("s1_name", " ");
    expect(value("dl")).toEqual(["dot ok", "http://192.168.1.10:8080"]);
    page.type("s1_url", "");
    expect(value("dl")).toEqual(["dot", t.noServers]);
  });

  test("the first run: the question, the state hidden, the lines follow", () => {
    const page = open("empty");
    expect([home(page).state, page.el(ID.first).hidden]).toEqual([null, false]);
    expect(page.el("start").getAttribute("href")).toBe("#src");
    page.choose("watch", "p2p");
    expect(page.el("vsrc").textContent).toBe(page.init.i18n.modeP2p + " · " + page.init.i18n.viaTs + " · только русские");
  });

  test("the first save that stays on the page: the state instead of the question, as of the form now", async () => {
    const page = open("empty");
    page.type(ID.rdKey, "RDKEY");
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "create", notes: [] }),
      json({ ok: true, msg: "Created", skip: false, base: "https://aiostreams.kompotik.com", mode: "debrid",
        conf: { cfg: "https://cfg.example/1", login: "l", pass: "p", tpl: "addons" } }));
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    expect([page.el(ID.first).hidden, home(page).state]).toEqual([true, ["state ok", "ok", ["ok"]]]);
  });

  test("a failed save: still the first run", async () => {
    const page = open("empty");
    page.replies.push(json({ ok: false, errors: [{ msg: "Key", field: "rd_key" }] }));
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    expect([page.el(ID.first).hidden, home(page).state]).toEqual([false, null]);
  });

  test("a config made by the save on the page: the state turns ok", async () => {
    const page = open("filled");
    page.choose("aio_server", "https://aio.atbphosting.com");
    expect(home(page).state![1]).toBe("conf");
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "create", notes: [] }),
      json({ ok: true, msg: "Created", skip: false, base: "https://aio.atbphosting.com", mode: "debrid",
        conf: { cfg: "https://cfg.example/1", login: "l", pass: "p", tpl: "addons" } }));
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    expect(home(page).state).toEqual(["state ok", "ok", ["ok"]]);
  });
});

describe("the keys of Debrid", () => {
  const vis = (page: Page) => ({
    seg: !page.el(ID.debridSwitch).hidden,
    rd: !page.el(ID.rdBox).hidden,
    tb: !page.el(ID.tbBox).hidden,
    addRd: !page.el(ID.rdAdd).hidden,
    addTb: !page.el(ID.tbAdd).hidden,
    pressed: Array.from(page.el(ID.debridSwitch).querySelectorAll("button"), (button) => button.getAttribute("aria-pressed")),
  });
  const key = (page: Page, k: string) => page.el(ID.debridSwitch).querySelector<HTMLButtonElement>("[data-k=" + k + "]")!;

  test("both empty: the switch and one field; typed in, the switch goes and Add of the other one comes", () => {
    const page = open("empty");
    expect(vis(page)).toEqual({ seg: true, rd: true, tb: false, addRd: false, addTb: false, pressed: ["true", "false"] });
    key(page, "tb").click();
    expect(vis(page)).toEqual({ seg: true, rd: false, tb: true, addRd: false, addTb: false, pressed: ["false", "true"] });
    page.type(ID.tbKey, "TBKEY");
    expect(vis(page)).toEqual({ seg: false, rd: false, tb: true, addRd: true, addTb: false, pressed: ["false", "true"] });
    // Erased: the switch again.
    page.type(ID.tbKey, "");
    expect(vis(page).seg).toBe(true);
    page.type(ID.tbKey, "TBKEY");
    page.el(ID.rdAdd).click();
    expect(vis(page)).toEqual({ seg: false, rd: true, tb: true, addRd: false, addTb: false, pressed: ["false", "true"] });
    expect(page.doc.activeElement).toBe(page.el(ID.rdKey));
  });

  test("both filled: both fields, no switch, no Add", () => {
    const page = open("filled");
    expect(vis(page)).toEqual({ seg: false, rd: true, tb: true, addRd: false, addTb: false, pressed: ["true", "false"] });
  });

  test("one filled: its field and Add of the other one", () => {
    const page = open("filled", { before: (doc) => ((doc.getElementById(ID.tbKey) as HTMLInputElement).value = "") });
    expect(vis(page)).toEqual({ seg: false, rd: true, tb: false, addRd: false, addTb: true, pressed: ["true", "false"] });
    page.el(ID.tbAdd).click();
    expect([vis(page).rd, vis(page).tb, vis(page).addTb]).toEqual([true, true, false]);
    // ownconf.html: TorBox only.
    cleanup();
    const tb = open("ownconf");
    expect(vis(tb)).toEqual({ seg: false, rd: false, tb: true, addRd: true, addTb: false, pressed: ["false", "true"] });
  });
});

describe("the bar of the save", () => {
  const bar = (page: Page) => (page.el(ID.bar).hidden ? null : page.el(ID.dirty).textContent);

  test("hidden with no change; the count of the fields changed, back to the value: one less, none: hidden", () => {
    const page = open("filled");
    expect(bar(page)).toBeNull();
    page.type(ID.ts, "http://192.168.1.10:8091");
    expect(bar(page)).toBe("1 изменение");
    page.choose("jacred", "jac.red");
    page.type("s2_name", "x");
    expect(bar(page)).toBe("3 изменения");
    page.type(ID.tmdbKey, "");
    page.choose(NAME.template, "jacred");
    expect(bar(page)).toBe("5 изменений");
    page.choose("jacred", "own");
    page.type("s2_name", "");
    page.type(ID.tmdbKey, "TMDBTESTKEY000000000001");
    page.choose(NAME.template, "addons");
    expect(bar(page)).toBe("1 изменение");
    page.type(ID.ts, "http://192.168.1.10:8090");
    expect(bar(page)).toBeNull();
  });

  test("a config to create: shown with no change (Save and create)", () => {
    const page = open("filled", { before: (doc) => ((doc.querySelector("input[name=aio_server][checked]") as HTMLInputElement).value = "https://aio.atbphosting.com") });
    expect([bar(page), page.form.querySelector("." + CLS.save)!.textContent]).toEqual(["", page.init.i18n.saveAndCreate]);
  });

  test("the template of the config not known yet (the mark none: Save finds it): shown with no change; not for own config", () => {
    const page = open("ownconf");
    expect([templateOf(page, "debrid"), bar(page)]).toEqual(["none", ""]);
    page.choose("watch", "own");
    expect(bar(page)).toBe("1 изменение");
  });

  test("the save finds the template of the config: no change after it (the mark none gone is none)", async () => {
    const page = open("ownconf");
    page.type(ID.ts, "http://192.168.1.10:8091");
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "update", notes: [] }),
      json({ ok: true, msg: "Updated", skip: false, base: "own", mode: "debrid",
        conf: { cfg: "https://cfg.example/1", login: "l", pass: "p", tpl: "addons" } }));
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    expect(templateOf(page, "debrid")).toBe("addons");
    page.el(ID.hideResult).click();
    expect(bar(page)).toBeNull();
  });

  test("the first run: Torrents chosen - a change and a config to create", () => {
    const page = open("empty");
    expect(bar(page)).toBeNull();
    page.choose("watch", "p2p");
    expect(bar(page)).toBe("1 изменение");
    page.choose("watch", "debrid");
    expect(bar(page)).toBeNull();
  });

  test("a save that stays on the page: its lines shown, what is saved no change any more", async () => {
    const page = open("filled");
    page.type(ID.ts, "http://192.168.1.10:8091");
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "", notes: [{ level: "warn", msg: "Note" }] }));
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    expect(lines(page.el(ID.saveResult))).toEqual([["ok", page.init.i18n.saved], ["warn", "Note"]]);
    expect([bar(page), page.el(ID.hideResult).hidden]).toEqual(["", false]);
    page.type(ID.ts, "http://192.168.1.10:8090");
    expect(bar(page)).toBe("1 изменение");
  });

  test("Hide: the results gone, the bar with them while nothing to save; a new save - new results", async () => {
    const page = open("filled");
    page.type(ID.ts, "http://192.168.1.10:8091");
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "", notes: [{ level: "warn", msg: "Note" }] }));
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    page.el(ID.hideResult).click();
    expect([lines(page.el(ID.saveResult)), page.el(ID.hideResult).hidden, bar(page)]).toEqual([[], true, null]);
    page.type(ID.ts, "http://192.168.1.10:8090");
    page.replies.push(json({ ok: false, errors: [{ msg: "TorrServer", field: "ts" }] }));
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    expect([lines(page.el(ID.saveResult)), page.el(ID.hideResult).hidden, bar(page)]).toEqual([[["err", "TorrServer"]], false, "1 изменение"]);
    page.el(ID.hideResult).click();
    expect([page.el(ID.hideResult).hidden, bar(page)]).toEqual([true, "1 изменение"]);
  });

  test("no Hide while the config is made (its line comes into the results)", async () => {
    const page = open("filled");
    page.choose("aio_server", "https://aio.atbphosting.com");
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "create", notes: [] }), HANG);
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    expect([lines(page.el(ID.saveResult)).length, page.el(ID.hideResult).hidden, bar(page)]).toEqual([2, true, ""]);
  });

  test("typed while the save goes: still a change (saved is what was posted)", async () => {
    const page = open("filled");
    page.type(ID.ts, "http://192.168.1.10:8091");
    const answer = later();
    page.replies.push(answer.reply);
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    page.type("s2_name", "x");
    answer.resolve(json({ ok: true, next: TOKEN + "&saved=1", aio: "", notes: [{ level: "warn", msg: "Note" }] }));
    await flush();
    expect(bar(page)).toBe("1 изменение");
    page.type("s2_name", "");
    expect(bar(page)).toBe("");
  });

  test.each([["ok", true], ["no connection", false]] as const)("typed while the config is made (%s): still a change", async (_name, ok) => {
    const page = open("filled");
    page.choose("aio_server", "https://aio.atbphosting.com");
    const sync = later();
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "create", notes: [] }), sync.reply);
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    page.type(ID.ts, "http://192.168.1.10:8091");
    expect(bar(page)).toBe("1 изменение");
    sync.resolve(ok ? json({ ok: true, msg: "Created", skip: false, base: "https://aio.atbphosting.com", mode: "debrid",
      conf: { cfg: "https://cfg.example/7", login: "l", pass: "p", tpl: "jacred" } }) : NET);
    await flush();
    // The template of the new config (jacred) is set by the script: no change.
    expect([templateOf(page, "debrid"), bar(page)]).toEqual([ok ? "jacred" : "addons", "1 изменение"]);
    page.type(ID.ts, "http://192.168.1.10:8090");
    expect(bar(page)).toBe("");
  });

  test("errors of a save: the count stays, the errors in the bar", async () => {
    const page = open("filled");
    page.type(ID.ts, "bad");
    page.replies.push(json({ ok: false, errors: [{ msg: "TorrServer", field: "ts" }] }));
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    expect([bar(page), lines(page.el(ID.saveResult))]).toEqual(["1 изменение", [["err", "TorrServer"]]]);
  });

  test("Duplicate: the block filled counts as typed (the bar, the line of the servers)", () => {
    const page = open("filled");
    page.form.querySelector<HTMLButtonElement>("." + CLS.serverDup)!.click();
    expect([bar(page), page.el("vdl").textContent]).toEqual(["5 изменений", "qBittorrent, qBittorrent (копия)"]);
  });

  test("the forms of the count: Russian three, English two", () => {
    const page = open("filled", { start: false });
    const t = page.init.i18n;
    const ru = { ...t, changeOne: "%d изменение", changeFew: "%d изменения", changeMany: "%d изменений" };
    expect([1, 2, 4, 5, 11, 12, 14, 21, 22, 25, 101, 111].map((n) => plural(ru, "ru", n))).toEqual([
      "1 изменение", "2 изменения", "4 изменения", "5 изменений", "11 изменений", "12 изменений", "14 изменений",
      "21 изменение", "22 изменения", "25 изменений", "101 изменение", "111 изменений",
    ]);
    const en = { ...t, changeOne: "%d change", changeFew: "%d changes", changeMany: "%d changes" };
    expect([1, 2, 21].map((n) => plural(en, "en", n))).toEqual(["1 change", "2 changes", "21 changes"]);
  });
});
