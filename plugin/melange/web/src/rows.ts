// The screen of the rows of the catalog (#rows): the list comes from the Dune when the screen opens; two parts, as
// the tabs of the catalog (CatRow.tab), each with its rows shown in their order and its groups, one shown by the
// switch (Movies first, till the page is left); the rows shown go to the field cat_rows, a save posts it. The field stays as it is until the user changes something:
// no list, or none of its rows left, and the saved choice is not touched.

import type { Api, CatRow } from "./api";
import { byId, DATA, ID, type Page } from "./dom";
import { failureText, type Texts } from "./i18n";
import { addLine, show } from "./ui";

// The groups of aio_cat_group (catalog.php), in the order of the screen; any other one goes to the last, "other".
const GROUPS: readonly (readonly [string, keyof Texts])[] = [
  ["top", "groupTop"],
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
// Keys in the field: no key has a space (aio_cat_row).
const SEPARATOR = " ";
type Tab = CatRow["tab"];
// The parts of the screen, as the tabs of the catalog; in the field the keys of movies go first.
const TABS: readonly Tab[] = ["movie", "series"];

// As aio_cat_user_rows (catalog.php): the keys of the choice of the tab still in the list, else its start rows.
export function chosenKeys(rows: CatRow[], value: string, tab: Tab): string[] {
  const of = rows.filter((row) => row.tab === tab);
  const known = new Set(of.map((row) => row.key));
  const chosen = [...new Set(value.split(SEPARATOR))].filter((key) => known.has(key));
  return chosen.length ? chosen : of.filter((row) => row.home).map((row) => row.key);
}

// changed: the bar follows (a move is no change event of the form).
export function setupRows(page: Page, api: Api, changed: () => void): void {
  const { doc, texts } = page;
  const field = byId<HTMLInputElement>(doc, ID.rowsValue),
    status = byId(doc, ID.rowsStatus),
    retry = byId(doc, ID.rowsRetry),
    box = byId(doc, ID.rowsBox),
    results = byId(doc, ID.saveResult);
  const switchButton = (tab: Tab) => byId(doc, ID.rowsSwitch).querySelector<HTMLButtonElement>("[" + DATA.key + "=" + tab + "]")!;
  // Of each part: its box, its button in the switch, the rows shown in their order, all its rows by groups, the text
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
  const names = new Map<string, string>(),
    tabs = new Map<string, Tab>(),
    // The checkbox of each row in its group, the title and rows of each group.
    boxes = new Map<string, HTMLInputElement>(),
    titles: [HTMLElement, string, string[]][] = [];
  const chosen: Record<Tab, string[]> = { movie: [], series: [] };
  let state: "none" | "going" | "here" = "none",
    // The part shown; the other one keeps its choice, only hidden.
    current: Tab = "movie",
    // The line of rowsLast* under the save button, until a change.
    refused: HTMLElement | null = null;
  const isOn = (key: string) => chosen[tabs.get(key)!].includes(key);

  // A row with its checkbox, in the list or a group.
  function checkRow(key: string): [HTMLLabelElement, HTMLInputElement] {
    const label = doc.createElement("label"),
      check = doc.createElement("input"),
      name = doc.createElement("span");
    label.className = "cr";
    check.type = "checkbox";
    check.checked = isOn(key);
    check.addEventListener("change", () => toggle(key, check));
    name.textContent = names.get(key)!;
    label.append(check, name);
    return [label, check];
  }
  function moveButton(list: string[], key: string, text: string, step: number, at: number): HTMLButtonElement {
    const button = doc.createElement("button");
    button.type = "button";
    button.className = "chk";
    button.textContent = step < 0 ? "↑" : "↓";
    button.setAttribute("aria-label", text);
    button.disabled = at + step < 0 || at + step >= list.length;
    button.addEventListener("click", () => {
      list.splice(at, 1);
      list.splice(at + step, 0, key);
      commit();
    });
    return button;
  }
  function render(): void {
    for (const tab of TABS) {
      const list = chosen[tab],
        { part, button, shown } = parts[tab];
      part.hidden = tab !== current;
      button.setAttribute("aria-pressed", tab === current ? "true" : "false");
      button.querySelector("span")!.textContent = " · " + list.length;
      shown.textContent = "";
      list.forEach((key, at) => {
        const line = doc.createElement("div");
        line.className = "cro";
        line.append(checkRow(key)[0], moveButton(list, key, texts.rowsUp, -1, at), moveButton(list, key, texts.rowsDown, 1, at));
        shown.appendChild(line);
      });
    }
    boxes.forEach((check, key) => (check.checked = isOn(key)));
    for (const [title, name, keys] of titles) title.textContent = name + " · " + keys.filter(isOn).length + "/" + keys.length;
  }
  function commit(): void {
    if (refused) refused.remove();
    refused = null;
    field.value = [...chosen.movie, ...chosen.series].join(SEPARATOR);
    render();
    changed();
  }
  function toggle(key: string, check: HTMLInputElement): void {
    const tab = tabs.get(key)!;
    if (check.checked) chosen[tab].push(key);
    else if (chosen[tab].length > 1) chosen[tab] = chosen[tab].filter((one) => one !== key);
    else {
      // Never an empty part: its last row stays, the bar under the screen says why.
      check.checked = true;
      if (refused) refused.remove();
      refused = addLine(results, "warn", texts[parts[tab].last]);
      changed();
      return;
    }
    commit();
  }
  function buildGroups(rows: CatRow[], groupsBox: HTMLElement): void {
    const of = new Map<string, CatRow[]>();
    const known = new Set(GROUPS.map(([group]) => group));
    for (const row of rows) {
      const group = known.has(row.group) ? row.group : "other";
      of.set(group, [...(of.get(group) || []), row]);
    }
    for (const [group, text] of GROUPS) {
      const list = of.get(group);
      if (!list) continue;
      const details = doc.createElement("details"),
        title = doc.createElement("summary");
      details.className = "crg";
      details.appendChild(title);
      for (const row of list) {
        const [label, check] = checkRow(row.key);
        boxes.set(row.key, check);
        details.appendChild(label);
      }
      titles.push([title, texts[text], list.map((row) => row.key)]);
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
      for (const row of rows) {
        names.set(row.key, row.name);
        tabs.set(row.key, row.tab);
      }
      if (stale) show(status, "warn", [texts.rowsStale]);
      else status.textContent = "";
      for (const tab of TABS) {
        chosen[tab] = chosenKeys(rows, field.value, tab);
        buildGroups(
          rows.filter((row) => row.tab === tab),
          parts[tab].groups,
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
  doc.defaultView!.addEventListener("hashchange", opened);
  opened();
}
