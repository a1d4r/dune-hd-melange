// §2.4 The "Check" buttons: each one -> its request -> its result; level warn; no answer of every kind.

import { afterEach, describe, expect, jest, test } from "bun:test";
import { ID } from "../src/dom";
import { type Call, cleanup, flush, HANG, json, later, lines, NET, open, TOKEN } from "./harness";

afterEach(cleanup);

const FORM = "application/x-www-form-urlencoded";

interface Case {
  button: string;
  out: string;
  // The field the check takes and a value typed there.
  input: string;
  value: string;
  call: Call;
  // The result has the class of level warn.
  warn: boolean;
}

const CASES: Case[] = [
  { button: ID.tsCheck, out: ID.tsResult, input: ID.ts, value: "http://192.168.1.10:8090/a b", warn: false,
    call: { method: "GET", url: TOKEN + "&a=ts_check&ts=http%3A%2F%2F192.168.1.10%3A8090%2Fa%20b", body: null, contentType: null } },
  { button: ID.manifestCheck, out: ID.manifestResult, input: ID.manifest, value: "http://192.168.1.10:3000/m.json?x=1&y", warn: true,
    call: { method: "POST", url: TOKEN + "&a=aio_check&ajax=1", body: "manifest=http%3A%2F%2F192.168.1.10%3A3000%2Fm.json%3Fx%3D1%26y", contentType: FORM } },
  { button: ID.aioOwnCheck, out: ID.aioOwnResult, input: ID.aioOwnUrl, value: "http://192.168.1.10:3000", warn: true,
    call: { method: "POST", url: TOKEN + "&a=aio_status&ajax=1", body: "url=http%3A%2F%2F192.168.1.10%3A3000", contentType: FORM } },
  { button: ID.rdCheck, out: ID.rdResult, input: ID.rdKey, value: "RD+KEY", warn: true,
    call: { method: "POST", url: TOKEN + "&a=key_check&ajax=1", body: "what=rd&key=RD%2BKEY", contentType: FORM } },
  { button: ID.tbCheck, out: ID.tbResult, input: ID.tbKey, value: "TB KEY", warn: true,
    call: { method: "POST", url: TOKEN + "&a=key_check&ajax=1", body: "what=tb&key=TB%20KEY", contentType: FORM } },
  { button: ID.tmdbCheck, out: ID.tmdbResult, input: ID.tmdbKey, value: "TMDB", warn: true,
    call: { method: "POST", url: TOKEN + "&a=key_check&ajax=1", body: "what=tmdb&key=TMDB", contentType: FORM } },
];

describe.each(CASES)("$button -> $out", (c) => {
  test("the request; disabled and Checking… while it goes; ok -> ok, not ok -> err", async () => {
    const page = open("filled");
    const button = page.el<HTMLButtonElement>(c.button);
    page.type(c.input, c.value);
    const answer = later();
    page.replies.push(answer.reply);
    await page.click(c.button);
    // A second click while it goes: no second request.
    await page.click(c.button);
    expect(page.calls).toEqual([c.call]);
    expect(button.disabled).toBe(true);
    expect(lines(page.el(c.out))).toEqual([["hint", page.init.i18n.checking]]);
    answer.resolve(json({ ok: true, level: "ok", msg: "Works" }));
    await flush();
    expect(button.disabled).toBe(false);
    expect(lines(page.el(c.out))).toEqual([["ok", "Works"]]);
    page.replies.push(json({ ok: false, level: "err", msg: "Bad key" }));
    await page.click(c.button);
    expect(lines(page.el(c.out))).toEqual([["err", "Bad key"]]);
  });

  test("level warn -> warn" + (c.warn ? "" : " (not for TorrServer: ok or err)"), async () => {
    const page = open("filled");
    page.replies.push(json({ ok: true, level: "warn", msg: "Slow" }), json({ ok: false, level: "warn", msg: "Half" }));
    await page.click(c.button);
    expect(lines(page.el(c.out))).toEqual([[c.warn ? "warn" : "ok", "Slow"]]);
    await page.click(c.button);
    expect(lines(page.el(c.out))).toEqual([[c.warn ? "warn" : "err", "Half"]]);
  });
});

describe("no answer to show", () => {
  test("500 not JSON: the HTTP error with its code; 403 (text): the link is out of date", async () => {
    const page = open("filled");
    page.replies.push({ text: "<html>oops</html>", status: 500 }, { text: "403 Forbidden\n", status: 403 });
    await page.click(ID.rdCheck);
    expect(lines(page.el(ID.rdResult))).toEqual([["err", page.init.i18n.httpError.replace("%d", "500")]]);
    await page.click(ID.rdCheck);
    expect(lines(page.el(ID.rdResult))).toEqual([["err", page.init.i18n.linkExpired]]);
  });

  test("JSON not of its shape (ok not boolean, an array, a string): the HTTP error of its status", async () => {
    const page = open("filled");
    const http200 = page.init.i18n.httpError.replace("%d", "200");
    for (const body of [{ ok: "true", msg: "x" }, [true], "ok", null]) {
      page.replies.push(json(body));
      await page.click(ID.tbCheck);
      expect(lines(page.el(ID.tbResult))).toEqual([["err", http200]]);
    }
    page.replies.push({ text: '{"ok":true,', status: 200 });
    await page.click(ID.tbCheck);
    expect(lines(page.el(ID.tbResult))).toEqual([["err", http200]]);
  });

  test("a lone surrogate in the field: nothing sent or locked, the broken character said; fixed, it goes", async () => {
    const page = open("filled");
    page.type(ID.rdKey, "RD\ud800KEY");
    await page.click(ID.rdCheck);
    expect(page.calls).toEqual([]);
    expect(lines(page.el(ID.rdResult))).toEqual([["err", page.init.i18n.badChars]]);
    expect([page.el<HTMLButtonElement>(ID.rdCheck).disabled, page.el(ID.rdCheck).getAttribute("aria-busy")]).toEqual([false, null]);
    page.type(ID.rdKey, "RDKEY");
    page.replies.push(json({ ok: true, level: "ok", msg: "Works" }));
    await page.click(ID.rdCheck);
    expect(lines(page.el(ID.rdResult))).toEqual([["ok", "Works"]]);
  });

  test("no connection: the text of it, the button back", async () => {
    const page = open("filled");
    page.replies.push(NET);
    await page.click(ID.manifestCheck);
    expect(lines(page.el(ID.manifestResult))).toEqual([["err", page.init.i18n.noConnection]]);
    expect(page.el<HTMLButtonElement>(ID.manifestCheck).disabled).toBe(false);
  });

  test("no answer in 40 s: aborted, the Dune did not answer in 40 s (not no connection)", async () => {
    jest.useFakeTimers();
    const page = open("filled");
    page.replies.push(HANG);
    await page.click(ID.tsCheck);
    jest.advanceTimersByTime(39999);
    await flush();
    expect(lines(page.el(ID.tsResult))).toEqual([["hint", page.init.i18n.checking]]);
    jest.advanceTimersByTime(1);
    await flush();
    expect(lines(page.el(ID.tsResult))).toEqual([["err", "Дюна не ответила за 40 с — повторите"]]);
    expect(page.el<HTMLButtonElement>(ID.tsCheck).disabled).toBe(false);
  });
});
