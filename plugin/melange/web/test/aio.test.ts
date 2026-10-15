// §2.3 AIOStreams config: the answer of "Where to get films" (watch), the server (own one by its address), its config, the template, the text of
// the save button, "Copy" of the password.

import { afterEach, describe, expect, mock, test } from "bun:test";
import { CLS, ID } from "../src/dom";
import { cleanup, flush, json, later, lines, open, type Page, setGlobal, shown } from "./harness";

afterEach(cleanup);

const OWN_CFG = "http://192.168.1.10:3000/stremio/00000000-0000-4000-8000-000000000001/dGVzdA/configure";
const ATB = "https://aio.atbphosting.com";
const ATB_CFG = ATB + "/stremio/00000000-0000-4000-8000-000000000002/dGVzdA/configure";
const MADE = "https://aiostreams.12312023.xyz";
// The third server of p2p.html and ownconf.html: a config changed in AIOStreams (of Debrid, of P2P).
const WIZ = "https://aiostreams-stable.forthewizards.uk";

const server = (page: Page, value: string) => page.type(ID.aioServer, value);
const conf = (page: Page) => ({
  none: shown(page.el(ID.confNone)),
  have: shown(page.el(ID.confHave)),
  link: page.el<HTMLAnchorElement>(ID.confLink).getAttribute("href"),
  login: page.el<HTMLInputElement>(ID.confLogin).value,
  pass: page.el<HTMLInputElement>(ID.confPass).value,
});
const template = (page: Page) => {
  const select = page.el<HTMLSelectElement>(ID.template);
  const visible = Array.from(select.options).filter((option) => !option.hidden).map((option) => option.value);
  return { value: select.value, seen: page.el<HTMLInputElement>(ID.templateShown).value, visible: visible, hint: page.el(ID.templateHint).textContent || "" };
};
const hint = (page: Page, value: string) => page.doc.querySelector('#at option[value="' + value + '"]')!.getAttribute("data-hint") || "";
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

  test("Torrents: the TorrServer field on #src (#tsmoved on #torr points there), else back in its place on #torr", () => {
    const page = open("filled");
    const box = page.el(ID.tsBox);
    const where = () => ({ screen: box.closest("section")!.id, moved: shown(page.el(ID.tsMoved)) });
    const after = box.previousElementSibling;
    // The way without the script: gone, the field is on #src itself.
    expect(page.el(ID.tsLink).hidden).toBe(true);
    expect(where()).toEqual({ screen: "torr", moved: false });
    page.choose("watch", "p2p");
    expect(where()).toEqual({ screen: "src", moved: true });
    expect(box.parentElement!.id).toBe(ID.tsP2p);
    page.choose("watch", "own");
    expect(where()).toEqual({ screen: "torr", moved: false });
    expect(box.previousElementSibling).toBe(after);
    // A page of Torrents: there from the start.
    cleanup();
    const p2p = open("p2p");
    expect([p2p.el(ID.tsBox).parentElement!.id, shown(p2p.el(ID.tsMoved))]).toEqual([ID.tsP2p, true]);
  });

  test("the name of the server in the summary follows the choice and the address of own one", () => {
    const page = open("filled");
    const name = () => page.el(ID.aioServerName).textContent;
    expect(name()).toBe("aiostreams.12312023.xyz");
    server(page, ATB);
    expect(name()).toBe("aio.atbphosting.com");
    server(page, "own");
    page.type(ID.aioOwnUrl, "");
    expect(name()).toBe(page.el<HTMLSelectElement>(ID.aioServer).selectedOptions[0]!.text.replace(/…$/, ""));
    page.type(ID.aioOwnUrl, "http://192.168.1.10:3000");
    expect(name()).toBe("192.168.1.10");
  });
});

describe("server", () => {
  test("own: its address box, no TMDB note, no LAN note; TMDB box by data-tmdb; 403 note by data-jr403 and the JacRed chosen", () => {
    const page = open("filled");
    const vis = () => ({
      ownBox: shown(page.el(ID.aioOwnBox)), tmdbBox: shown(page.el(ID.tmdbBox)), tmdbNote: shown(page.el(ID.tmdbNote)),
      lan: shown(page.el(ID.jacredLan)), jr403: shown(page.el(ID.jacred403)),
    });
    // TMDB of its own.
    expect(vis()).toEqual({ ownBox: false, tmdbBox: false, tmdbNote: true, lan: true, jr403: false });
    // The 403 note only while the JacRed that refuses the server is chosen (here: own one).
    server(page, "https://aiostreams.fortheweak.cloud");
    expect(vis()).toEqual({ ownBox: false, tmdbBox: true, tmdbNote: true, lan: true, jr403: false });
    page.choose("jacred", "jacred.stream");
    expect(vis()).toEqual({ ownBox: false, tmdbBox: true, tmdbNote: true, lan: true, jr403: true });
    page.choose("jacred", "jacred.kompotik.com");
    expect(vis().jr403).toBe(false);
    page.choose("jacred", "jacred.stream");
    server(page, "own");
    expect(vis()).toEqual({ ownBox: true, tmdbBox: true, tmdbNote: false, lan: false, jr403: false });
    // The 403 note not for own config.
    server(page, "https://aiostreams.fortheweak.cloud");
    page.choose("watch", "own");
    expect(vis().jr403).toBe(false);
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
    const all = ["addons", "jacred"];
    expect(template(page)).toEqual({ value: "none", seen: "none", visible: [...all, "none"], hint: hint(page, "none") });
    server(page, ATB);
    expect(template(page)).toEqual({ value: "custom", seen: "custom", visible: [...all, "custom"], hint: hint(page, "custom") });
    server(page, "https://aiostreams.stremio.ru");
    expect(template(page)).toEqual({ value: "addons", seen: "addons", visible: all, hint: hint(page, "addons") });
    server(page, "own");
    expect(template(page).value).toBe("none");
    // The same server and config again: the choice kept.
    page.type(ID.template, "jacred");
    page.type(ID.rdKey, "x");
    expect(template(page)).toEqual({ value: "jacred", seen: "none", visible: [...all, "none"], hint: hint(page, "jacred") });
  });

  test("at the start the choice of the page stays (a form restored by the browser)", () => {
    const page = open("filled", { before: (doc) => ((doc.getElementById(ID.template) as HTMLSelectElement).value = "jacred") });
    expect([template(page).value, template(page).seen]).toEqual(["jacred", "addons"]);
  });

  test("change of the template: only its hint", () => {
    const page = open("filled");
    page.type(ID.template, "jacred");
    expect(template(page)).toEqual({ value: "jacred", seen: "addons", visible: ["addons", "jacred"], hint: hint(page, "jacred") });
  });
});

describe("mode", () => {
  const mode = (page: Page) => page.form.querySelector<HTMLInputElement>("input[name=watch]:checked")!.value;
  // The parts of Debrid and Torrents are shown by the CSS (by the radio): the TorrServer field of Torrents here.
  const parts = (page: Page) => ({ mode: mode(page), debrid: mode(page) === "debrid", p2p: page.el(ID.tsBox).parentElement!.id === ID.tsP2p });
  const login = (page: Page) => (conf(page).have ? conf(page).login : null);

  test("the answer, one for all servers: another server keeps it", () => {
    const page = open("p2p");
    expect(parts(page)).toEqual({ mode: "p2p", debrid: false, p2p: true });
    server(page, ATB);
    expect(parts(page)).toEqual({ mode: "p2p", debrid: false, p2p: true });
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

  test("chosen: its part shown; the choice kept while the config is the same", () => {
    const page = open("filled");
    expect(parts(page)).toEqual({ mode: "debrid", debrid: true, p2p: false });
    page.choose("watch", "p2p");
    expect(parts(page)).toEqual({ mode: "p2p", debrid: false, p2p: true });
    page.type(ID.rdKey, "x");
    expect(mode(page)).toBe("p2p");
    page.choose("watch", "debrid");
    expect(parts(page)).toEqual({ mode: "debrid", debrid: true, p2p: false });
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
    const kompotik = page.doc.querySelector<HTMLOptionElement>('#as option[value="https://aiostreams.kompotik.com"]')!;
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
