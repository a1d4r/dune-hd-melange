// The table of "Compare all" of JacRed: a row a JacRed (name, available, time, releases), "Choose" under the name
// checks its radio of the choice (no column of its own: the table fits a phone of 320 px).

import { CLS, JACRED_RADIOS, type Page } from "./dom";
import type { Lock } from "./lock";

// A JacRed of the choice: its name in the table and the value of its radio (its id, "own").
export interface Choice {
  name: string;
  value: string;
}

export interface JacredTable {
  // The cells of row index after the name: available (or how the probes go), time, releases.
  set(index: number, cells: string[]): void;
}

// In out, a row a choice ("Checking…"). Choose: disabled while a save goes and after a 403.
export function createTable(page: Page, lock: Lock, out: HTMLElement, choices: Choice[]): JacredTable {
  const { doc, form, texts } = page;
  // As a click on the radio: the handlers of the choice follow (the note under it).
  function choose(value: string): void {
    const radios = form.querySelectorAll<HTMLInputElement>(JACRED_RADIOS);
    for (let i = 0; i < radios.length; i++) if (radios[i]!.value === value) radios[i]!.click();
  }
  function chooseButton(value: string): HTMLButtonElement {
    const button = doc.createElement("button");
    button.type = "button";
    button.className = CLS.check + " " + CLS.jacredChoose;
    button.textContent = texts.choose;
    button.addEventListener("click", () => {
      if (lock.free(button)) choose(value);
    });
    return button;
  }

  const table = doc.createElement("table"),
    head = table.insertRow(-1);
  for (const title of [texts.colName, texts.colAvailable, texts.colTime, texts.releases])
    head.appendChild(doc.createElement("th")).textContent = title;
  const buttons: HTMLButtonElement[] = [];
  const rows = choices.map((choice) => {
    const row = table.insertRow(-1);
    for (let c = 0; c < 4; c++) row.insertCell(-1);
    row.cells[0]!.textContent = choice.name;
    row.cells[1]!.textContent = texts.checking;
    buttons.push(row.cells[0]!.appendChild(chooseButton(choice.value)));
    return row;
  });
  out.textContent = "";
  out.appendChild(table);
  // On the page now: the lock lets go of those off it.
  buttons.forEach((button) => lock.hold(button));
  return {
    set(index: number, cells: string[]): void {
      cells.forEach((text, i) => (rows[index]!.cells[i + 1]!.textContent = text));
    },
  };
}
