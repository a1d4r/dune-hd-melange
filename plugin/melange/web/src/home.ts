// The main screen: the state (#state) and the line of each screen (its dot and value) by the values of the form now,
// by the rules of PHP (aio_cgi_state, aio_cgi_rows in cgi/lib/home.php): the same values give the same page.

import type { Aio } from "./aio";
import { byId, checkedValue, DATA, hostOf, ID, NAME, type Page, type Row, SERVER_BLOCK, SERVER_FIELD, trimmed, VALUE, watchOf } from "./dom";

export interface Home {
  render(): void;
  // After a save that stays on the page: the first run is over, the state instead of its question.
  saved(): void;
}

// The state of aio_cgi_state: key - Debrid without a key, conf - no config of the mode on the server, man - own
// config without its link, ok.
type State = "key" | "conf" | "man" | "ok";
// A dot: ok, warn or none (grey).
type Dot = "ok" | "warn" | "";

// aio: "Save" creates a config (no config of the mode chosen on the server), the server chosen and its config.
export function setupHome(page: Page, aio: Aio): Home {
  const { doc, form, texts } = page;
  const value = (id: string) => trimmed(byId<HTMLInputElement>(doc, id).value);

  function state(): State {
    const watch = watchOf(form);
    if (watch === VALUE.own) return value(ID.manifest) === "" ? "man" : "ok";
    if (watch === VALUE.debrid && value(ID.rdKey) + value(ID.tbKey) === "") return "key";
    return aio.creates() ? "conf" : "ok";
  }
  function sourceLine(watch: string): string {
    if (watch === VALUE.own) return texts.watchOwn + (value(ID.manifest) === "" ? " · " + texts.needLink : "");
    // The template of the mode by its short name (0.44.0: P2P too).
    const template = byId<HTMLSelectElement>(doc, watch === VALUE.p2p ? ID.templateP2p : ID.template),
      option = template.options[template.selectedIndex];
    const short = option ? option.getAttribute(DATA.short) : null,
      tail = short ? " · " + short : "";
    if (watch === VALUE.p2p) return texts.modeP2p + " · " + texts.viaTs + tail;
    const keys: string[] = [];
    if (value(ID.rdKey) !== "") keys.push("Real-Debrid");
    if (value(ID.tbKey) !== "") keys.push("TorBox");
    return "Debrid · " + (keys.length ? keys.join(" + ") : texts.needKey) + tail;
  }
  // The names of the server blocks filled (else their address).
  function servers(): string[] {
    const names: string[] = [];
    form.querySelectorAll(SERVER_BLOCK).forEach((block) => {
      let name = "",
        url = "";
      block.querySelectorAll<HTMLInputElement>("input").forEach((field) => {
        const match = SERVER_FIELD.exec(field.name);
        if (match && match[2] === "name") name = trimmed(field.value);
        if (match && match[2] === "url") url = trimmed(field.value);
      });
      if (name + url !== "") names.push(name !== "" ? name : url);
    });
    return names;
  }
  function setRow(id: Row, dot: Dot, text: string): void {
    byId(doc, "d" + id).className = "dot" + (dot ? " " + dot : "");
    byId(doc, "v" + id).textContent = text;
  }

  function render(): void {
    const watch = watchOf(form),
      st = state();
    const box = byId(doc, ID.state);
    box.className = "state " + (st === "ok" ? "ok" : "warn");
    box.setAttribute(DATA.state, st);
    const lines = box.querySelectorAll<HTMLElement>("p[" + DATA.state + "]");
    for (let i = 0; i < lines.length; i++) lines[i]!.hidden = lines[i]!.getAttribute(DATA.state) !== st;
    setRow("src", st === "ok" ? "ok" : "warn", sourceLine(watch));
    // The server: not for own config (the line hidden).
    const host = aio.serverHost();
    setRow("cfg", watch === VALUE.own ? "" : aio.hasConf() ? "ok" : "warn", host !== "" ? host : texts.otherAioServer);
    const jacred = checkedValue(form, NAME.jacred) || "",
      jr = jacred !== VALUE.own ? jacred : hostOf(byId<HTMLInputElement>(doc, ID.jacredOwnUrl).value);
    setRow("voices", jr === "" ? "warn" : "ok", jr !== "" ? jr : texts.ownJacred);
    const ts = value(ID.ts);
    setRow("torr", watch === VALUE.p2p ? "ok" : "", (ts !== "" ? ts : texts.tsDune) + (watch === VALUE.debrid ? " · " + texts.tsSpare : ""));
    const names = servers();
    setRow("dl", names.length ? "ok" : "", names.length ? names.join(", ") : texts.noServers);
  }
  render();
  return {
    render: render,
    saved(): void {
      const first = doc.getElementById(ID.first);
      if (first) first.hidden = true;
      byId(doc, ID.state).hidden = false;
    },
  };
}
