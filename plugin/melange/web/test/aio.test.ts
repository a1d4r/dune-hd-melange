// §2.3 AIOStreams config: the source, the server (own one by its address), its config, the template, the text of
// the save button, "Copy" of the password.

import { afterEach, describe, expect, mock, test } from "bun:test";
import { CLS, ID } from "../src/dom";
import { cleanup, flush, json, later, lines, open, type Page, setGlobal, shown } from "./harness";

afterEach(cleanup);

const OWN_CFG = "http://192.168.1.10:3000/stremio/00000000-0000-4000-8000-000000000001/dGVzdA/configure";
const ATB = "https://aio.atbphosting.com";
const ATB_CFG = ATB + "/stremio/00000000-0000-4000-8000-000000000002/dGVzdA/configure";

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

describe("source", () => {
  test("own: its block, the own texts of JacRed, the TorrServer note; no 403 or LAN note; and back", () => {
    const page = open("filled");
    const vis = () => ({
      made: shown(page.el(ID.sourceMade)), own: shown(page.el(ID.sourceOwn)), tsOwn: shown(page.el(ID.tsOwn)),
      jr403: shown(page.el(ID.jacred403)), lan: shown(page.el(ID.jacredLan)),
      heading: page.el(ID.jacredHeading).textContent || "", text: page.el(ID.jacredText).textContent || "",
    });
    const texts = (attr: string) => ({ heading: page.el(ID.jacredHeading).getAttribute(attr) || "", text: page.el(ID.jacredText).getAttribute(attr) || "" });
    expect(vis()).toEqual({ made: true, own: false, tsOwn: false, jr403: false, lan: true, ...texts("data-made") });
    page.choose("source", "own");
    expect(vis()).toEqual({ made: false, own: true, tsOwn: true, jr403: false, lan: false, ...texts("data-own") });
    page.choose("source", "made");
    expect(vis()).toEqual({ made: true, own: false, tsOwn: false, jr403: false, lan: true, ...texts("data-made") });
  });

  test("a page of source own: set so at the start", () => {
    const page = open("own");
    expect([shown(page.el(ID.sourceMade)), shown(page.el(ID.sourceOwn)), shown(page.el(ID.tsOwn))]).toEqual([false, true, true]);
    expect(page.el(ID.jacredHeading).textContent).toBe(page.el(ID.jacredHeading).getAttribute("data-own") || "");
  });
});

describe("server", () => {
  test("own: its address box, no TMDB note, no LAN note; TMDB box by data-tmdb; 403 note by data-jr403", () => {
    const page = open("filled");
    const vis = () => ({
      ownBox: shown(page.el(ID.aioOwnBox)), tmdbBox: shown(page.el(ID.tmdbBox)), tmdbNote: shown(page.el(ID.tmdbNote)),
      lan: shown(page.el(ID.jacredLan)), jr403: shown(page.el(ID.jacred403)),
    });
    // TMDB of its own.
    expect(vis()).toEqual({ ownBox: false, tmdbBox: false, tmdbNote: true, lan: true, jr403: false });
    server(page, "https://aiostreams.fortheweak.cloud");
    expect(vis()).toEqual({ ownBox: false, tmdbBox: true, tmdbNote: true, lan: true, jr403: true });
    server(page, "own");
    expect(vis()).toEqual({ ownBox: true, tmdbBox: true, tmdbNote: false, lan: false, jr403: false });
    // The 403 note only under source made.
    server(page, "https://aiostreams.fortheweak.cloud");
    page.choose("source", "own");
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

describe("save button text", () => {
  test("Save and create: source made, no config, a RD or TorBox key; else Save", () => {
    const page = open("empty");
    const { save, saveAndCreate } = page.init.i18n;
    expect(saveText(page)).toBe(save);
    page.type(ID.rdKey, "   ");
    expect(saveText(page)).toBe(save);
    page.type(ID.tbKey, "TBKEY");
    expect(saveText(page)).toBe(saveAndCreate);
    page.choose("source", "own");
    expect(saveText(page)).toBe(save);
    page.choose("source", "made");
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
