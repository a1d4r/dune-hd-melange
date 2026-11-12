// The source profiles (#pr, 0.58.0): the block by the script (by the CSS only with "Source profiles" on), the list
// of #init, "Save the current one as a profile" (a=profile_add, disabled with a change not saved, the profiles full
// or the source in use a profile), "Edit" -> a=profile_edit (the link of the one in use: the field of the own config
// too) and "Cancel", "Delete" -> a=profile_del, Enter in their fields, the list anew after a save that stayed; src_was
// after such a save.

import { afterEach, describe, expect, test } from "bun:test";
import { readFileSync } from "fs";
import { CLS, ID, NAME } from "../src/dom";
import { cleanup, flush, json, later, lines, NET, open, type Page, setGlobal, TOKEN } from "./harness";

afterEach(cleanup);

const LINK = "https://aio.example/stremio/00000000-0000-4000-8000-000000000001/dGVzdA/manifest.json";
const LINK2 = "https://aio.example/stremio/00000000-0000-4000-8000-000000000002/dGVzdA/manifest.json";
const MADE = "https://aiostreams.12312023.xyz";
const POST = { method: "POST", contentType: "application/x-www-form-urlencoded" };
const url = (a: string) => TOKEN + "&a=" + a + "&ajax=1";
// The list of prof.html, as PHP sends it.
const LIST = [
  { name: "Debrid", line: "Debrid · " + MADE.slice(8), now: false, ready: true },
  { name: "NAS", line: "Торренты · 192.0.2.10", now: false, ready: false },
  { name: "Свой & <конфиг>", line: "Свой конфиг · aio.example", now: true, ready: true, url: LINK },
];
const answer = (ok: boolean, msg: string, list: unknown[] = LIST, more: Record<string, unknown> = {}) =>
  json({ ok: ok, msg: msg, profiles: { list: list, def: "Свой конфиг", max: 8 }, ...more });
// prof.html with none of its profiles in use (the source saved is none of them).
const NONE_NOW = {
  before: (doc: Document) => {
    const el = doc.getElementById(ID.init)!;
    const init = JSON.parse(el.textContent || "");
    for (const p of init.profiles.list) p.now = false;
    el.textContent = JSON.stringify(init);
  },
};
const enter = (page: Page, input: HTMLElement) => {
  const event = new page.win.KeyboardEvent("keydown", { key: "Enter", bubbles: true, cancelable: true }) as unknown as Event;
  input.dispatchEvent(event);
  return event.defaultPrevented;
};

const cards = (page: Page) => Array.from(page.el(ID.profileList).querySelectorAll<HTMLElement>("." + CLS.profile));
// A card as seen: its title (with "now"), the lines under it.
const seen = (card: HTMLElement) => Array.from(card.querySelectorAll("." + CLS.profileHead + " > span")).map((span) => span.textContent);
const buttons = (card: HTMLElement) => Array.from(card.querySelectorAll<HTMLButtonElement>("button:not(.eye)"));
const add = (page: Page) => page.el<HTMLButtonElement>(ID.profileAdd);
const results = (page: Page) => lines(page.el(ID.profileResult));
async function press(button: HTMLElement): Promise<void> {
  button.click();
  await flush();
}

describe("the block", () => {
  test("without the script: hidden; with it: shown on every page (the CSS hides it with the setting off)", () => {
    expect(open("prof", { start: false }).el(ID.profiles).hidden).toBe(true);
    cleanup();
    expect(open("filled").el(ID.profiles).hidden).toBe(false);
    cleanup();
    // No fetch: nothing of it works, it stays hidden.
    expect(open("prof", { before: () => setGlobal("fetch", undefined) }).el(ID.profiles).hidden).toBe(true);
  });

  test("the CSS: the block hidden by the radio of off, not by that of on", () => {
    const css = readFileSync(import.meta.dir + "/../../cgi/settings.css", "utf8").split("\n");
    const hides = css.find((line) => line.includes("#nt2:checked~* .xa"))!.replace(/\{.*/, "").split(",");
    const page = open("prof", { start: false });
    const block = page.el(ID.profiles);
    expect([page.field<HTMLInputElement>(NAME.srcProfiles).value, page.doc.querySelector<HTMLInputElement>("#spf2")!.checked]).toEqual(["off", true]);
    expect(hides.some((selector) => block.matches(selector))).toBe(false);
    page.choose(NAME.srcProfiles, "off");
    expect(hides.some((selector) => block.matches(selector))).toBe(true);
  });

  test("no profiles: the line of none, no card; the name of a new one as the placeholder", () => {
    const page = open("filled");
    expect([cards(page).length, page.el(ID.profilesNone).hidden, page.el<HTMLInputElement>(ID.profileName).placeholder]).toEqual([0, false, "Debrid"]);
  });
});

describe("the list", () => {
  test("a card each in order: the name as text, now, kind and host, no config; Edit and Delete", () => {
    const page = open("prof");
    expect(cards(page).map(seen)).toEqual([
      ["Debrid", "Debrid · aiostreams.12312023.xyz"],
      ["NAS", "Торренты · 192.0.2.10", page.init.i18n.profileNoConf],
      ["Свой & <конфиг>" + page.init.i18n.profileNow, "Свой конфиг · aio.example"],
    ]);
    expect(page.el(ID.profileList).querySelectorAll("." + CLS.badge).length).toBe(1);
    expect(page.el(ID.profilesNone).hidden).toBe(true);
    expect(page.el<HTMLInputElement>(ID.profileName).placeholder).toBe("Свой конфиг");
    // The edit fields hidden till Edit; Save of the profile in them.
    expect(buttons(cards(page)[0]!).map((button) => [button.textContent, button.closest("[hidden]") !== null])).toEqual([
      [page.init.i18n.profileSave, true], [page.init.i18n.profileEdit, false], [page.init.i18n.profileDelete, false]]);
  });

  test("Edit: the name, and the link of an own config only (masked, its own eye); nothing of them posted by a save", async () => {
    const page = open("prof");
    const [debrid, , own] = cards(page);
    await press(buttons(debrid!)[1]!);
    expect([debrid!.querySelectorAll("input").length, debrid!.querySelector<HTMLInputElement>("input")!.value, buttons(debrid!)[1]!.getAttribute("aria-expanded")])
      .toEqual([1, "Debrid", "true"]);
    await press(buttons(own!)[1]!);
    const link = own!.querySelectorAll<HTMLInputElement>("input")[1]!;
    expect([link.value, link.classList.contains(CLS.secret)]).toEqual([LINK, true]);
    await press(own!.querySelector<HTMLButtonElement>("." + CLS.eye)!);
    expect(link.classList.contains(CLS.secret)).toBe(false);
    expect(page.el(ID.profiles).querySelectorAll("[name]").length).toBe(0);
    // A change of a field of a card is none of the form: the bar stays hidden.
    page.type(link, LINK2);
    expect(page.el(ID.bar).hidden).toBe(true);
  });
});

describe("Save the current one as a profile", () => {
  test("a change not saved: disabled, the line why; back as it came: enabled", () => {
    const page = open("prof", NONE_NOW);
    expect([add(page).disabled, page.el(ID.profileDirty).hidden]).toEqual([false, true]);
    page.type(ID.rdKey, "RDTESTKEY00000000000000009");
    expect([add(page).disabled, page.el(ID.profileDirty).hidden]).toEqual([true, false]);
    page.type(ID.rdKey, "RDTESTKEY00000000000000001");
    expect([add(page).disabled, page.el(ID.profileDirty).hidden]).toEqual([false, true]);
  });

  test("posts the name typed; added: the list anew, the field empty, the line ok", async () => {
    const page = open("prof", NONE_NOW);
    page.type(ID.profileName, "Мой & NAS");
    const more = [...LIST, { name: "Мой & NAS", line: "Свой конфиг · aio.example", now: false, ready: true, url: LINK2 }];
    page.replies.push(answer(true, "Профиль «Мой & NAS» сохранён", more));
    await press(add(page));
    expect(page.calls).toEqual([{ ...POST, url: url("profile_add"), body: "name=" + encodeURIComponent("Мой & NAS") }]);
    expect([cards(page).length, page.el<HTMLInputElement>(ID.profileName).value, results(page)]).toEqual([4, "", [["ok", "Профиль «Мой & NAS» сохранён"]]]);
  });

  test("refused about the name: the line, the field marked, the list kept; no answer: the line why", async () => {
    const page = open("prof", NONE_NOW);
    page.type(ID.profileName, "Debrid");
    page.replies.push(answer(false, "Профиль «Debrid» уже есть — назовите иначе", LIST, { part: "name" }));
    await press(add(page));
    expect([results(page), page.el(ID.profileName).classList.contains(CLS.bad), cards(page).length])
      .toEqual([[["err", "Профиль «Debrid» уже есть — назовите иначе"]], true, 3]);
    page.replies.push(NET);
    await press(add(page));
    expect([results(page), page.el(ID.profileName).classList.contains(CLS.bad)]).toEqual([[["err", page.init.i18n.noConnection]], false]);
  });

  test("the profiles full: disabled, the line of the limit; one deleted: enabled again", async () => {
    const page = open("prof", NONE_NOW);
    const eight = Array.from({ length: 8 }, (_, i) => ({ name: "P" + i, line: "Debrid · h", now: false, ready: true }));
    page.replies.push(answer(true, "", eight));
    page.type(ID.profileName, "P7");
    await press(add(page));
    expect([add(page).disabled, page.el(ID.profilesFull).hidden, page.el(ID.profileDirty).hidden, page.el(ID.profileInUse).hidden])
      .toEqual([true, false, true, true]);
    page.confirmAnswer = true;
    page.replies.push(answer(true, "Профиль «P0» удалён", eight.slice(1)));
    await press(buttons(cards(page)[0]!)[2]!);
    expect([add(page).disabled, page.el(ID.profilesFull).hidden, cards(page).length]).toEqual([false, true, 7]);
  });
});

describe("Save the current one as a profile: in use, Enter", () => {
  test("the source in use a profile already: disabled, its line; a change not saved: that line instead", () => {
    const page = open("prof");
    expect([add(page).disabled, page.el(ID.profileInUse).hidden, page.el(ID.profileDirty).hidden]).toEqual([true, false, true]);
    page.type(ID.rdKey, "RDTESTKEY00000000000000009");
    expect([add(page).disabled, page.el(ID.profileInUse).hidden, page.el(ID.profileDirty).hidden]).toEqual([true, true, false]);
  });

  test("the list anew with none in use (a save that stayed): enabled, no line", async () => {
    const page = open("prof");
    page.confirmAnswer = true;
    page.replies.push(answer(true, "Профиль «Свой & <конфиг>» удалён", [LIST[0]!, LIST[1]!]));
    await press(buttons(cards(page)[2]!)[2]!);
    expect([add(page).disabled, page.el(ID.profileInUse).hidden]).toEqual([false, true]);
  });

  test("Enter in the name of a new one: a=profile_add, not the save of the form", async () => {
    const page = open("prof", NONE_NOW);
    let submits = 0;
    page.form.addEventListener("submit", () => submits++);
    page.type(ID.profileName, "Мой");
    page.replies.push(answer(true, "Профиль «Мой» сохранён"));
    expect(enter(page, page.el(ID.profileName))).toBe(true);
    await flush();
    expect([page.calls.map((call) => call.url), submits]).toEqual([[url("profile_add")], 0]);
  });

  test("Enter there while disabled (in use): nothing goes", async () => {
    const page = open("prof");
    expect(enter(page, page.el(ID.profileName))).toBe(true);
    await flush();
    expect(page.calls).toEqual([]);
  });
});

describe("Edit and Delete", () => {
  test("Save the profile: i, the name it had, the name typed (no url of a config of Melange); the list anew", async () => {
    const page = open("prof");
    const debrid = cards(page)[0]!;
    await press(buttons(debrid)[1]!);
    page.type(debrid.querySelector("input")!, "RD");
    page.replies.push(answer(true, "Профиль «RD» сохранён", [{ ...LIST[0]!, name: "RD" }, LIST[1]!, LIST[2]!]));
    await press(buttons(debrid)[0]!);
    expect(page.calls).toEqual([{ ...POST, url: url("profile_edit"), body: "i=0&was=Debrid&name=RD" }]);
    // The line in its card (closed, anew), none under the block.
    expect([cards(page).map((card) => seen(card)[0]), lines(cards(page)[0]!.querySelector<HTMLElement>("." + CLS.result)!), results(page)])
      .toEqual([["RD", "NAS", "Свой & <конфиг>" + page.init.i18n.profileNow], [["ok", "Профиль «RD» сохранён"]], []]);
  });

  test("Edit -> Cancel, Delete hidden; Cancel: closed, the values as saved, Delete back", async () => {
    const page = open("prof");
    const own = cards(page)[2]!;
    const [, edit, del] = buttons(own);
    await press(edit!);
    expect([edit!.textContent, edit!.getAttribute("aria-expanded"), del!.hidden]).toEqual([page.init.i18n.profileCancel, "true", true]);
    const [name, link] = Array.from(own.querySelectorAll<HTMLInputElement>("input"));
    page.type(name!, "Другое");
    page.type(link!, LINK2);
    link!.classList.add(CLS.bad);
    await press(edit!);
    expect([edit!.textContent, edit!.getAttribute("aria-expanded"), del!.hidden, buttons(own)[0]!.closest("[hidden]") !== null,
      name!.value, link!.value, link!.classList.contains(CLS.bad)]).toEqual([page.init.i18n.profileEdit, "false", false, true, "Свой & <конфиг>", LINK, false]);
  });

  test("Enter in the name or the link of a card: a=profile_edit of it, not the save of the form", async () => {
    const page = open("prof");
    let submits = 0;
    page.form.addEventListener("submit", () => submits++);
    const own = cards(page)[2]!;
    await press(buttons(own)[1]!);
    const [name, link] = Array.from(own.querySelectorAll<HTMLInputElement>("input"));
    page.replies.push(answer(true, "Профиль не изменился"), answer(true, "Профиль не изменился"));
    expect(enter(page, name!)).toBe(true);
    await flush();
    await press(buttons(cards(page)[2]!)[1]!);
    expect(enter(page, cards(page)[2]!.querySelectorAll<HTMLInputElement>("input")[1]!)).toBe(true);
    await flush();
    expect([page.calls.map((call) => call.url), submits, link!.isConnected]).toEqual([[url("profile_edit"), url("profile_edit")], 0, false]);
  });

  test("an edit refused with the same list (no part): the line in the card, the card open, the values typed kept", async () => {
    const page = open("prof");
    const debrid = cards(page)[0]!;
    await press(buttons(debrid)[1]!);
    page.type(debrid.querySelector("input")!, "RD");
    page.replies.push(answer(false, "Не сохранено: с этим профилем резервная копия будет слишком большой"));
    await press(buttons(debrid)[0]!);
    expect([debrid.isConnected, debrid.querySelector("input")!.value, lines(debrid.querySelector<HTMLElement>("." + CLS.result)!), results(page)])
      .toEqual([true, "RD", [["err", "Не сохранено: с этим профилем резервная копия будет слишком большой"]], []]);
  });

  test("an edit refused with another list (another tab): the list anew, the line under the block", async () => {
    const page = open("prof");
    await press(buttons(cards(page)[0]!)[1]!);
    page.replies.push(answer(false, "Профили изменились в другой вкладке: список обновлён", [LIST[1]!, LIST[2]!]));
    await press(buttons(cards(page)[0]!)[0]!);
    expect([cards(page).length, results(page)]).toEqual([2, [["err", "Профили изменились в другой вкладке: список обновлён"]]]);
  });

  test("a wrong link: the line in the card, the link marked; nothing else changed", async () => {
    const page = open("prof");
    const own = cards(page)[2]!;
    await press(buttons(own)[1]!);
    const link = own.querySelectorAll<HTMLInputElement>("input")[1]!;
    page.type(link, "ftp://x");
    page.replies.push(answer(false, "Нужен адрес вида https://…/manifest.json", LIST, { part: "url" }));
    await press(buttons(own)[0]!);
    expect(page.calls[0]!.body).toBe("i=2&was=" + encodeURIComponent("Свой & <конфиг>") + "&name=" + encodeURIComponent("Свой & <конфиг>") + "&url=ftp%3A%2F%2Fx");
    expect([lines(own.querySelector<HTMLElement>("." + CLS.result)!), link.classList.contains(CLS.bad), results(page), link.isConnected])
      .toEqual([[["err", "Нужен адрес вида https://…/manifest.json"]], true, [], true]);
  });

  test("the link of the one in use: the field of the own config and src_was take it, no change for the bar", async () => {
    const page = open("prof");
    const own = cards(page)[2]!;
    await press(buttons(own)[1]!);
    page.type(own.querySelectorAll<HTMLInputElement>("input")[1]!, LINK2);
    page.replies.push(answer(true, "Профиль сохранён", [LIST[0]!, LIST[1]!, { ...LIST[2]!, url: LINK2 }], { manifest: true }));
    await press(buttons(own)[0]!);
    expect([page.field<HTMLInputElement>(NAME.manifest).value, page.el<HTMLInputElement>(ID.sourceWas).value, page.el(ID.bar).hidden])
      .toEqual([LINK2, "own " + MADE + "  " + LINK2, true]);
  });

  test("the link of the one in use, its field changed on the page: that one stays (a change), src_was too", async () => {
    const page = open("prof");
    page.type(ID.manifest, LINK.replace("dGVzdA", "eA"));
    const own = cards(page)[2]!;
    await press(buttons(own)[1]!);
    page.replies.push(answer(true, "Профиль сохранён", [LIST[0]!, LIST[1]!, { ...LIST[2]!, url: LINK2 }], { manifest: true }));
    await press(buttons(own)[0]!);
    expect([page.field<HTMLInputElement>(NAME.manifest).value, page.el<HTMLInputElement>(ID.sourceWas).value.endsWith(LINK)])
      .toEqual([LINK.replace("dGVzdA", "eA"), true]);
  });

  test("Delete: asked with its name; no - nothing; yes - i and the name posted, the list anew", async () => {
    const page = open("prof");
    await press(buttons(cards(page)[1]!)[2]!);
    expect([page.confirms, page.calls]).toEqual([[page.init.i18n.profileDeleteConfirm.replace("%s", "NAS")], []]);
    page.confirmAnswer = true;
    page.replies.push(answer(true, "Профиль «NAS» удалён", [LIST[0]!, LIST[2]!]));
    await press(buttons(cards(page)[1]!)[2]!);
    expect(page.calls).toEqual([{ ...POST, url: url("profile_del"), body: "i=1&was=NAS" }]);
    expect([cards(page).length, results(page)]).toEqual([2, [["ok", "Профиль «NAS» удалён"]]]);
  });

  test("Delete of a name with $& (a pattern of replace): asked with the name as it is", async () => {
    const page = open("prof", NONE_NOW);
    page.type(ID.profileName, "a$&b$1");
    page.replies.push(answer(true, "ok", [...LIST, { name: "a$&b$1", line: "Debrid · h", now: false, ready: true }]));
    await press(add(page));
    await press(buttons(cards(page)[3]!)[2]!);
    expect(page.confirms).toEqual([page.init.i18n.profileDeleteConfirm.replace("%s", () => "a$&b$1")]);
    expect(page.confirms[0]).toContain("«a$&b$1»");
  });

  test("the profiles changed in another tab: the list of the file, the line", async () => {
    const page = open("prof");
    page.confirmAnswer = true;
    page.replies.push(answer(false, "Профили изменились в другой вкладке: список обновлён", [LIST[2]!]));
    await press(buttons(cards(page)[0]!)[2]!);
    expect([cards(page).length, results(page)]).toEqual([1, [["err", "Профили изменились в другой вкладке: список обновлён"]]]);
  });

  test("a request going: every button of the profiles and Save disabled; back: enabled", async () => {
    const page = open("prof", NONE_NOW);
    const reply = later();
    page.replies.push(reply.reply);
    page.confirmAnswer = true;
    await press(buttons(cards(page)[0]!)[2]!);
    const all = () => [add(page), ...cards(page).flatMap(buttons), page.form.querySelector<HTMLButtonElement>("." + CLS.save)!];
    expect(all().filter((button) => button.textContent !== page.init.i18n.profileEdit).every((button) => button.disabled)).toBe(true);
    await press(buttons(cards(page)[1]!)[2]!);
    expect(page.calls.length).toBe(1);
    reply.resolve(answer(true, "Профиль «Debrid» удалён", [LIST[1]!, { ...LIST[2]!, now: false }]));
    await flush();
    expect(all().some((button) => button.disabled)).toBe(false);
  });

  test("a 403: the profiles locked as the rest of the page", async () => {
    const page = open("prof", NONE_NOW);
    page.replies.push(json({}, 403));
    await press(add(page));
    expect([add(page).disabled, ...cards(page).flatMap(buttons).map((button) => button.disabled)].every(Boolean)).toBe(true);
  });
});

describe("a save that stays on the page", () => {
  const SYNC = { ok: true, msg: "Created", skip: false, base: MADE, mode: "p2p", conf: null };

  test("src_was: of the values posted (the next save keeps the stored source when they come back)", async () => {
    const page = open("filled");
    page.choose(NAME.watch, "p2p");
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "create", notes: [] }), json(SYNC));
    await press(page.form.querySelector<HTMLButtonElement>("." + CLS.save)!);
    expect(page.el<HTMLInputElement>(ID.sourceWas).value).toBe("p2p " + MADE + "  ");
    // Off as saved: no list asked for.
    expect(page.calls.map((call) => call.url)).toEqual([TOKEN + "&ajax=1", url("aio_sync")]);
  });

  test("the setting on: the list asked for after the config, shown anew", async () => {
    const page = open("prof");
    page.choose(NAME.watch, "debrid");
    page.replies.push(json({ ok: true, next: TOKEN + "&saved=1", aio: "update", notes: [] }), json({ ...SYNC, mode: "debrid" }),
      answer(true, "", [{ ...LIST[0]!, now: true }, LIST[1]!, { ...LIST[2]!, now: false }]));
    await press(page.form.querySelector<HTMLButtonElement>("." + CLS.save)!);
    expect(page.calls.map((call) => call.url)).toEqual([TOKEN + "&ajax=1", url("aio_sync"), url("profiles")]);
    expect(cards(page).map((card) => seen(card)[0])).toEqual(["Debrid" + page.init.i18n.profileNow, "NAS", "Свой & <конфиг>"]);
    expect(results(page)).toEqual([]);
  });
});
