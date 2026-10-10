// The contract PHP <-> script: what dom.ts and i18n.ts name is on every page PHP makes (test/fixtures).

import { afterEach, describe, expect, test } from "bun:test";
import { CLS, DATA, ID, JACRED_RADIOS, NAME, SERVER_BLOCK, SERVER_CHECK_FIELD, SERVER_FIELD, VALUE } from "../src/dom";
import { TEXT_KEYS, type Texts } from "../src/i18n";
import { cleanup, FIXTURES, open } from "./harness";

afterEach(cleanup);

// The texts with placeholders, as the script replaces them in order; the others have none.
const PLACEHOLDERS: Partial<Record<keyof Texts, string[]>> = { httpError: ["%d"], timeout: ["%d"], resetConfirm: ["%s", "%s"] };

// Made by the script, not by PHP: "Duplicate", the "on" state of an eye, the mark of an error, "Choose" of a JacRed.
const SCRIPT_CLASSES: string[] = [CLS.serverDup, CLS.on, CLS.bad, CLS.jacredChoose];

describe.each([...FIXTURES])("%s", (name) => {
  test("#init: readInit, i18n has the keys of Texts and only them, none empty", () => {
    const page = open(name, { start: false });
    expect(Object.keys(page.init.i18n).sort()).toEqual([...TEXT_KEYS].sort());
    for (const key of TEXT_KEYS) expect(typeof page.init.i18n[key] === "string" && page.init.i18n[key] !== "").toBe(true);
    expect(page.init.jacredDefault).toBe("jacred.stream");
    expect(typeof page.init.tplNew).toBe("string");
    expect(typeof page.init.confs === "object" && page.init.confs !== null && !Array.isArray(page.init.confs)).toBe(true);
  });

  test("i18n placeholders: those the script replaces (i18n.ts, aio.ts), no others", () => {
    const { init } = open(name, { start: false });
    for (const key of TEXT_KEYS) expect([key, [...(init.i18n[key].match(/%[a-z]/g) || [])]]).toEqual([key, PLACEHOLDERS[key] || []]);
  });

  test("every id of ID, once", () => {
    const { doc } = open(name, { start: false });
    for (const id of Object.values(ID)) expect([id, doc.querySelectorAll("#" + id).length]).toEqual([id, 1]);
  });

  test("the classes of CLS (but those the script makes), names of NAME, data- of DATA", () => {
    const { doc, form } = open(name, { start: false });
    for (const cls of Object.values(CLS))
      expect([cls, doc.querySelectorAll("." + cls).length > 0]).toEqual([cls, !SCRIPT_CLASSES.includes(cls)]);
    expect(form.querySelectorAll("." + CLS.save + "[type=submit]").length).toBe(1);
    expect(form.querySelectorAll("input[type=radio][name=" + NAME.source + "]").length).toBe(2);
    expect(form.querySelectorAll(JACRED_RADIOS + "[type=radio]").length).toBe(form.querySelectorAll("input[name=" + NAME.jacred + "]").length);
    // Every built-in JacRed is a radio of the choice with its id.
    const builtins = form.querySelectorAll<HTMLInputElement>("input." + CLS.jacredBuiltin);
    expect(builtins.length).toBeGreaterThan(1);
    builtins.forEach((radio) => expect([radio.name, radio.getAttribute(DATA.jacredId)]).toEqual([NAME.jacred, radio.value]));
    doc.querySelectorAll<HTMLOptionElement>("#" + ID.template + " option").forEach((option) =>
      expect(option.hasAttribute(DATA.hint)).toBe(true),
    );
    for (const id of [ID.jacredHeading, ID.jacredText])
      expect([doc.getElementById(id)!.hasAttribute(DATA.made), doc.getElementById(id)!.hasAttribute(DATA.own)]).toEqual([true, true]);
    doc.querySelectorAll<HTMLOptionElement>("#" + ID.aioServer + " option").forEach((option) =>
      expect(option.getAttribute(DATA.tmdb)).toMatch(/^[01]$/),
    );
    expect(doc.querySelectorAll("#" + ID.aioServer + " option[" + DATA.jr403 + "='1']").length).toBeGreaterThan(0);

    // Every eye: a button for a field there, masked.
    const eyes = doc.querySelectorAll("." + CLS.eye);
    expect(eyes.length).toBeGreaterThan(0);
    eyes.forEach((eye) => {
      const field = doc.getElementById(eye.getAttribute(DATA.eyeFor) || "");
      expect([eye.tagName, field ? field.classList.contains(CLS.secret) : null]).toEqual(["BUTTON", true]);
    });
    expect(doc.querySelectorAll("." + CLS.eye + "[" + DATA.eyeFor + "=" + ID.confPass + "]").length).toBe(1);
  });

  test("the values of VALUE: own of the source, the server and JacRed; none a mark (disabled) of the template", () => {
    const { doc, form } = open(name, { start: false });
    expect([
      form.querySelectorAll("input[type=radio][name=" + NAME.source + "][value=" + VALUE.own + "]").length,
      doc.querySelectorAll("#" + ID.aioServer + " option[value=" + VALUE.own + "]").length,
      form.querySelectorAll(JACRED_RADIOS + "[type=radio][value=" + VALUE.own + "]").length,
      doc.querySelectorAll("#" + ID.template + " option[value=" + VALUE.none + "][disabled]").length,
    ]).toEqual([1, 1, 1, 1]);
  });

  test("every box the script writes a result or a message into: aria-live polite (a screen reader says it)", () => {
    const { doc } = open(name, { start: false });
    const ids = [ID.tsResult, ID.manifestResult, ID.aioOwnResult, ID.rdResult, ID.tbResult, ID.tmdbResult, ID.copyResult,
      ID.jacredResult, ID.jacredTable, ID.saveResult, ID.message];
    const boxes = [...ids.map((id) => doc.getElementById(id)!), ...Array.from(doc.querySelectorAll("." + CLS.serverResult))];
    expect(boxes.length).toBe(ids.length + 5);
    for (const box of boxes) expect([box.id || box.className, box.getAttribute("aria-live")]).toEqual([box.id || box.className, "polite"]);
  });

  test("server blocks: each of s<n>_<key> for its n, a check and a result", () => {
    const { form } = open(name, { start: false });
    const blocks = form.querySelectorAll(SERVER_BLOCK);
    expect(blocks.length).toBe(5);
    blocks.forEach((block, i) => {
      const keys: string[] = [];
      block.querySelectorAll<HTMLInputElement>("input,select").forEach((field) => {
        const match = SERVER_FIELD.exec(field.name);
        if (match) {
          expect(match[1]).toBe(String(i + 1));
          keys.push(match[2]!);
        }
      });
      expect(keys.sort()).toEqual(["cache", "category", "name", "pass", "tags", "url", "user"]);
      const checked = keys.filter((key) => SERVER_CHECK_FIELD.test("s" + (i + 1) + "_" + key));
      expect(checked.sort()).toEqual(["category", "pass", "tags", "url", "user"]);
      expect(block.querySelectorAll("button." + CLS.serverCheck).length).toBe(1);
      expect(block.querySelectorAll("." + CLS.serverResult).length).toBe(1);
      expect(block.querySelector("input[type=text]")!.getAttribute("name")).toBe("s" + (i + 1) + "_name");
    });
  });

  test("the contents on top: 4 links to the headings of the sections, Advanced to one inside #adv", () => {
    const { doc } = open(name, { start: false });
    const links = Array.from(doc.querySelectorAll<HTMLAnchorElement>("nav.toc a"));
    const targets = links.map((link) => doc.getElementById((link.getAttribute("href") || "").replace(/^#/, "")));
    expect(targets.map((target) => (target ? target.tagName : null))).toEqual(["H2", "H2", "H2", "H2"]);
    expect(targets[1]!.id).toBe(ID.jacredHeading);
    expect(links[3]!.id).toBe(ID.tocAdvanced);
    expect(targets[3]!.closest("details")!.id).toBe(ID.advanced);
    // Before the form: the first thing under the title.
    expect(doc.querySelector("h1")!.nextElementSibling!.matches("nav.toc")).toBe(true);
  });

  test("the form: action settings?t=<token>, the reset is a submit of aio_reset", () => {
    const { form, doc } = open(name, { start: false });
    expect(form.getAttribute("action")).toMatch(/^settings\?t=[0-9a-f]{32}$/);
    expect(doc.forms.length).toBe(1);
    const reset = doc.getElementById(ID.resetConf)!;
    expect([reset.getAttribute("type"), reset.getAttribute("name")]).toEqual(["submit", "aio_reset"]);
  });
});
