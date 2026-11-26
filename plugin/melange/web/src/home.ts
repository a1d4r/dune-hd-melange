// The main screen: the state (#state) and the line of each screen (its dot and value) by the values of the form now,
// by the rules of PHP (mel_cgi_state, mel_cgi_rows in cgi/lib/home.php): the same values give the same page.

import type { Aio } from "./aio";
import { byId, checkedRadio, checkedValue, DATA, hostOf, ID, NAME, type Page, type Row, SERVER_BLOCK, SERVER_FIELD, trimmed, tsUserinfo, VALUE, watchOf } from "./dom";

export interface Home {
  render(): void;
  // After a save that stays on the page: the first run is over, the state instead of its question, the backup's
  // "Save to a file" instead of its line.
  saved(): void;
}

// The state of mel_cgi_state: key - Debrid without a key, conf - no config of the mode on the server, man - own
// config without its link, lp - Lampac without its address (v2), ok.
type State = "key" | "conf" | "man" | "lp" | "ok";
// A dot: ok, warn or "" (grey); "none" (no dot) is PHP only, on lines JS never sets.
type Dot = "ok" | "warn" | "";

// aio: "Save" creates a config (no config of the mode chosen on the server), the server chosen and its config, the
// short name of the template.
export function setupHome(page: Page, aio: Pick<Aio, "creates" | "hasConf" | "serverHost" | "templateShort">): Home {
  const { doc, form, texts } = page;
  const value = (id: string) => trimmed(byId<HTMLInputElement>(doc, id).value);

  function state(): State {
    const watch = watchOf(form);
    if (watch === VALUE.lampac) return value(ID.lampacUrl) === "" ? "lp" : "ok";
    if (watch === VALUE.own) return value(ID.manifest) === "" ? "man" : "ok";
    if (watch === VALUE.debrid && value(ID.rdKey) + value(ID.tbKey) === "") return "key";
    return aio.creates() ? "conf" : "ok";
  }
  function sourceLine(watch: string): string {
    if (watch === VALUE.lampac) {
      const host = hostOf(value(ID.lampacUrl));
      return "Lampac · " + (value(ID.lampacUrl) !== "" ? host : texts.needAddress);
    }
    if (watch === VALUE.own) return texts.watchOwn + (value(ID.manifest) === "" ? " · " + texts.needLink : "");
    // The template of the mode by its short name (0.44.0: P2P too).
    const short = aio.templateShort(watch),
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
  // The value of a line fixed by PHP (data-fixed: the source with two sources or more) stays.
  function setRow(id: Row, dot: Dot, text: string): void {
    byId(doc, "d" + id).className = "dot" + (dot ? " " + dot : "");
    const line = byId(doc, "v" + id);
    if (!line.hasAttribute(DATA.fixed)) line.textContent = text;
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
    // The server: not for own config nor Lampac (the line hidden).
    const host = aio.serverHost();
    setRow("cfg", watch === VALUE.own || watch === VALUE.lampac ? "" : aio.hasConf() ? "ok" : "warn", host !== "" ? host : texts.ownAioServer);
    const jacred = checkedValue(form, NAME.jacred) || "",
      jr = jacred !== VALUE.own ? jacred : hostOf(byId<HTMLInputElement>(doc, ID.jacredOwnUrl).value);
    setRow("voices", jr === "" ? "warn" : "ok", jr !== "" ? jr : texts.ownJacred);
    // Never the login and password typed in the address (login:password@host): as mel_ts_userinfo (common.php).
    const ts = tsUserinfo(value(ID.ts))[0];
    setRow("torr", watch === VALUE.p2p ? "ok" : "", (ts !== "" ? ts : texts.tsDune) + (watch === VALUE.debrid ? " · " + texts.tsSpare : ""));
    const names = servers();
    setRow("dl", names.length ? "ok" : "", names.length ? names.join(", ") : texts.noServers);
    // No dot: only the value (a wrong one as on, as PHP).
    byId(doc, "vlook").textContent = (checkedValue(form, NAME.catLogo) === VALUE.off ? texts.logoOff : texts.logoOn) + " · " +
      (checkedValue(form, NAME.catRating) === VALUE.off ? texts.ratingOff : texts.ratingOn);
    // The age and the IMDb ratings by their short names, as mel_cgi_hide_line: "До 12+, IMDb: фильмы от 6.5, сериалы
    // от 7", each part only when set (none checked or selected: all, any); none set - the short name of all, the first
    // radio of the age. The ratings are lists (0.70.0): the data-short of the option selected ("" of any).
    const short = (radio: HTMLInputElement | null): string => (radio && radio.value !== VALUE.all ? radio.getAttribute(DATA.short) || "" : "");
    const selected = (name: string): string => {
      const list = form.elements.namedItem(name) as HTMLSelectElement | null;
      const option = list && list.selectedIndex >= 0 ? list.options[list.selectedIndex] : null;
      return option ? option.getAttribute(DATA.short) || "" : "";
    };
    const imdb = [NAME.catImdbMovie, NAME.catImdbSeries].map(selected).filter((part) => part !== "");
    const parts = [short(checkedRadio(form, NAME.catAge))].concat(imdb.length ? ["IMDb: " + imdb.join(", ")] : []).filter((part) => part !== "");
    const all = form.querySelector<HTMLInputElement>("input[name=" + NAME.catAge + "]");
    byId(doc, "vhide").textContent = parts.length ? parts.join(", ") : all ? all.getAttribute(DATA.short) || "" : "";
  }
  render();
  return {
    render: render,
    saved(): void {
      const first = doc.getElementById(ID.first);
      if (first) first.hidden = true;
      byId(doc, ID.state).hidden = false;
      byId(doc, ID.backupNone).hidden = true;
      byId(doc, ID.backupExport).hidden = false;
    },
  };
}
