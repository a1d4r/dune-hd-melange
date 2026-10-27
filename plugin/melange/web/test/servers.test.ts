// §2.2 Servers: "Add server", "Duplicate" (the name cut without splitting a surrogate pair), "Check" of a block.

import { afterEach, describe, expect, mock, test } from "bun:test";
import { CLS, ID } from "../src/dom";
import { cleanup, flush, json, later, lines, open, type Page, shown, TOKEN } from "./harness";

afterEach(cleanup);

const blocks = (page: Page) => Array.from(page.doc.querySelectorAll<HTMLDetailsElement>("details." + CLS.serverBlock));
const dup = (page: Page, blockNo: number) => blocks(page)[blockNo - 1]!.querySelector<HTMLButtonElement>("." + CLS.serverDup)!;
const result = (page: Page, blockNo: number) => blocks(page)[blockNo - 1]!.querySelector<HTMLElement>("." + CLS.serverResult)!;
const value = (page: Page, name: string) => page.field<HTMLInputElement>(name).value;

describe("Add server", () => {
  test("the first hidden block shown, opened, its first field focused; Add gone with the last hidden one", async () => {
    const page = open("filled");
    await page.click(ID.addServer);
    const second = blocks(page)[1]!;
    expect([shown(second), second.open]).toEqual([true, true]);
    expect(page.doc.activeElement).toBe(page.field("s2_name"));
    expect(page.el(ID.addServer).hidden).toBe(false);
    expect(dup(page, 1).disabled).toBe(false);
    for (let i = 0; i < 3; i++) await page.click(ID.addServer);
    expect(blocks(page).map(shown)).toEqual([true, true, true, true, true]);
    expect(page.el(ID.addServer).hidden).toBe(true);
    // No hidden block: every Duplicate disabled, its title tells why.
    expect(blocks(page).map((_block, i) => dup(page, i + 1).disabled)).toEqual([true, true, true, true, true]);
    expect(blocks(page).map((_block, i) => dup(page, i + 1).title)).toEqual(Array(5).fill(page.init.i18n.serversFull));
  });
});

describe("Duplicate", () => {
  test("every field copied into the next hidden block, name + suffix, result cleared, shown, scrolled, focused", () => {
    const page = open("filled");
    page.field<HTMLSelectElement>("s1_cache").value = "torbox";
    page.type("s1_tags", "a,b");
    result(page, 2).textContent = "old";
    const target = blocks(page)[1]!;
    const scroll = mock(() => {});
    target.scrollIntoView = scroll;
    dup(page, 1).click();
    expect(["url", "user", "pass", "category", "tags", "cache"].map((key) => value(page, "s2_" + key))).toEqual([
      "http://192.168.1.10:8080", "admin", "test-pass", "movies", "a,b", "torbox",
    ]);
    expect(value(page, "s2_name")).toBe("qBittorrent" + page.init.i18n.copySuffix);
    expect(value(page, "s1_name")).toBe("qBittorrent");
    expect(result(page, 2).textContent).toBe("");
    expect([shown(target), target.open]).toEqual([true, true]);
    expect(scroll).toHaveBeenCalledTimes(1);
    expect(page.doc.activeElement).toBe(page.field("s2_name"));
    expect(page.el(ID.addServer).hidden).toBe(false);
    // No event of a field: the line of the main screen and the bar follow anyway.
    expect(page.el("vdl").textContent).toBe("qBittorrent, qBittorrent" + page.init.i18n.copySuffix);
    expect(page.el(ID.bar).hidden).toBe(false);
  });

  test("into the first hidden block, not the one after", () => {
    const page = open("filled");
    page.el(ID.addServer).click();
    page.el(ID.addServer).click();
    page.type("s3_url", "http://192.168.1.10:8083");
    dup(page, 1).click();
    expect(value(page, "s4_url")).toBe("http://192.168.1.10:8080");
    expect(value(page, "s3_url")).toBe("http://192.168.1.10:8083");
  });

  test("the name cut to 40 with the suffix, a surrogate pair never split", () => {
    const page = open("filled");
    const suffix = page.init.i18n.copySuffix;
    const keep = 40 - suffix.length;
    // The cut falls between the halves of the emoji: it goes whole.
    page.type("s1_name", "a".repeat(keep - 1) + "😀" + "bbb");
    dup(page, 1).click();
    expect(value(page, "s2_name")).toBe("a".repeat(keep - 1) + suffix);
    // A plain name: cut at keep.
    page.type("s1_name", "c".repeat(45));
    dup(page, 1).click();
    expect(value(page, "s3_name")).toBe("c".repeat(keep) + suffix);
    expect(value(page, "s3_name").length).toBe(40);
    // A whole pair before the cut stays.
    page.type("s1_name", "a".repeat(keep - 2) + "😀" + "bbb");
    dup(page, 1).click();
    expect(value(page, "s4_name")).toBe("a".repeat(keep - 2) + "😀" + suffix);
  });

  test("no name: Server <n> + suffix", () => {
    const page = open("filled");
    page.type("s1_name", "");
    dup(page, 1).click();
    expect(value(page, "s2_name")).toBe(page.init.i18n.server + " 1" + page.init.i18n.copySuffix);
  });

  test("a blank block: nothing copied", () => {
    const page = open("filled");
    page.el(ID.addServer).click();
    dup(page, 2).click();
    expect(blocks(page).map(shown)).toEqual([true, true, false, false, false]);
    expect(value(page, "s3_name")).toBe("");
  });
});

describe("Client of a block", () => {
  const labelOf = (page: Page, name: string) =>
    page.doc.querySelector<HTMLLabelElement>('label[for="' + page.field(name).id + '"]')!.textContent;
  const texts = (page: Page, blockNo: number) => [
    labelOf(page, "s" + blockNo + "_category"),
    labelOf(page, "s" + blockNo + "_tags"),
    page.field("s" + blockNo + "_url").placeholder,
  ];
  const QBT = ["Категория (необязательно)", "Теги через запятую (необязательно)", "http://192.168.1.10:8080"];
  const TR = ["Папка загрузки на сервере (необязательно)", "Метки через запятую (необязательно)", "http://192.168.1.10:9091"];

  test("Transmission chosen: the labels of category and tags, the address placeholder; back to qBittorrent", () => {
    const page = open("filled");
    expect(value(page, "s1_type")).toBe("qbt");
    expect(texts(page, 1)).toEqual(QBT);
    page.type("s1_type", "tr");
    expect(texts(page, 1)).toEqual(TR);
    // The values typed stay: the same keys on the server.
    expect([value(page, "s1_category"), value(page, "s1_url")]).toEqual(["movies", "http://192.168.1.10:8080"]);
    expect(texts(page, 2)).toEqual(QBT);
    page.type("s1_type", "qbt");
    expect(texts(page, 1)).toEqual(QBT);
  });

  test("a client restored by the browser before the script (Back, reload): its texts at load", () => {
    const page = open("filled", {
      before: (doc) => {
        doc.querySelector<HTMLSelectElement>('[name="s1_type"]')!.value = "tr";
      },
    });
    expect(texts(page, 1)).toEqual(TR);
    expect(texts(page, 2)).toEqual(QBT);
  });

  test("Duplicate of a Transmission block: the client and its texts in the copy", () => {
    const page = open("filled");
    page.type("s1_type", "tr");
    dup(page, 1).click();
    expect(value(page, "s2_type")).toBe("tr");
    expect(texts(page, 2)).toEqual(TR);
  });

  test("Check of a Transmission block posts type=tr", async () => {
    const page = open("filled");
    page.type("s1_type", "tr");
    page.replies.push(json({ ok: true, msg: "OK", lines: ["OK: вход принят · версия 4.1.3"] }));
    blocks(page)[0]!.querySelector<HTMLButtonElement>("." + CLS.serverCheck)!.click();
    await flush();
    expect(page.calls[0]!.body).toBe(
      "type=tr&url=http%3A%2F%2F192.168.1.10%3A8080&user=admin&pass=test-pass&category=movies&tags=",
    );
    expect(lines(result(page, 1))).toEqual([["ok", "OK: вход принят · версия 4.1.3"]]);
  });
});

describe("Check of a block", () => {
  test("POST srv_check with type, url, user, pass, category, tags of its block; disabled and Checking… while it goes", async () => {
    const page = open("filled");
    const button = blocks(page)[0]!.querySelector<HTMLButtonElement>("." + CLS.serverCheck)!;
    const answer = later();
    page.replies.push(answer.reply);
    page.type("s1_tags", "x y&z");
    button.click();
    await flush();
    expect(page.calls).toEqual([
      {
        method: "POST",
        url: TOKEN + "&a=srv_check&ajax=1",
        body: "type=qbt&url=http%3A%2F%2F192.168.1.10%3A8080&user=admin&pass=test-pass&category=movies&tags=x%20y%26z",
        contentType: "application/x-www-form-urlencoded",
      },
    ]);
    expect(button.disabled).toBe(true);
    expect(lines(result(page, 1))).toEqual([["hint", page.init.i18n.checking]]);
    answer.resolve(json({ ok: true, msg: "ok", lines: ["Connected", "qBittorrent v5"] }));
    await flush();
    expect(button.disabled).toBe(false);
    expect(lines(result(page, 1))).toEqual([["ok", "Connected"], ["ok", "qBittorrent v5"]]);
  });

  test("a lone surrogate in a field of the block: nothing sent, the button stays enabled", async () => {
    const page = open("filled");
    const button = blocks(page)[0]!.querySelector<HTMLButtonElement>("." + CLS.serverCheck)!;
    page.type("s1_pass", "\udc00pass");
    button.click();
    await flush();
    expect(page.calls).toEqual([]);
    expect(lines(result(page, 1))).toEqual([["err", page.init.i18n.badChars]]);
    expect(button.disabled).toBe(false);
  });

  test("no lines: msg; ok false: err; 403: the link is out of date", async () => {
    const page = open("filled");
    const button = blocks(page)[0]!.querySelector<HTMLButtonElement>("." + CLS.serverCheck)!;
    page.replies.push(json({ ok: false, msg: "Wrong password", lines: [] }), { text: "403 Forbidden\n", status: 403 });
    button.click();
    await flush();
    expect(lines(result(page, 1))).toEqual([["err", "Wrong password"]]);
    button.click();
    await flush();
    expect(lines(result(page, 1))).toEqual([["err", page.init.i18n.linkExpired]]);
    expect(lines(result(page, 2))).toEqual([]);
  });
});
