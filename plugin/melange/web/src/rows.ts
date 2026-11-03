// The screen of the rows of the catalog (#rows): the list comes from the Dune when the screen opens; the rows shown,
// in their order, go to the field cat_rows, a save posts it. The field stays as it is until the user changes something:
// no list, or none of its rows left, and the saved choice is not touched.

import type { Api, CatRow } from "./api";
import { byId, ID, type Page } from "./dom";
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

// As aio_cat_user_rows (catalog.php): the keys of the choice still in the list, else the start rows.
export function chosenKeys(rows: CatRow[], value: string): string[] {
  const known = new Set(rows.map((row) => row.key));
  const chosen = [...new Set(value.split(SEPARATOR))].filter((key) => known.has(key));
  return chosen.length ? chosen : rows.filter((row) => row.home).map((row) => row.key);
}

// changed: the bar follows (a move is no change event of the form).
export function setupRows(page: Page, api: Api, changed: () => void): void {
  const { doc, texts } = page;
  const field = byId<HTMLInputElement>(doc, ID.rowsValue),
    status = byId(doc, ID.rowsStatus),
    retry = byId(doc, ID.rowsRetry),
    box = byId(doc, ID.rowsBox),
    shownBox = byId(doc, ID.rowsChosen),
    groupsBox = byId(doc, ID.rowsGroups),
    results = byId(doc, ID.saveResult);
  const names = new Map<string, string>(),
    // The checkbox of each row in its group, the title and rows of each group.
    boxes = new Map<string, HTMLInputElement>(),
    titles: [HTMLElement, string, string[]][] = [];
  let chosen: string[] = [],
    state: "none" | "going" | "here" = "none",
    // The line of rowsLast under the save button, until a change.
    refused: HTMLElement | null = null;

  // A row with its checkbox, in the list or a group.
  function checkRow(key: string): [HTMLLabelElement, HTMLInputElement] {
    const label = doc.createElement("label"),
      check = doc.createElement("input"),
      name = doc.createElement("span");
    label.className = "cr";
    check.type = "checkbox";
    check.checked = chosen.includes(key);
    check.addEventListener("change", () => toggle(key, check));
    name.textContent = names.get(key)!;
    label.append(check, name);
    return [label, check];
  }
  function moveButton(key: string, text: string, step: number, at: number): HTMLButtonElement {
    const button = doc.createElement("button");
    button.type = "button";
    button.className = "chk";
    button.textContent = step < 0 ? "↑" : "↓";
    button.setAttribute("aria-label", text);
    button.disabled = at + step < 0 || at + step >= chosen.length;
    button.addEventListener("click", () => {
      chosen.splice(at, 1);
      chosen.splice(at + step, 0, key);
      commit();
    });
    return button;
  }
  function render(): void {
    shownBox.textContent = "";
    chosen.forEach((key, at) => {
      const line = doc.createElement("div");
      line.className = "cro";
      line.append(checkRow(key)[0], moveButton(key, texts.rowsUp, -1, at), moveButton(key, texts.rowsDown, 1, at));
      shownBox.appendChild(line);
    });
    boxes.forEach((check, key) => (check.checked = chosen.includes(key)));
    for (const [title, name, keys] of titles) title.textContent = name + " · " + keys.filter((key) => chosen.includes(key)).length + "/" + keys.length;
  }
  function commit(): void {
    if (refused) refused.remove();
    refused = null;
    field.value = chosen.join(SEPARATOR);
    render();
    changed();
  }
  function toggle(key: string, check: HTMLInputElement): void {
    if (check.checked) chosen.push(key);
    else if (chosen.length > 1) chosen = chosen.filter((one) => one !== key);
    else {
      // Never an empty choice: the last row stays, the bar under the screen says why.
      check.checked = true;
      if (refused) refused.remove();
      refused = addLine(results, "warn", texts.rowsLast);
      changed();
      return;
    }
    commit();
  }
  function buildGroups(rows: CatRow[]): void {
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
      for (const row of rows) names.set(row.key, row.name);
      chosen = chosenKeys(rows, field.value);
      if (stale) show(status, "warn", [texts.rowsStale]);
      else status.textContent = "";
      buildGroups(rows);
      render();
      box.hidden = false;
    });
  }
  const opened = () => {
    if (doc.location.hash === SCREEN) load();
  };
  retry.addEventListener("click", load);
  doc.defaultView!.addEventListener("hashchange", opened);
  opened();
}
