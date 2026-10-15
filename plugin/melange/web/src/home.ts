// The main screen: the state (#state) and the line of each screen (its dot and value) by the values of the form now,
// by the rules of PHP (aio_cgi_state, aio_cgi_rows in cgi/lib/form.php): the same values give the same page.

import { byId, checkedValue, DATA, ID, NAME, type Page, type Row, SERVER_BLOCK, SERVER_FIELD, trimmed, VALUE, watchOf } from "./dom";

export interface Home {
  render(): void;
}

// The state of aio_cgi_state: key - Debrid without a key, conf - no config of the mode on the server, man - own
// config without its link, ok.
type State = "key" | "conf" | "man" | "ok";
// A dot: ok, warn or none (grey).
type Dot = "ok" | "warn" | "";

// The host of an address as parse_url() of PHP finds it ("" - none).
function host(url: string): string {
  const found = /^[a-z][a-z0-9+.-]*:\/\/(?:[^\/?#@]*@)?(\[[^\]\/]*\]|[^\/:?#]*)/i.exec(url);
  return found ? found[1]! : "";
}

// hasConf: the chosen server has a config of the mode chosen (Aio).
export function setupHome(page: Page, hasConf: () => boolean): Home {
  const { doc, form, texts } = page;
  const value = (id: string) => trimmed(byId<HTMLInputElement>(doc, id).value);

  function state(): State {
    const watch = watchOf(form);
    if (watch === VALUE.own) return value(ID.manifest) === "" ? "man" : "ok";
    if (watch === VALUE.debrid && value(ID.rdKey) + value(ID.tbKey) === "") return "key";
    return hasConf() ? "ok" : "conf";
  }
  function sourceLine(watch: string): string {
    if (watch === VALUE.own) return texts.watchOwn + (value(ID.manifest) === "" ? " · " + texts.needLink : "");
    if (watch === VALUE.p2p) return texts.templateP2p + " · " + texts.viaTs;
    const keys: string[] = [];
    if (value(ID.rdKey) !== "") keys.push("Real-Debrid");
    if (value(ID.tbKey) !== "") keys.push("TorBox");
    const template = byId<HTMLSelectElement>(doc, ID.template),
      option = template.options[template.selectedIndex];
    const short = option ? option.getAttribute(DATA.short) : null;
    return "Debrid · " + (keys.length ? keys.join(" + ") : texts.needKey) + (short ? " · " + short : "");
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
    // None on the first run (#first instead).
    const box = doc.getElementById(ID.state);
    if (box) {
      box.className = "state " + (st === "ok" ? "ok" : "warn");
      box.setAttribute(DATA.state, st);
      const lines = box.querySelectorAll<HTMLElement>("p[" + DATA.state + "]");
      for (let i = 0; i < lines.length; i++) lines[i]!.hidden = lines[i]!.getAttribute(DATA.state) !== st;
    }
    setRow("src", st === "ok" ? "ok" : "warn", sourceLine(watch));
    const jacred = checkedValue(form, NAME.jacred) || "",
      jr = jacred !== VALUE.own ? jacred : host(byId<HTMLInputElement>(doc, ID.jacredOwnUrl).value);
    setRow("voices", jr === "" ? "warn" : "ok", jr !== "" ? jr : texts.ownJacred);
    const ts = value(ID.ts);
    setRow("torr", watch === VALUE.p2p ? "ok" : "", (ts !== "" ? ts : texts.tsDune) + (watch === VALUE.debrid ? " · " + texts.tsSpare : ""));
    const names = servers();
    setRow("dl", names.length ? "ok" : "", names.length ? names.join(", ") : texts.noServers);
  }
  render();
  return { render: render };
}
