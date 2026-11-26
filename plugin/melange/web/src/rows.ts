// The screen of the rows of the catalog (#rows): the list comes from the Dune when the screen opens; two parts, as
// the tabs of the catalog (CatRow.tab), each with its rows shown by groups (a group moves among the groups, a row
// within its group; a row of Melange, OWN, is a group of its own without lines of rows) and all its groups, the rows of
// Melange first, one shown by the
// switch (Movies first, till the page is left); the rows shown go to the field cat_rows, a save posts it. The field stays as it is until the user changes something:
// no list, or none of its rows left, and the saved choice is not touched.

import type { Api, CatRow } from "./api";
import { byId, checkedValue, DATA, ID, NAME, type Page, VALUE } from "./dom";
import { failureText, type Texts } from "./i18n";
import { addLine, show } from "./ui";

// The groups of mel_cat_group (catalog.php), in the order of the screen; any other one goes to the last, "other".
const GROUPS: readonly (readonly [string, keyof Texts])[] = [
  ["top", "groupTop"],
  ["fresh", "groupFresh"],
  ["ru", "groupRu"],
  ["ru-services", "groupRuServices"],
  ["genres", "groupGenres"],
  ["theme", "groupTheme"],
  ["studios", "groupStudios"],
  ["decades", "groupDecades"],
  ["years", "groupYears"],
  ["streaming", "groupStreaming"],
  ["world", "groupWorld"],
  ["runtime", "groupRuntime"],
  ["anime", "groupAnime"],
  ["other", "groupOther"],
];
const SCREEN = "#rows";
// Keys in the field: no key has a space (mel_cat_row).
const SEPARATOR = " ";
type Tab = CatRow["tab"];
// The parts of the screen, as the tabs of the catalog; in the field the keys of movies go first.
const TABS: readonly Tab[] = ["movie", "series"];

// The rows of Melange, as mel_cat_pseudo (common.php), in its canonical order: the name (the key <tab>/<name>, its
// group of its own), its text, shown when the field has no key of it.
export const OWN: readonly (readonly [string, keyof Texts, boolean])[] = [
  ["@continue", "rowContinue", true],
  ["@mylist", "rowMylist", true],
  ["@torrserver", "rowTorrserver", false],
];
// The one not there with "No torrents" (mel_tsl_on).
const TORRSERVER = "@torrserver";
const OWN_NAMES = new Set(OWN.map(([name]) => name));
// As mel_cat_pseudo_key (common.php): a key of a row of Melange, <tab>/<name>[:off] -> its tab, name, hidden; null - none.
export function ownKey(key: string): { tab: Tab; name: string; off: boolean } | null {
  const m = /^(movie|series)\/(@[a-z]+)(:off)?$/.exec(key);
  return m && OWN_NAMES.has(m[2]!) ? { tab: m[1] as Tab, name: m[2]!, off: !!m[3] } : null;
}
const isOwn = (key: string): boolean => ownKey(key) !== null;

const KNOWN = new Set(GROUPS.map(([group]) => group));
// As mel_cat_block (catalog.php): the group of a row on the screen, one not of GROUPS "other"; a row of Melange its own.
export const groupOf = (row: Pick<CatRow, "group">): string => (KNOWN.has(row.group) || row.group[0] === "@" ? row.group : "other");

// As mel_cat_blocks (catalog.php): the rows by groups (groupOf), group => its rows, the groups in the order of their
// first row, the rows of each in their order.
export function blocksOf<T extends Pick<CatRow, "group">>(rows: readonly T[]): Map<string, T[]> {
  const blocks = new Map<string, T[]>();
  for (const row of rows) {
    const group = groupOf(row);
    blocks.set(group, [...(blocks.get(group) || []), row]);
  }
  return blocks;
}

// As mel_cat_by_groups (catalog.php): the keys of the rows with those of a group together (blocksOf).
export function byGroups(rows: readonly Pick<CatRow, "key" | "group">[]): string[] {
  return ([] as string[]).concat(...[...blocksOf(rows).values()].map((block) => block.map((row) => row.key)));
}

// As mel_cat_tab_slots (catalog.php) of rows of the server: the keys shown on the tab in order. Of the server: those
// of the choice still in the list, else its start rows (mel_cat_user_rows), the rows of a group together. Of Melange
// (<tab>/@<name>): where its key stands, a group of rows where its first one; :off - hidden; the first key of a name
// counts. No key of it: shown when on by default, right after the nearest one before it in OWN that is shown, none -
// first. The start rows after them.
export function chosenKeys(rows: CatRow[], value: string, tab: Tab): string[] {
  const of = rows.filter((row) => row.tab === tab);
  const by = new Map(of.map((row) => [row.key, row]));
  const keys = [...new Set(value.split(SEPARATOR))];
  const chosen = keys.filter((key) => by.has(key)).map((key) => by.get(key)!);
  const blocks = blocksOf(chosen.length ? chosen : of.filter((row) => row.home));
  // The blocks in their order: a group by its first row, a row of Melange by its key; name => shown, of those with a key.
  const order: string[] = [],
    seen = new Map<string, boolean>();
  for (const key of keys) {
    const row = by.get(key),
      own = ownKey(key);
    let id: string;
    if (row) id = groupOf(row);
    else if (own && own.tab === tab && !seen.has(own.name)) {
      seen.set(own.name, !own.off);
      if (own.off) continue;
      id = own.name;
    } else continue;
    if (!order.includes(id)) order.push(id);
  }
  let prev = "";
  for (const [name, , on] of OWN) {
    if (!seen.has(name) && on) {
      order.splice(prev ? order.indexOf(prev) + 1 : 0, 0, name);
      seen.set(name, true);
    }
    if (seen.get(name)) prev = name;
  }
  order.push(...[...blocks.keys()].filter((id) => !order.includes(id)));
  return ([] as string[]).concat(...order.map((id) => (id[0] === "@" ? [tab + "/" + id] : blocks.get(id)!.map((row) => row.key))));
}

// The four moves of the screen: to the first place, one up, one down, to the last; the path of its icon (24×24); the
// place it moves an item at in a list of n.
const MOVES: readonly (readonly [keyof Texts, string, (at: number, n: number) => number])[] = [
  ["rowsTop", "M5 4h14M12 20V8M6 14l6-6 6 6", () => 0],
  ["rowsUp", "M12 19V5M5 12l7-7 7 7", (at) => at - 1],
  ["rowsDown", "M12 5v14M5 12l7 7 7-7", (at) => at + 1],
  ["rowsBottom", "M5 20h14M12 4v12M6 10l6 6 6-6", (_at, n) => n - 1],
];
const SVG = "http://www.w3.org/2000/svg";

// changed: the bar follows (a move is no change event of the form).
export function setupRows(page: Page, api: Api, changed: () => void): void {
  const { doc, form, texts } = page;
  const field = byId<HTMLInputElement>(doc, ID.rowsValue),
    status = byId(doc, ID.rowsStatus),
    retry = byId(doc, ID.rowsRetry),
    box = byId(doc, ID.rowsBox),
    results = byId(doc, ID.saveResult);
  const switchButton = (tab: Tab) => byId(doc, ID.rowsSwitch).querySelector<HTMLButtonElement>("[" + DATA.key + "=" + tab + "]")!;
  // Of each part: its box, its button in the switch, the rows shown by groups, all its rows by groups, the text
  // when its last row can't go.
  const parts: Record<Tab, { part: HTMLElement; button: HTMLButtonElement; shown: HTMLElement; groups: HTMLElement; last: keyof Texts }> = {
    movie: {
      part: byId(doc, ID.rowsMoviePart),
      button: switchButton("movie"),
      shown: byId(doc, ID.rowsMovies),
      groups: byId(doc, ID.rowsMovieGroups),
      last: "rowsLastMovie",
    },
    series: {
      part: byId(doc, ID.rowsSeriesPart),
      button: switchButton("series"),
      shown: byId(doc, ID.rowsSeries),
      groups: byId(doc, ID.rowsSeriesGroups),
      last: "rowsLastSeries",
    },
  };
  const groupText = new Map<string, keyof Texts>([...GROUPS, ...OWN.map(([name, text]) => [name, text] as const)]);
  const rowOf = new Map<string, CatRow>(),
    // The checkbox of each row in its group; the title of each group: its text and checkbox.
    boxes = new Map<string, HTMLInputElement>(),
    titles: { heading: Text; all: HTMLInputElement; name: string; keys: string[] }[] = [];
  // The rows shown of each part, those of a group together (byGroups).
  const chosen: Record<Tab, string[]> = { movie: [], series: [] };
  let state: "none" | "going" | "here" = "none",
    // The part shown; the other one keeps its choice, only hidden.
    current: Tab = "movie",
    // The line of rowsLast* under the save button, until a change.
    refused: HTMLElement | null = null;
  const isOn = (key: string) => chosen[rowOf.get(key)!.tab].includes(key);
  const nameOf = (group: string) => texts[groupText.get(group)!];
  // Not on the screen: the row of TorrServer with "No torrents" chosen on the page; its key stays as it is.
  const there = (key: string) => !(key.endsWith("/" + TORRSERVER) && checkedValue(form, NAME.noTorrents) === VALUE.off);
  // Where a row of Melange goes on: right after the nearest one before it in OWN that is shown, none - first.
  function ownPlace(tab: Tab, key: string): number {
    const before = OWN.slice(0, OWN.findIndex(([name]) => key === tab + "/" + name)).map(([name]) => chosen[tab].indexOf(tab + "/" + name));
    return Math.max(-1, ...before) + 1;
  }

  // The rows shown of the part by groups, in their order: [group, its keys].
  function blocks(tab: Tab): [string, string[]][] {
    return [...blocksOf(chosen[tab].map((key) => rowOf.get(key)!))].map(([group, rows]) => [group, rows.map((row) => row.key)]);
  }
  // A row with its checkbox, in the list or a group.
  function checkRow(key: string): [HTMLLabelElement, HTMLInputElement] {
    const label = doc.createElement("label"),
      check = doc.createElement("input"),
      name = doc.createElement("span");
    label.className = "cr";
    check.type = "checkbox";
    check.checked = isOn(key);
    check.addEventListener("change", () => toggle([key], check));
    name.textContent = rowOf.get(key)!.name;
    label.append(check, name);
    return [label, check];
  }
  // The four buttons of the item at of the list (a group among the groups of the part, a row in its group), what is
  // moved named for a screen reader; done: the list moved. One that would not move it is off.
  function moveButtons<T>(list: T[], at: number, name: string, done: () => void): HTMLButtonElement[] {
    return MOVES.map(([text, path, place]) => {
      const to = place(at, list.length),
        button = doc.createElement("button"),
        icon = doc.createElementNS(SVG, "svg"),
        line = doc.createElementNS(SVG, "path");
      button.type = "button";
      button.className = "chk";
      button.setAttribute("aria-label", texts[text] + ": " + name);
      button.disabled = to === at || to < 0 || to >= list.length;
      for (const [k, v] of [["viewBox", "0 0 24 24"], ["width", "20"], ["height", "20"], ["fill", "none"], ["stroke", "currentColor"], ["stroke-width", "2"], ["aria-hidden", "true"]])
        icon.setAttribute(k!, v!);
      line.setAttribute("d", path);
      icon.appendChild(line);
      button.appendChild(icon);
      button.addEventListener("click", () => {
        list.splice(to, 0, list.splice(at, 1)[0]!);
        done();
        commit();
      });
      return button;
    });
  }
  function render(): void {
    for (const tab of TABS) {
      const { part, button, shown } = parts[tab],
        every = blocks(tab),
        all = every.filter(([, keys]) => there(keys[0]!));
      // A move puts the groups back in the order of the screen, one not on it right after the one it was after.
      const put = () => {
        const out = [...all];
        every.forEach((block, at) => {
          if (!all.includes(block)) out.splice(at ? out.indexOf(every[at - 1]!) + 1 : 0, 0, block);
        });
        chosen[tab] = ([] as string[]).concat(...out.map(([, keys]) => keys));
      };
      part.hidden = tab !== current;
      button.setAttribute("aria-pressed", tab === current ? "true" : "false");
      // Only the rows of the server: the rows of Melange were there before they could be chosen.
      button.querySelector("span")!.textContent = " · " + chosen[tab].filter((key) => !isOwn(key)).length;
      shown.textContent = "";
      all.forEach(([group, keys], at) => {
        const head = doc.createElement("div"),
          name = doc.createElement("span");
        head.className = "crh";
        name.textContent = nameOf(group);
        head.append(name, ...moveButtons(all, at, nameOf(group), put));
        shown.appendChild(head);
        // A row of Melange: its title only.
        if (group[0] === "@") return;
        // The moves of a row splice keys itself, the array in all: put() reads the move from there (a copy would lose it).
        keys.forEach((key, n) => {
          const line = doc.createElement("div");
          line.className = "cro";
          line.append(checkRow(key)[0], ...moveButtons(keys, n, rowOf.get(key)!.name, put));
          shown.appendChild(line);
        });
      });
    }
    boxes.forEach((check, key) => {
      check.checked = isOn(key);
      check.parentElement!.hidden = !there(key);
    });
    for (const { heading, all, name, keys } of titles) {
      const of = keys.filter(there),
        on = of.filter(isOn).length;
      heading.textContent = name + " · " + on + "/" + of.length;
      all.checked = on === of.length;
      all.indeterminate = on > 0 && on < of.length;
    }
  }
  // The field: of each tab the keys shown in order, then those of the rows of Melange hidden, with :off.
  function commit(): void {
    if (refused) refused.remove();
    refused = null;
    field.value = ([] as string[])
      .concat(...TABS.map((tab) => [...chosen[tab], ...OWN.map(([name]) => tab + "/" + name).filter((key) => !isOn(key)).map((key) => key + ":off")]))
      .join(SEPARATOR);
    render();
    changed();
  }
  // The checkbox of a row or of a group (keys: its rows): the ones not on go on, last in their group in the order of
  // keys, a group not shown last in the part, a row of Melange where it is by default (ownPlace); all on, all go off.
  function toggle(keys: string[], check: HTMLInputElement): void {
    const tab = rowOf.get(keys[0]!)!.tab;
    if (!keys.every(isOn)) {
      if (isOwn(keys[0]!)) for (const key of keys.filter((key) => !isOn(key))) chosen[tab].splice(ownPlace(tab, key), 0, key);
      else chosen[tab] = byGroups([...chosen[tab], ...keys.filter((key) => !isOn(key))].map((key) => rowOf.get(key)!));
    } else if (isOwn(keys[0]!) || chosen[tab].some((key) => !isOwn(key) && !keys.includes(key))) chosen[tab] = chosen[tab].filter((key) => !keys.includes(key));
    else {
      // Never an empty part: its last rows stay, the bar under the screen says why.
      check.checked = true;
      if (refused) refused.remove();
      refused = addLine(results, "warn", texts[parts[tab].last]);
      changed();
      return;
    }
    commit();
  }
  // The rows of Melange of the tab first, as a group.
  function buildGroups(rows: CatRow[], groupsBox: HTMLElement, tab: Tab): void {
    const of = new Map([["@", OWN.map(([name]) => rowOf.get(tab + "/" + name)!)], ...blocksOf(rows)]);
    for (const [group, text] of [["@", "groupOwn"] as const, ...GROUPS]) {
      const list = of.get(group);
      if (!list) continue;
      const details = doc.createElement("details"),
        summary = doc.createElement("summary"),
        all = doc.createElement("input"),
        heading = doc.createTextNode(""),
        keys = list.map((row) => row.key);
      details.className = "crg";
      all.type = "checkbox";
      all.setAttribute("aria-label", texts.rowsGroupAll + ": " + texts[text]);
      // A click on a form control in the summary leaves the details as they are (Chrome, WebKit and Firefox:
      // the activation is the control's); on the text it opens or closes them.
      all.addEventListener("change", () => toggle(keys.filter(there), all));
      summary.append(all, heading);
      details.appendChild(summary);
      for (const row of list) {
        const [label, check] = checkRow(row.key);
        boxes.set(row.key, check);
        details.appendChild(label);
      }
      titles.push({ heading, all, name: texts[text], keys });
      groupsBox.appendChild(details);
    }
  }
  function load(): void {
    if (state !== "none") return;
    state = "going";
    retry.hidden = true;
    show(status, "hint", [texts.rowsLoading]);
    api.catRows().then((result) => {
      state = "none";
      if (result.kind !== "data" || !result.data.ok || !result.data.rows.length) {
        show(status, "err", [result.kind !== "data" ? failureText(texts, result) : texts.rowsNoList]);
        retry.hidden = false;
        return;
      }
      state = "here";
      const { rows, stale } = result.data;
      for (const row of rows) rowOf.set(row.key, row);
      if (stale) show(status, "warn", [texts.rowsStale]);
      else status.textContent = "";
      for (const tab of TABS) {
        for (const [name, text, on] of OWN) rowOf.set(tab + "/" + name, { key: tab + "/" + name, name: texts[text], group: name, tab: tab, home: on });
        chosen[tab] = chosenKeys(rows, field.value, tab);
        buildGroups(
          rows.filter((row) => row.tab === tab),
          parts[tab].groups,
          tab,
        );
      }
      render();
      box.hidden = false;
    });
  }
  const opened = () => {
    if (doc.location.hash === SCREEN) load();
  };
  for (const tab of TABS)
    parts[tab].button.addEventListener("click", () => {
      current = tab;
      render();
    });
  retry.addEventListener("click", load);
  // "No torrents" chosen or not: the row of TorrServer goes from the screen or comes back.
  form.querySelectorAll("input[name=" + NAME.noTorrents + "]").forEach((radio) => radio.addEventListener("change", () => state === "here" && render()));
  doc.defaultView!.addEventListener("hashchange", opened);
  opened();
}
