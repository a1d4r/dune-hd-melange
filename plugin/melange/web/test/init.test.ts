// §2.1 The start: blank server blocks hidden, "Add" and "Duplicate", the JacRed note, the eyes; no #init or no fetch.

import { afterEach, describe, expect, test } from "bun:test";
import { CLS, ID } from "../src/dom";
import { cleanup, flush, open, setGlobal, shown } from "./harness";

afterEach(cleanup);

const blocks = (doc: Document) => Array.from(doc.querySelectorAll<HTMLDetailsElement>("details." + CLS.serverBlock));
const dupButtons = (doc: Document) => Array.from(doc.querySelectorAll<HTMLButtonElement>("button." + CLS.serverDup));

describe("server blocks at the start", () => {
  test.each([
    ["filled", [true, false, false, false, false]],
    ["empty", [false, false, false, false, false]],
  ] as const)("%s: blank blocks hidden, a filled one shown; Add shown while one is hidden", (name, want) => {
    const { doc, el } = open(name);
    expect(blocks(doc).map(shown)).toEqual([...want]);
    expect(el(ID.addServer).hidden).toBe(false);
  });

  test("all five filled: none hidden, no Add, every Duplicate disabled with the title Все 5 блоков заняты", () => {
    const { doc, el } = open("ownconf");
    expect(blocks(doc).map(shown)).toEqual([true, true, true, true, true]);
    expect(el(ID.addServer).hidden).toBe(true);
    expect(dupButtons(doc).map((button) => button.disabled)).toEqual([true, true, true, true, true]);
    expect(dupButtons(doc).map((button) => button.getAttribute("title"))).toEqual(Array(5).fill("Все 5 блоков заняты"));
  });

  test("a Duplicate in every block, before its result; enabled while a block is hidden", () => {
    const { doc, init } = open("filled");
    for (const block of blocks(doc)) {
      const dup = block.querySelectorAll<HTMLButtonElement>("button." + CLS.serverDup);
      expect(dup.length).toBe(1);
      expect([dup[0]!.type, dup[0]!.className, dup[0]!.textContent]).toEqual(["button", "chk srvdup", init.i18n.duplicate]);
      expect(dup[0]!.nextElementSibling!.classList.contains(CLS.serverResult)).toBe(true);
      expect([dup[0]!.disabled, dup[0]!.getAttribute("title")]).toEqual([false, null]);
    }
  });
});

describe("JacRed note", () => {
  test("hidden under the default JacRed, shown under another one; follows the choice", () => {
    const empty = open("empty");
    expect(empty.el(ID.jacredNote).hidden).toBe(true);
    empty.choose("jacred", "jac.red");
    expect(empty.el(ID.jacredNote).hidden).toBe(false);
    empty.choose("jacred", "jacred.stream");
    expect(empty.el(ID.jacredNote).hidden).toBe(true);
  });

  test.each(["filled", "ownconf"])("%s: another JacRed chosen: shown at the start", (name) => {
    expect(open(name).el(ID.jacredNote).hidden).toBe(false);
  });
});

describe("eyes", () => {
  test("an eye toggles sec of its field and on of itself", () => {
    const { doc, el } = open("filled");
    const eye = doc.querySelector<HTMLButtonElement>("." + CLS.eye + "[data-for=rd]")!;
    eye.click();
    expect([el(ID.rdKey).classList.contains(CLS.secret), eye.classList.contains(CLS.on)]).toEqual([false, true]);
    eye.click();
    expect([el(ID.rdKey).classList.contains(CLS.secret), eye.classList.contains(CLS.on)]).toEqual([true, false]);
    // Only its own field.
    expect(el(ID.tbKey).classList.contains(CLS.secret)).toBe(true);
  });
});

describe("nothing without #init or fetch", () => {
  const untouched = (before: (doc: Document) => void) => {
    const page = open("filled", { before: before });
    const { doc, form, el } = page;
    expect(dupButtons(doc).length).toBe(0);
    expect(blocks(doc).map(shown)).toEqual([true, true, true, true, true]);
    const eye = doc.querySelector<HTMLButtonElement>("." + CLS.eye)!;
    eye.click();
    expect(eye.classList.contains(CLS.on)).toBe(false);
    // The form posts as without JS.
    const submit = new page.win.Event("submit", { cancelable: true }) as unknown as Event;
    expect(form.dispatchEvent(submit)).toBe(true);
    expect(el(ID.saveResult).textContent).toBe("");
    expect(page.calls).toEqual([]);
  };
  test("no #init", () => untouched((doc) => doc.getElementById(ID.init)!.remove()));
  test("#init not JSON", () => untouched((doc) => (doc.getElementById(ID.init)!.textContent = "{")));
  test("#init without i18n", () => untouched((doc) => (doc.getElementById(ID.init)!.textContent = '{"jacredDefault":"jacred.stream"}')));

  test("no fetch: the page is set up, but no check and no save by the script", async () => {
    const page = open("filled", { before: () => setGlobal("fetch", undefined) });
    const { doc, form, el } = page;
    expect(dupButtons(doc).length).toBe(5);
    expect(el(ID.jacredNote).hidden).toBe(false);
    const eye = doc.querySelector<HTMLButtonElement>("." + CLS.eye)!;
    eye.click();
    expect(eye.classList.contains(CLS.on)).toBe(true);
    el(ID.rdCheck).click();
    await flush();
    expect(el(ID.rdResult).textContent).toBe("");
    const submit = new page.win.Event("submit", { cancelable: true }) as unknown as Event;
    expect(form.dispatchEvent(submit)).toBe(true);
  });
});
