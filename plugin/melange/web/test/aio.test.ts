// §2.3 AIOStreams config: the answer of "Where to get films" (watch), the server (own one by its address), its config, the template, the text of
// the save button, "Copy" of the password.

import { afterEach, describe, expect, mock, test } from "bun:test";
import { CLS, DATA, ID, NAME } from "../src/dom";
import { cleanup, flush, json, later, lines, open, type Page, setGlobal, shown, templateOf } from "./harness";

afterEach(cleanup);

const OWN_CFG = "http://192.168.1.10:3000/stremio/00000000-0000-4000-8000-000000000001/dGVzdA/configure";
const ATB = "https://aio.atbphosting.com";
const ATB_CFG = ATB + "/stremio/00000000-0000-4000-8000-000000000002/dGVzdA/configure";
const MADE = "https://aiostreams.12312023.xyz";
// The third server of p2p.html and ownconf.html: a config changed in AIOStreams (of Debrid, of P2P).
const WIZ = "https://aiostreams-stable.forthewizards.uk";

const server = (page: Page, value: string) => page.choose("aio_server", value);
const conf = (page: Page) => ({
  none: shown(page.el(ID.confNone)),
  have: shown(page.el(ID.confHave)),
  link: page.el<HTMLAnchorElement>(ID.confLink).getAttribute("href"),
  login: page.el<HTMLInputElement>(ID.confLogin).value,
  pass: page.el<HTMLInputElement>(ID.confPass).value,
});
// The template choice of a mode: the card checked, else the mark shown (templateOf), the one shown (posted as seen),
// the lines of the marks shown.
const choiceOf = (page: Page, mode: "debrid" | "p2p") => ({
  value: templateOf(page, mode),
  seen: page.el<HTMLInputElement>(mode === "p2p" ? ID.templateP2pShown : ID.templateShown).value,
  marks: Array.from(page.el(mode === "p2p" ? ID.templateP2pMarks : ID.templateMarks).querySelectorAll("[" + DATA.mark + "]:not([hidden])"), (line) =>
    line.getAttribute(DATA.mark),
  ),
});
const template = (page: Page) => choiceOf(page, "debrid");
// The template choice of P2P (0.44.0).
const templateP2p = (page: Page) => choiceOf(page, "p2p");
const saveText = (page: Page) => page.form.querySelector("." + CLS.save)!.textContent || "";

describe("answer", () => {
  test("own: the own text of JacRed; no 403 or LAN note; and back", () => {
    const page = open("filled");
    const vis = () => ({
      jr403: shown(page.el(ID.jacred403)), lan: shown(page.el(ID.jacredLan)), text: page.el(ID.jacredText).textContent || "",
    });
    const text = (attr: string) => page.el(ID.jacredText).getAttribute(attr) || "";
    expect(vis()).toEqual({ jr403: false, lan: true, text: text("data-made") });
    page.choose("watch", "own");
    expect(vis()).toEqual({ jr403: false, lan: false, text: text("data-own") });
    page.choose("watch", "debrid");
    expect(vis()).toEqual({ jr403: false, lan: true, text: text("data-made") });
  });

  test("a page of the answer own: set so at the start", () => {
    const page = open("own");
    expect(page.el(ID.jacredText).textContent).toBe(page.el(ID.jacredText).getAttribute("data-own") || "");
  });

  test("TorrServer: its field stays on #torr in every answer (0.44.0), Torrents link there", () => {
    const page = open("p2p");
    const where = () => page.el(ID.ts).closest("section")!.id;
    expect(where()).toBe("torr");
    page.choose("watch", "debrid");
    page.choose("watch", "p2p");
    expect(where()).toBe("torr");
    expect(page.doc.querySelector<HTMLAnchorElement>("#mp2p a[href='#torr']")).not.toBeNull();
  });

  test("the line of the server on the main screen: the host of the choice (own one: of its address, none - otherAioServer), ok - a config of the mode there", () => {
    const page = open("ownconf");
    const row = () => [page.el("dcfg").className, page.el("vcfg").textContent];
    expect(row()).toEqual(["dot ok", "192.168.1.10"]);
    page.type(ID.aioOwnUrl, " http://192.168.1.10:3001 ");
    expect(row()).toEqual(["dot warn", "192.168.1.10"]);
    page.type(ID.aioOwnUrl, "");
    expect(row()).toEqual(["dot warn", page.init.i18n.otherAioServer]);
    server(page, ATB);
    expect(row()).toEqual(["dot ok", "aio.atbphosting.com"]);
    page.choose("watch", "p2p");
    expect(row()).toEqual(["dot warn", "aio.atbphosting.com"]);
    // Own config: no dot (the line hidden).
    page.choose("watch", "own");
    expect(row()).toEqual(["dot", "aio.atbphosting.com"]);
  });
});

describe("server", () => {
  test("own: no TMDB note, no LAN note; TMDB box by data-tmdb; 403 note by data-jr403 and the JacRed chosen", () => {
    const page = open("filled");
    const vis = () => ({
      tmdbBox: shown(page.el(ID.tmdbBox)), tmdbNote: shown(page.el(ID.tmdbNote)),
      lan: shown(page.el(ID.jacredLan)), jr403: shown(page.el(ID.jacred403)),
    });
    // TMDB of its own.
    expect(vis()).toEqual({ tmdbBox: false, tmdbNote: true, lan: true, jr403: false });
    // The 403 note only while the JacRed that refuses the server is chosen (here: own one).
    server(page, "https://aiostreams.fortheweak.cloud");
    expect(vis()).toEqual({ tmdbBox: true, tmdbNote: true, lan: true, jr403: false });
    page.choose("jacred", "jacred.stream");
    expect(vis()).toEqual({ tmdbBox: true, tmdbNote: true, lan: true, jr403: true });
    page.choose("jacred", "jacred.kompotik.com");
    expect(vis().jr403).toBe(false);
    page.choose("jacred", "jacred.stream");
    server(page, "own");
    expect(vis()).toEqual({ tmdbBox: true, tmdbNote: false, lan: false, jr403: false });
    // The 403 note not for own config.
    server(page, "https://aiostreams.fortheweak.cloud");
    page.choose("watch", "own");
    expect(vis().jr403).toBe(false);
  });

  test("an address typed into own server (its card chosen): the line, TMDB, the button follow; another card: the address kept", () => {
    const page = open("filled");
    const posted = () => new page.doc.defaultView!.FormData(page.form).get("aio_own_url");
    const row = () => [page.el("dcfg").className, page.el("vcfg").textContent];
    server(page, "own");
    expect(row()).toEqual(["dot warn", page.init.i18n.otherAioServer]);
    page.type(ID.aioOwnUrl, "http://192.0.2.50:3000");
    expect(row()).toEqual(["dot warn", "192.0.2.50"]);
    expect([shown(page.el(ID.tmdbBox)), saveText(page)]).toEqual([true, page.init.i18n.saveAndCreate]);
    server(page, MADE);
    expect([row(), posted()]).toEqual([["dot ok", "aiostreams.12312023.xyz"], "http://192.0.2.50:3000"]);
  });

  test("the config of the chosen server: link, login, password; none: the note; the copy result cleared", () => {
    const page = open("ownconf");
    expect(conf(page)).toEqual({ none: false, have: true, link: OWN_CFG, login: "00000000-0000-4000-8000-000000000001", pass: "00112233445566778899aabb" });
    page.el(ID.copyResult).textContent = "copied";
    server(page, ATB);
    expect(conf(page)).toEqual({ none: false, have: true, link: ATB_CFG, login: "00000000-0000-4000-8000-000000000002", pass: "aabbccddeeff001122334455" });
    expect(page.el(ID.copyResult).textContent).toBe("");
    server(page, "https://aiostreams.stremio.ru");
    expect([conf(page).none, conf(page).have]).toEqual([true, false]);
    server(page, "own");
    expect(conf(page)).toEqual({ none: false, have: true, link: OWN_CFG, login: "00000000-0000-4000-8000-000000000001", pass: "00112233445566778899aabb" });
  });

  test("own server: its config only for its address, as normalized (spaces, a slash, leading zeros of the port, case)", () => {
    const page = open("ownconf");
    page.type(ID.aioOwnUrl, "http://192.168.1.10:3001");
    expect([conf(page).none, conf(page).have]).toEqual([true, false]);
    page.type(ID.aioOwnUrl, "  HTTP://192.168.1.10:03000/ ");
    expect([conf(page).none, conf(page).have]).toEqual([false, true]);
    page.type(ID.aioOwnUrl, "http://192.168.1.10:3000/x");
    expect(conf(page).have).toBe(false);
  });

  test("own server on a page without its config: none for its address either", () => {
    const page = open("filled");
    server(page, "own");
    page.type(ID.aioOwnUrl, "http://192.168.1.10:3000");
    expect([conf(page).none, conf(page).have]).toEqual([true, false]);
  });
});

describe("template", () => {
  test("by the config of the server: its template, a mark (custom, none) only while shown, else the one for a new config", () => {
    const page = open("ownconf");
    expect(template(page)).toEqual({ value: "none", seen: "none", marks: ["none"] });
    server(page, ATB);
    expect(template(page)).toEqual({ value: "custom", seen: "custom", marks: ["custom"] });
    server(page, "https://aiostreams.stremio.ru");
    expect(template(page)).toEqual({ value: "addons", seen: "addons", marks: [] });
    server(page, "own");
    expect(template(page).value).toBe("none");
    // The same server and config again: the choice kept (a card checked: no line of the mark).
    page.choose(NAME.template, "jacred");
    page.type(ID.rdKey, "x");
    expect(template(page)).toEqual({ value: "jacred", seen: "none", marks: [] });
  });

  test("at the start the choice of the page stays (a form restored by the browser)", () => {
    const page = open("filled", { before: (doc) => (doc.querySelector<HTMLInputElement>("input[name=" + NAME.template + "][value=jacred]")!.checked = true) });
    expect([template(page).value, template(page).seen]).toEqual(["jacred", "addons"]);
  });

  test("change of the template: only the card checked", () => {
    const page = open("filled");
    page.choose(NAME.template, "jacred");
    expect(template(page)).toEqual({ value: "jacred", seen: "addons", marks: [] });
  });
});

describe("mode", () => {
  const mode = (page: Page) => page.form.querySelector<HTMLInputElement>("input[name=watch]:checked")!.value;
  const login = (page: Page) => (conf(page).have ? conf(page).login : null);

  test("the answer, one for all servers: another server keeps it", () => {
    const page = open("p2p");
    expect(mode(page)).toBe("p2p");
    server(page, ATB);
    expect(mode(page)).toBe("p2p");
    server(page, WIZ);
    expect(mode(page)).toBe("p2p");
    page.choose("watch", "debrid");
    server(page, MADE);
    expect(mode(page)).toBe("debrid");
  });

  test("the config shown: of the server in the mode chosen (a config of each); none of the mode: the note", () => {
    const page = open("p2p");
    expect(login(page)).toBe("00000000-0000-4000-8000-000000000001");
    page.choose("watch", "debrid");
    expect(login(page)).toBe("00000000-0000-4000-8000-000000000004");
    server(page, ATB);
    expect(login(page)).toBe("00000000-0000-4000-8000-000000000002");
    page.choose("watch", "p2p");
    expect([conf(page).none, conf(page).have]).toEqual([true, false]);
  });

  test("the template choice: of the config of Debrid in either mode, else the one for a new config", () => {
    const page = open("p2p");
    expect([template(page).value, template(page).seen]).toEqual(["addons", "addons"]);
    page.choose("watch", "debrid");
    expect([template(page).value, template(page).seen]).toEqual(["addons", "addons"]);
    server(page, WIZ);
    expect([template(page).value, template(page).seen]).toEqual(["custom", "custom"]);
    page.choose("watch", "p2p");
    expect([template(page).value, template(page).seen]).toEqual(["custom", "custom"]);
    server(page, "https://aiostreams.stremio.ru");
    expect([template(page).value, template(page).seen]).toEqual(["jacred", "jacred"]);
  });

  test("the template choice of P2P: of the config of P2P of the server, else the one for a new config (p2p_jacred); apart from Debrid", () => {
    const page = open("p2p");
    expect(templateP2p(page)).toEqual({ value: "p2p", seen: "p2p", marks: [] });
    server(page, ATB);
    expect([templateP2p(page), template(page).value]).toEqual([{ value: "p2p_jacred", seen: "p2p_jacred", marks: [] }, "addons"]);
    page.choose("watch", "debrid");
    server(page, WIZ);
    expect([templateP2p(page).value, template(page).value]).toEqual(["p2p_jacred", "custom"]);
    server(page, MADE);
    expect([templateP2p(page).value, templateP2p(page).seen, template(page).value]).toEqual(["p2p", "p2p", "addons"]);
    // Changed: the same server and config again keep the choice.
    page.choose(NAME.templateP2p, "p2p_jacred");
    page.choose("watch", "p2p");
    expect([templateP2p(page), template(page).value]).toEqual([{ value: "p2p_jacred", seen: "p2p", marks: [] }, "addons"]);
  });

  test("the template choice of P2P, configs of 0.42 (ownconf.html): custom of P2P shown, a mark", () => {
    const page = open("ownconf");
    server(page, WIZ);
    expect(templateP2p(page)).toEqual({ value: "custom", seen: "custom", marks: ["custom"] });
    server(page, ATB);
    expect(templateP2p(page).marks).toEqual([]);
  });

  test("configs of 0.42 (ownconf.html): each in the cell of its mode, custom of P2P only in P2P", () => {
    const page = open("ownconf");
    expect(mode(page)).toBe("debrid");
    server(page, ATB);
    expect([template(page).value, login(page)]).toEqual(["custom", "00000000-0000-4000-8000-000000000002"]);
    server(page, WIZ);
    expect([template(page).value, login(page)]).toEqual(["addons", null]);
    page.choose("watch", "p2p");
    expect([template(page).value, login(page)]).toEqual(["addons", "00000000-0000-4000-8000-000000000003"]);
  });

  test("chosen: the answer kept while typing", () => {
    const page = open("filled");
    expect(mode(page)).toBe("debrid");
    page.choose("watch", "p2p");
    expect(mode(page)).toBe("p2p");
    page.type(ID.rdKey, "x");
    expect(mode(page)).toBe("p2p");
    page.choose("watch", "debrid");
    expect(mode(page)).toBe("debrid");
  });

  test("Save and create: P2P without a key; with a config of the mode: Save", () => {
    const page = open("p2p");
    const { save, saveAndCreate } = page.init.i18n;
    expect(saveText(page)).toBe(save);
    server(page, ATB);
    expect(saveText(page)).toBe(saveAndCreate);
    page.choose("watch", "debrid");
    expect(saveText(page)).toBe(save);
    server(page, "https://aiostreams.stremio.ru");
    expect(saveText(page)).toBe(save);
  });

  test("JacRed notes in P2P: no 403 of the server (Magnetio asks), the home address one for own server too", () => {
    const page = open("p2p");
    const vis = () => ({ jr403: shown(page.el(ID.jacred403)), lan: shown(page.el(ID.jacredLan)) });
    server(page, "https://aiostreams.fortheweak.cloud");
    page.choose("jacred", "jacred.stream");
    expect(vis()).toEqual({ jr403: false, lan: true });
    page.choose("watch", "debrid");
    expect(vis()).toEqual({ jr403: true, lan: true });
    server(page, "own");
    page.choose("watch", "debrid");
    expect(vis()).toEqual({ jr403: false, lan: false });
    page.choose("watch", "p2p");
    expect(vis()).toEqual({ jr403: false, lan: true });
  });

  test("JacRed notes in P2P on a server with Torznab (data-p2ptz): as in Debrid - the server asks JacRed", () => {
    const page = open("p2p");
    const vis = () => ({ jr403: shown(page.el(ID.jacred403)), lan: shown(page.el(ID.jacredLan)) });
    const kompotik = page.doc.querySelector<HTMLInputElement>('input[name=aio_server][value="https://aiostreams.kompotik.com"]')!;
    expect(kompotik.getAttribute("data-p2ptz")).toBe("1");
    // No server of the list both refused by a JacRed and with Torznab: the 403 note on a made-up one.
    kompotik.setAttribute("data-jr403", "jacred.stream");
    server(page, "https://aiostreams.kompotik.com");
    page.choose("jacred", "jacred.stream");
    expect(vis()).toEqual({ jr403: true, lan: true });
    page.choose("watch", "debrid");
    expect(vis()).toEqual({ jr403: true, lan: true });
    kompotik.removeAttribute("data-p2ptz");
    page.choose("watch", "p2p");
    expect(vis()).toEqual({ jr403: false, lan: true });
  });
});

describe("save button text", () => {
  test("Save and create: Debrid, no config, a RD or TorBox key; else Save", () => {
    const page = open("empty");
    const { save, saveAndCreate } = page.init.i18n;
    expect(saveText(page)).toBe(save);
    page.type(ID.rdKey, "   ");
    expect(saveText(page)).toBe(save);
    page.type(ID.tbKey, "TBKEY");
    expect(saveText(page)).toBe(saveAndCreate);
    page.choose("watch", "own");
    expect(saveText(page)).toBe(save);
    page.choose("watch", "debrid");
    expect(saveText(page)).toBe(saveAndCreate);
  });

  test("a config there: Save; a server without one: Save and create", () => {
    const page = open("filled");
    expect(saveText(page)).toBe(page.init.i18n.save);
    server(page, ATB);
    expect(saveText(page)).toBe(page.init.i18n.saveAndCreate);
  });

  test("not changed while a save goes; after it, by the keys again", async () => {
    const page = open("empty");
    const answer = later();
    page.replies.push(answer.reply);
    page.form.querySelector<HTMLButtonElement>("." + CLS.save)!.click();
    await flush();
    page.type(ID.rdKey, "RDKEY");
    expect(saveText(page)).toBe(page.init.i18n.saving);
    answer.resolve(json({ ok: false, errors: [{ msg: "Bad", field: null }] }));
    await flush();
    expect(saveText(page)).toBe(page.init.i18n.saveAndCreate);
  });
});

describe("Copy the password", () => {
  const eyeOn = (page: Page) => page.doc.querySelector("." + CLS.eye + "[data-for=ap]")!.classList.contains(CLS.on);
  const masked = (page: Page) => page.el(ID.confPass).classList.contains(CLS.secret);

  test("clipboard API in a secure context", async () => {
    const page = open("filled");
    const writeText = mock((_text: string) => Promise.resolve());
    setGlobal("navigator", { clipboard: { writeText: writeText } });
    setGlobal("window", { isSecureContext: true });
    await page.click(ID.copyPass);
    expect(writeText.mock.calls).toEqual([["0123456789abcdef01234567"]]);
    expect(lines(page.el(ID.copyResult))).toEqual([["ok", page.init.i18n.passCopied]]);
    expect([masked(page), eyeOn(page)]).toEqual([true, false]);
  });

  test("the copy result stays while the config is the same (a redraw by a key typed), cleared by another config", async () => {
    const page = open("ownconf");
    setGlobal("navigator", { clipboard: { writeText: () => Promise.resolve() } });
    setGlobal("window", { isSecureContext: true });
    await page.click(ID.copyPass);
    const copied: [string, string][] = [["ok", page.init.i18n.passCopied]];
    expect(lines(page.el(ID.copyResult))).toEqual(copied);
    page.type(ID.rdKey, "RDKEY");
    expect(lines(page.el(ID.copyResult))).toEqual(copied);
    server(page, ATB);
    expect(lines(page.el(ID.copyResult))).toEqual([]);
  });

  test("not a secure context: execCommand copy of a hidden textarea with the password", async () => {
    const page = open("filled");
    const copied: string[] = [];
    // The API is there, but not usable outside a secure context (window.isSecureContext false).
    const writeText = mock((_text: string) => Promise.resolve());
    setGlobal("navigator", { clipboard: { writeText: writeText } });
    (page.doc as unknown as { execCommand: (cmd: string) => boolean }).execCommand = (cmd: string) => {
      const area = page.doc.querySelector("textarea")!;
      copied.push(cmd + ":" + area.value.slice(area.selectionStart, area.selectionEnd));
      return true;
    };
    await page.click(ID.copyPass);
    expect(writeText).not.toHaveBeenCalled();
    expect(copied).toEqual(["copy:0123456789abcdef01234567"]);
    expect(page.doc.querySelector("textarea")).toBeNull();
    expect(lines(page.el(ID.copyResult))).toEqual([["ok", page.init.i18n.passCopied]]);
  });

  test("the API refused: execCommand; it fails too: warn and the password shown", async () => {
    const page = open("filled");
    setGlobal("navigator", { clipboard: { writeText: () => Promise.reject(new Error("denied")) } });
    setGlobal("window", { isSecureContext: true });
    let tried = 0;
    (page.doc as unknown as { execCommand: () => boolean }).execCommand = () => {
      tried++;
      return false;
    };
    await page.click(ID.copyPass);
    expect(tried).toBe(1);
    expect(lines(page.el(ID.copyResult))).toEqual([["warn", page.init.i18n.passNotCopied]]);
    expect([masked(page), eyeOn(page)]).toEqual([false, true]);
  });

  test("no execCommand at all (it throws): warn", async () => {
    const page = open("filled");
    (page.doc as unknown as { execCommand: () => boolean }).execCommand = () => {
      throw new Error("no");
    };
    await page.click(ID.copyPass);
    await flush();
    expect(lines(page.el(ID.copyResult))).toEqual([["warn", page.init.i18n.passNotCopied]]);
  });
});
