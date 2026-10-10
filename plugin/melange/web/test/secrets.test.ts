// The secret fields (class sec): masked by -webkit-text-security; where the browser has none (Firefox), the script
// makes them type=password, away from password managers, the eye switching the type.

import { afterEach, describe, expect, test } from "bun:test";
import { CLS, DATA, ID } from "../src/dom";
import { cleanup, open, type Page, setGlobal, shown } from "./harness";

afterEach(cleanup);

const blocks = (page: Page) => Array.from(page.doc.querySelectorAll<HTMLDetailsElement>("details." + CLS.serverBlock));
const secrets = (page: Page) => Array.from(page.doc.querySelectorAll<HTMLInputElement>("input." + CLS.secret));
const eye = (page: Page, id: string) => page.doc.querySelector<HTMLButtonElement>("." + CLS.eye + "[" + DATA.eyeFor + "=" + id + "]")!;
// CSS.supports() as the browser answers it; the property asked is kept.
function css(supported: boolean, asked: string[] = []): void {
  setGlobal("CSS", { supports: (property: string, value: string) => (asked.push(property + ": " + value), supported) });
}

describe("no -webkit-text-security (Firefox)", () => {
  test("every secret field type=password, kept away from password managers; the values stay", () => {
    const asked: string[] = [];
    const page = open("filled", { before: () => css(false, asked) });
    expect(asked[0]).toBe("-webkit-text-security: disc");
    const fields = secrets(page);
    expect(fields.length).toBe(11);
    for (const field of fields)
      expect([field.id, field.type, field.getAttribute("autocomplete"), field.getAttribute("data-lpignore"), field.getAttribute("data-1p-ignore"),
        field.getAttribute("data-bwignore"), field.getAttribute("data-form-type")]).toEqual([field.id, "password", "new-password", "true", "true", "true", "other"]);
    expect([page.el<HTMLInputElement>(ID.rdKey).value, page.field("s1_pass").value]).toEqual(["RDTESTKEY00000000000000001", "test-pass"]);
  });

  test("the eye: type=text and on, again: type=password and off", () => {
    const page = open("filled", { before: () => css(false) });
    const key = page.el<HTMLInputElement>(ID.rdKey);
    eye(page, ID.rdKey).click();
    expect([key.type, key.classList.contains(CLS.secret), eye(page, ID.rdKey).classList.contains(CLS.on)]).toEqual(["text", false, true]);
    eye(page, ID.rdKey).click();
    expect([key.type, key.classList.contains(CLS.secret), eye(page, ID.rdKey).classList.contains(CLS.on)]).toEqual(["password", true, false]);
  });

  test("a server block with the password only is not blank: shown; Duplicate copies the password", () => {
    const page = open("filled", {
      before: (doc) => {
        css(false);
        doc.querySelector<HTMLInputElement>("[name=s2_pass]")!.value = "only-pass";
      },
    });
    expect(blocks(page).map(shown)).toEqual([true, true, false, false, false]);
    blocks(page)[0]!.querySelector<HTMLButtonElement>("." + CLS.serverDup)!.click();
    expect([page.field("s3_pass").value, page.field("s3_pass").type, shown(blocks(page)[2]!)]).toEqual(["test-pass", "password", true]);
    blocks(page)[1]!.querySelector<HTMLButtonElement>("." + CLS.serverDup)!.click();
    expect([page.field("s4_pass").value, page.field("s4_url").value]).toEqual(["only-pass", ""]);
  });

  test("Add server focuses the name of the block", () => {
    const page = open("filled", { before: () => css(false) });
    page.el(ID.addServer).click();
    expect(page.doc.activeElement).toBe(page.field("s2_name"));
  });

  test("Copy failed: the password of the config shown (type=text)", async () => {
    const page = open("ownconf", { before: () => css(false) });
    const pass = page.el<HTMLInputElement>(ID.confPass);
    expect(pass.type).toBe("password");
    page.doc.execCommand = () => false;
    await page.click(ID.copyPass);
    expect([pass.type, eye(page, ID.confPass).classList.contains(CLS.on)]).toEqual(["text", true]);
  });
});

test("with -webkit-text-security: the secret fields stay type=text, no attributes added; the eye only toggles the class", () => {
  const page = open("filled", { before: () => css(true) });
  for (const field of secrets(page)) expect([field.id, field.type, field.getAttribute("autocomplete")]).toEqual([field.id, "text", "off"]);
  eye(page, ID.rdKey).click();
  expect([page.el<HTMLInputElement>(ID.rdKey).type, page.el(ID.rdKey).classList.contains(CLS.secret)]).toEqual(["text", false]);
});
