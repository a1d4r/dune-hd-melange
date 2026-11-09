// The contract PHP <-> script: what dom.ts and i18n.ts name is on every page PHP makes (test/fixtures).

import { afterEach, describe, expect, test } from "bun:test";
import { readFileSync } from "fs";
import { AIO_SERVER_RADIOS, CLS, DATA, ID, JACRED_RADIOS, NAME, RADIO_GROUP, ROWS, SERVER_BLOCK, SERVER_CHECK_FIELD, SERVER_FIELD, VALUE } from "../src/dom";
import { TEXT_KEYS, type Texts } from "../src/i18n";
import { cleanup, FIXTURES, open } from "./harness";

afterEach(cleanup);

// The selectors of the rule of settings.css with this one, to ask the page itself what the CSS does.
const CSS = readFileSync(import.meta.dir + "/../../cgi/settings.css", "utf8").split("\n");
const cssRule = (selector: string): string[] => CSS.find((line) => line.includes(selector))!.replace(/\{.*/, "").split(",");
const HIDES = cssRule("#nt2:checked~* .xa");
const NO_TOP_LINE = cssRule(".row:first-child");

// The texts with placeholders, as the script replaces them in order; the others have none.
const PLACEHOLDERS: Partial<Record<keyof Texts, string[]>> = {
  httpError: ["%d"],
  timeout: ["%d"],
  resetConfirm: ["%s", "%s"],
  changeOne: ["%d"],
  changeFew: ["%d"],
  changeMany: ["%d"],
};

// Made by the script, not by PHP: "Duplicate", the "on" state of an eye, the mark of an error.
const SCRIPT_CLASSES: string[] = [CLS.serverDup, CLS.on, CLS.bad];
// Made by PHP only on a page with errors of a save (#msg): the own fields with one shown.
const ERROR_CLASSES: string[] = [CLS.ownOpen];

describe.each([...FIXTURES])("%s", (name) => {
  test("#init: readInit, i18n has the keys of Texts and only them, none empty", () => {
    const page = open(name, { start: false });
    expect(Object.keys(page.init.i18n).sort()).toEqual([...TEXT_KEYS].sort());
    for (const key of TEXT_KEYS) expect(typeof page.init.i18n[key] === "string" && page.init.i18n[key] !== "").toBe(true);
    expect(page.init.jacredDefault).toBe("jacred.kompotik.com");
    // The template for a new config of each mode.
    const tplNew = page.init.tplNew as Record<string, unknown>;
    expect([Object.keys(tplNew), typeof tplNew[VALUE.debrid], typeof tplNew[VALUE.p2p]]).toEqual([[VALUE.debrid, VALUE.p2p], "string", "string"]);
    expect(typeof page.init.confs === "object" && page.init.confs !== null && !Array.isArray(page.init.confs)).toBe(true);
  });

  test("i18n placeholders: those the script replaces (i18n.ts, aio.ts), no others", () => {
    const { init } = open(name, { start: false });
    for (const key of TEXT_KEYS) expect([key, [...(init.i18n[key].match(/%[a-z]/g) || [])]]).toEqual([key, PLACEHOLDERS[key] || []]);
  });

  test("every id of ID, once (#first: on the first run only, #state hidden there); the line of each screen of ROWS", () => {
    const { doc } = open(name, { start: false });
    const first = doc.querySelectorAll("#" + ID.first).length === 1;
    for (const id of Object.values(ID))
      expect([id, doc.querySelectorAll("#" + id).length]).toEqual([id, id === ID.first && !first ? 0 : 1]);
    expect(doc.getElementById(ID.state)!.hidden).toBe(first);
    for (const row of ROWS) {
      expect([row, doc.querySelectorAll("section#" + row + "." + CLS.screen).length]).toEqual([row, 1]);
      expect(doc.querySelectorAll("#home a#r" + row + "[href='#" + row + "'] #d" + row + ".dot").length).toBe(1);
      expect(doc.querySelectorAll("#r" + row + " #v" + row).length).toBe(1);
    }
    // The state: a line of each state, one shown.
    const state = doc.getElementById(ID.state)!;
    const lines = Array.from(state.querySelectorAll<HTMLElement>("p[" + DATA.state + "]"));
    expect(lines.map((line) => line.getAttribute(DATA.state))).toEqual(["key", "conf", "man", "ok"]);
    expect(lines.filter((line) => !line.hidden).map((line) => line.getAttribute(DATA.state))).toEqual([state.getAttribute(DATA.state)]);
  });

  test("the classes of CLS (but those the script makes), names of NAME, data- of DATA", () => {
    const { doc, form } = open(name, { start: false });
    const errors = doc.querySelectorAll("#" + ID.message + " .err").length > 0;
    for (const cls of Object.values(CLS))
      expect([cls, doc.querySelectorAll("." + cls).length > 0]).toEqual([cls, ERROR_CLASSES.includes(cls) ? errors : !SCRIPT_CLASSES.includes(cls)]);
    expect(doc.querySelectorAll("." + CLS.ownOpen + ":not(." + CLS.ownFields + ")").length).toBe(0);
    expect(form.querySelectorAll("." + CLS.save + "[type=submit]").length).toBe(1);
    // The answers: the values the script compares with, each with its card in a group of #src.
    const watches = Array.from(form.querySelectorAll<HTMLInputElement>("input[type=radio][name=" + NAME.watch + "]"));
    expect(watches.map((radio) => radio.value)).toEqual([VALUE.debrid, VALUE.p2p, VALUE.own]);
    for (const radio of watches)
      expect(doc.querySelector("label[for=" + radio.id + "]")!.closest(RADIO_GROUP)!.closest("section")!.id).toBe("src");
    // Every JacRed radio: its card in a group.
    form.querySelectorAll<HTMLInputElement>(JACRED_RADIOS).forEach((radio) =>
      expect(doc.querySelector("label[for=" + radio.id + "]")!.closest(RADIO_GROUP)).not.toBeNull(),
    );
    // The switch of Debrid: a button of each key.
    expect(Array.from(doc.querySelectorAll("#" + ID.debridSwitch + " button")).map((button) => button.getAttribute(DATA.key))).toEqual([
      "rd",
      "tb",
    ]);
    // The TorrServer field, once, on #torr (0.44.0: in every answer).
    expect(form.querySelectorAll("input[name=ts]").length).toBe(1);
    expect(doc.getElementById(ID.ts)!.closest("section")!.id).toBe("torr");
    // Play or Add & Play (ts_save, 0.45.0): two radios "play", "add" with their cards in a group of #torr.
    const saves = Array.from(form.querySelectorAll<HTMLInputElement>("input[name=ts_save]"));
    expect(saves.map((radio) => [radio.type, radio.value])).toEqual([["radio", "play"], ["radio", "add"]]);
    for (const radio of saves)
      expect(doc.querySelector("label[for=" + radio.id + "]")!.closest(RADIO_GROUP)!.closest("section")!.id).toBe("torr");
    // No torrents (0.49.0): two radios right in the form, before the screens (the CSS hides by them), their cards on
    // #adv, all hidden with "Торренты".
    const offs = Array.from(form.querySelectorAll<HTMLInputElement>("input[name=no_torrents]"));
    expect(offs.map((radio) => [radio.type, radio.value, radio.parentElement === form])).toEqual([["radio", "allow", true], ["radio", "off", true]]);
    for (const radio of offs) {
      const card = doc.querySelector("label[for=" + radio.id + "]")!;
      expect([card.closest("section")!.id, !!card.closest(".xt")]).toEqual(["adv", true]);
    }
    // Skip intro and credits (markers, 0.49.1): two radios "on", "off" with their cards on #adv.
    const marks = Array.from(form.querySelectorAll<HTMLInputElement>("input[name=markers]"));
    expect(marks.map((radio) => [radio.type, radio.value])).toEqual([["radio", "on"], ["radio", "off"]]);
    for (const radio of marks)
      expect(doc.querySelector("label[for=" + radio.id + "]")!.closest(RADIO_GROUP)!.closest("section")!.id).toBe("adv");
    // Film logo in place of the title (cat_logo, 0.55.0): two radios "on", "off" with their cards on #look.
    const logos = Array.from(form.querySelectorAll<HTMLInputElement>("input[name=" + NAME.catLogo + "]"));
    expect(logos.map((radio) => [radio.type, radio.value, radio.checked])).toEqual([["radio", "on", true], ["radio", "off", false]]);
    for (const radio of logos)
      expect(doc.querySelector("label[for=" + radio.id + "]")!.closest(RADIO_GROUP)!.closest("section")!.id).toBe("look");
    // IMDb rating on posters (cat_rating): two radios "on", "off" with their cards on #look.
    const ratings = Array.from(form.querySelectorAll<HTMLInputElement>("input[name=cat_rating]"));
    expect(ratings.map((radio) => [radio.type, radio.value, radio.checked])).toEqual([["radio", "on", true], ["radio", "off", false]]);
    for (const radio of ratings)
      expect(doc.querySelector("label[for=" + radio.id + "]")!.closest(RADIO_GROUP)!.closest("section")!.id).toBe("look");
    // The rows of the catalog: one field on #rows, not of type hidden (the bar counts only others), nothing to read by eye.
    const rowsField = form.querySelectorAll<HTMLInputElement>("input[name=cat_rows]");
    expect(Array.from(rowsField).map((field) => [field.id, field.type, field.hidden, field.closest("section")!.id])).toEqual([[ID.rowsValue, "text", true, "rows"]]);
    // The bar of the save under the screens: its results, "Hide" of them (a button, not a submit), its button the save.
    expect(doc.querySelector("#" + ID.bar + " #" + ID.saveResult + "+ button#" + ID.hideResult + "[type=button][hidden] + * #" + ID.dirty + "+ button." + CLS.save)).not.toBeNull();
    expect(form.querySelectorAll(JACRED_RADIOS + "[type=radio]").length).toBe(form.querySelectorAll("input[name=" + NAME.jacred + "]").length);
    // Every built-in JacRed is a radio of the choice with its id.
    const builtins = form.querySelectorAll<HTMLInputElement>("input." + CLS.jacredBuiltin);
    expect(builtins.length).toBeGreaterThan(1);
    builtins.forEach((radio) => expect([radio.name, radio.getAttribute(DATA.jacredId)]).toEqual([NAME.jacred, radio.value]));
    // The templates of each mode: radios with their cards in a group (0.44.0), each with its short name; the lines of
    // the marks too, in the box of its marks before the cards.
    for (const [templateName, marks] of [[NAME.template, ID.templateMarks], [NAME.templateP2p, ID.templateP2pMarks]] as const) {
      const radios = form.querySelectorAll<HTMLInputElement>("input[type=radio][name=" + templateName + "]");
      expect(radios.length).toBe(2);
      radios.forEach((radio) =>
        expect([radio.getAttribute(DATA.short) || "", !!form.querySelector('label[for="' + radio.id + '"]')!.closest(RADIO_GROUP)]).toEqual([
          expect.stringMatching(/./),
          true,
        ]),
      );
      const lines = doc.querySelectorAll("#" + marks + " [" + DATA.mark + "]");
      expect(Array.from(lines, (line) => [line.getAttribute(DATA.mark), !!line.getAttribute(DATA.short)])).toEqual([["custom", true], [VALUE.none, true]]);
    }
    const jrp = doc.getElementById(ID.jacredText)!;
    expect([jrp.hasAttribute(DATA.made), jrp.hasAttribute(DATA.own)]).toEqual([true, true]);
    // A card of "Compare speed" for every JacRed (data-jr: the value of its radio).
    expect(Array.from(doc.querySelectorAll("." + CLS.jacredSpeed)).map((card) => card.getAttribute(DATA.jacredOf))).toEqual(
      Array.from(form.querySelectorAll<HTMLInputElement>(JACRED_RADIOS)).map((radio) => radio.value),
    );
    // The AIOStreams servers (0.44.0): radios with their cards in a group of #cfg, each with data-tmdb; no select.
    const servers = form.querySelectorAll<HTMLInputElement>(AIO_SERVER_RADIOS);
    expect([servers.length > 1, form.querySelectorAll("select[name=" + NAME.aioServer + "]").length]).toEqual([true, 0]);
    servers.forEach((radio) => {
      expect([radio.type, radio.getAttribute(DATA.tmdb)]).toEqual(["radio", expect.stringMatching(/^[01]$/)]);
      expect(doc.querySelector("label[for=" + radio.id + "]")!.closest(RADIO_GROUP)!.closest("section")!.id).toBe("cfg");
    });
    // A server JacRed answers 403: the id of that JacRed, a radio of the choice.
    const refused = form.querySelectorAll<HTMLInputElement>(AIO_SERVER_RADIOS + "[" + DATA.jr403 + "]");
    expect(refused.length).toBeGreaterThan(0);
    refused.forEach((radio) =>
      expect(form.querySelectorAll(JACRED_RADIOS + "[value='" + radio.getAttribute(DATA.jr403) + "']").length).toBe(1),
    );
    // A server asking JacRed itself in P2P (Torznab): "1"; the own one never.
    const torznab = form.querySelectorAll<HTMLInputElement>(AIO_SERVER_RADIOS + "[" + DATA.p2pTorznab + "]");
    expect(Array.from(torznab).map((radio) => [radio.value, radio.getAttribute(DATA.p2pTorznab)])).toEqual([
      ["https://aiostreams.kompotik.com", "1"],
    ]);

    // Every eye: a button for a field there, masked.
    const eyes = doc.querySelectorAll("." + CLS.eye);
    expect(eyes.length).toBeGreaterThan(0);
    eyes.forEach((eye) => {
      const field = doc.getElementById(eye.getAttribute(DATA.eyeFor) || "");
      expect([eye.tagName, field ? field.classList.contains(CLS.secret) : null]).toEqual(["BUTTON", true]);
    });
    expect(doc.querySelectorAll("." + CLS.eye + "[" + DATA.eyeFor + "=" + ID.confPass + "]").length).toBe(1);
  });

  test("the values of VALUE: own of the answer, the server and JacRed; none a mark of the template", () => {
    const { doc, form } = open(name, { start: false });
    expect([
      form.querySelectorAll("input[type=radio][name=" + NAME.watch + "][value=" + VALUE.own + "]").length,
      form.querySelectorAll(AIO_SERVER_RADIOS + "[type=radio][value=" + VALUE.own + "]").length,
      form.querySelectorAll(JACRED_RADIOS + "[type=radio][value=" + VALUE.own + "]").length,
      doc.querySelectorAll("#" + ID.templateMarks + " [" + DATA.mark + "=" + VALUE.none + "]").length,
      doc.querySelectorAll("#" + ID.templateP2pMarks + " [" + DATA.mark + "=" + VALUE.none + "]").length,
    ]).toEqual([1, 1, 1, 1, 1]);
  });

  test("every box the script writes a result or a message into: aria-live polite (a screen reader says it)", () => {
    const { doc } = open(name, { start: false });
    const ids = [ID.tsResult, ID.manifestResult, ID.aioOwnResult, ID.rdResult, ID.tbResult, ID.tmdbResult, ID.copyResult,
      ID.jacredResult, ID.jacredSpeed, ID.saveResult, ID.message];
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
      expect(keys.sort()).toEqual(["cache", "category", "name", "pass", "tags", "type", "url", "user"]);
      const checked = keys.filter((key) => SERVER_CHECK_FIELD.test("s" + (i + 1) + "_" + key));
      expect(checked.sort()).toEqual(["category", "pass", "tags", "type", "url", "user"]);
      expect(block.querySelectorAll("button." + CLS.serverCheck).length).toBe(1);
      expect(block.querySelectorAll("." + CLS.serverResult).length).toBe(1);
      expect(block.querySelector("input[type=text]")!.getAttribute("name")).toBe("s" + (i + 1) + "_name");
    });
  });

  // The CSS, no script (0.49.0): #adv stays in every mode (else no way to turn "Без торрентов" off).
  test.each([
    ["wd", "nt1"],
    ["wd", "nt2"],
    ["wo", "nt1"],
    ["wo", "nt2"],
    ["wp", "nt1"],
    ["wp", "nt2"],
  ])("the lines of the main screen with %s and %s", (watch, answer) => {
    const { doc } = open(name, { start: false });
    doc.querySelector<HTMLInputElement>("#" + watch)!.checked = true;
    doc.querySelector<HTMLInputElement>("#" + answer)!.checked = true;
    const hidden = (el: Element) => HIDES.some((selector) => el.matches(selector));
    const shown = (id: string) => !hidden(doc.getElementById(id)!);
    const off = answer === "nt2" && watch !== "wp";
    expect([shown("radv"), shown("rtorr"), shown("rdl")]).toEqual([true, !off, !off]);
    // One line between neighbours, none on top of a list, whichever lines are left.
    doc.querySelectorAll("#home .list").forEach((list) => {
      const rows = Array.from(list.children).filter((row) => !hidden(row));
      expect(rows.map((row) => NO_TOP_LINE.some((selector) => row.matches(selector)))).toEqual(rows.map((_, i) => i === 0));
    });
    // The value of the line: only with "Без торрентов" in effect.
    const value = doc.querySelector("#vadv span")!;
    expect([doc.querySelectorAll("#vadv span").length, value.textContent, hidden(value)]).toEqual([1, "Торренты выключены", !off]);
    // "Запуск торрентов" on the screen: not with "Торренты".
    expect(hidden(doc.querySelector("#adv p.lbl")!)).toBe(watch === "wp");
    // Skip intro and credits: whatever is chosen.
    expect(hidden(doc.getElementById("mrk1")!.nextElementSibling!.closest(".choice")!)).toBe(false);
  });

  test("the screens: each a section with a link back to #home, #home last, the bar after it", () => {
    const { doc, form } = open(name, { start: false });
    const screens = Array.from(form.querySelectorAll("section." + CLS.screen));
    expect(screens.map((screen) => screen.id)).toEqual([...ROWS, "home"]);
    for (const screen of screens.slice(0, -1)) expect(screen.querySelectorAll(":scope > a.back[href='#home']").length).toBe(1);
    expect(screens[screens.length - 1]!.nextElementSibling!.id).toBe(ID.bar);
    // The message of PHP above the form.
    expect(doc.getElementById(ID.message)!.nextElementSibling).toBe(form);
  });

  test("the form: action settings?t=<token>, the reset is a submit of aio_reset", () => {
    const { form, doc } = open(name, { start: false });
    expect(form.getAttribute("action")).toMatch(/^settings\?t=[0-9a-f]{32}$/);
    expect(doc.forms.length).toBe(1);
    const reset = doc.getElementById(ID.resetConf)!;
    expect([reset.getAttribute("type"), reset.getAttribute("name")]).toEqual(["submit", "aio_reset"]);
  });
});
