// The blocks of qBittorrent servers (details.srv): blank ones hidden, "Add", "Duplicate", "Check".

import type { Advanced } from "./advanced";
import type { Api } from "./api";
import { bindCheck } from "./checks";
import { byId, CLS, ID, type Page, query, SERVER_BLOCK, SERVER_CHECK_FIELD, SERVER_FIELD } from "./dom";
import type { Lock } from "./lock";

type Field = HTMLInputElement | HTMLSelectElement;

export interface Servers {
  // blockNo: 1-based, as in s<n>_<key>.
  has(blockNo: number): boolean;
  // The block shown and opened, and Advanced around it.
  reveal(blockNo: number): HTMLDetailsElement;
}

// The fields s<n>_<key> of a block by <key>, and its <n>.
function serverFields(block: Element): { blockNo: string; byKey: Record<string, Field> } {
  const found: { blockNo: string; byKey: Record<string, Field> } = { blockNo: "", byKey: {} };
  const fields = block.querySelectorAll<Field>("input,select");
  for (let i = 0; i < fields.length; i++) {
    const match = SERVER_FIELD.exec(fields[i]!.name);
    if (match) {
      found.byKey[match[2]!] = fields[i]!;
      found.blockNo = match[1]!;
    }
  }
  return found;
}

// Every field typed into is empty (the choice of the cache is not typed: it has a value always). By name, not by
// type: a secret field may be type=password (secrets.maskSecrets).
function isBlank(block: Element): boolean {
  const byKey = serverFields(block).byKey;
  for (const key in byKey) if (byKey[key]!.tagName === "INPUT" && byKey[key]!.value !== "") return false;
  return true;
}

export function setupServers(page: Page, advanced: Advanced): Servers {
  const { form, texts } = page;
  const blocks = form.querySelectorAll<HTMLDetailsElement>(SERVER_BLOCK);
  const addButton = byId(page.doc, ID.addServer);
  function nextHidden(): HTMLDetailsElement | null {
    for (let i = 0; i < blocks.length; i++) if (blocks[i]!.style.display === "none") return blocks[i]!;
    return null;
  }
  // No hidden block to copy into: disabled, and the title tells why.
  function renderDupButtons(): void {
    const full = !nextHidden();
    form.querySelectorAll<HTMLButtonElement>("." + CLS.serverDup).forEach((button) => {
      button.disabled = full;
      if (full) button.title = texts.serversFull;
      else button.removeAttribute("title");
    });
  }
  function reveal(block: HTMLDetailsElement): void {
    block.style.display = "";
    block.open = true;
    addButton.hidden = !nextHidden();
    renderDupButtons();
  }
  // Into the next hidden block: its name gets " (copy)", cut to 40 without splitting a surrogate pair.
  function duplicate(block: HTMLElement): void {
    const target = nextHidden();
    if (!target || isBlank(block)) return;
    const from = serverFields(block),
      to = serverFields(target),
      suffix = texts.copySuffix;
    for (const key in from.byKey) if (to.byKey[key]) to.byKey[key].value = from.byKey[key]!.value;
    const name = from.byKey.name!.value;
    let copyName = (name !== "" ? name : texts.server + " " + from.blockNo).slice(0, 40 - suffix.length);
    if (/[\ud800-\udbff]$/.test(copyName)) copyName = copyName.slice(0, -1);
    to.byKey.name!.value = copyName + suffix;
    query(target, "." + CLS.serverResult).textContent = "";
    reveal(target);
    if (target.scrollIntoView) target.scrollIntoView();
    to.byKey.name!.focus();
  }

  for (let i = 0; i < blocks.length; i++) if (isBlank(blocks[i]!)) blocks[i]!.style.display = "none";
  addButton.hidden = !nextHidden();
  addButton.addEventListener("click", () => {
    const block = nextHidden();
    if (!block) return;
    reveal(block);
    const name = serverFields(block).byKey.name;
    if (name) name.focus();
  });
  for (let i = 0; i < blocks.length; i++) {
    const block = blocks[i]!;
    const button = page.doc.createElement("button");
    button.type = "button";
    button.className = CLS.check + " " + CLS.serverDup;
    button.textContent = texts.duplicate;
    block.insertBefore(button, block.querySelector("." + CLS.serverResult));
    button.addEventListener("click", () => duplicate(block));
  }
  renderDupButtons();
  return {
    has: (blockNo: number) => !!blocks[blockNo - 1],
    reveal: (blockNo: number) => {
      const block = blocks[blockNo - 1]!;
      advanced.open();
      reveal(block);
      return block;
    },
  };
}

// "Check" of a block: posts its url, user, pass, category, tags.
export function setupServerChecks(page: Page, api: Api, lock: Lock): void {
  const blocks = page.form.querySelectorAll(SERVER_BLOCK);
  for (let i = 0; i < blocks.length; i++) {
    const block = blocks[i]!,
      button = query<HTMLButtonElement>(block, "." + CLS.serverCheck);
    const fields = () => {
      const pairs: string[] = [];
      const inputs = block.querySelectorAll<HTMLInputElement>("input");
      for (let k = 0; k < inputs.length; k++) {
        const match = SERVER_CHECK_FIELD.exec(inputs[k]!.name);
        if (match) pairs.push(match[1] + "=" + encodeURIComponent(inputs[k]!.value));
      }
      return pairs.join("&");
    };
    bindCheck(
      lock,
      page.texts,
      button,
      () => query(block, "." + CLS.serverResult),
      () => api.srvCheck(fields()),
      (check) => [check.ok ? "ok" : "err", check.lines.length ? check.lines : [check.msg]],
    );
  }
}
