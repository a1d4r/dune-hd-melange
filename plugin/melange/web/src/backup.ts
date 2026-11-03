// The backup of the settings (#bak): "Save to a file" is a link of PHP (a=export, an attachment). "Load from a file"
// reads the file here (FileReader: no upload, $_FILES is not proven on the Dune), asks, posts its text (a=import) and
// opens the page again with the values imported - by the link of the file if it holds another one (web_token, 0.51.0).
// No FileReader: the box stays hidden.

import { type Api, importBody, isObject } from "./api";
import { byId, DATA, ID, type Page } from "./dom";
import { failureText } from "./i18n";
import type { Lock } from "./lock";
import { show } from "./ui";

// The file holds a token of the page (as the plugin makes it) other than that of this one (action: settings?t=<token>):
// the import writes it, the page goes to it.
function otherLink(text: string, action: string | null): boolean {
  let json: unknown = null;
  try {
    json = JSON.parse(text);
  } catch (e) {
    return false;
  }
  const token = isObject(json) ? json.web_token : null;
  return typeof token === "string" && /^[0-9a-f]{32}$/.test(token) && action !== "settings?t=" + token;
}

export function setupBackup(page: Page, api: Api, lock: Lock): void {
  const { doc, texts } = page;
  if (typeof FileReader !== "function") return;
  const button = byId<HTMLButtonElement>(doc, ID.backupButton),
    input = byId<HTMLInputElement>(doc, ID.backupFile),
    results = byId(doc, ID.backupResult);
  const max = Number(input.getAttribute(DATA.max)) || 0;
  byId(doc, ID.backupImport).hidden = false;
  lock.hold(button);

  function send(text: string): void {
    const body = importBody(text);
    // The limit is of the body (post_max_size of the Dune): over it, not sent.
    if (max && body.length > max) {
      show(results, "err", [texts.importTooBig]);
      return;
    }
    const question = otherLink(text, page.form.getAttribute("action")) ? texts.importConfirmLink : texts.importConfirm;
    // A save may have started while the file was read.
    if (!lock.free(button) || !confirm(question)) return;
    lock.run(button, true);
    show(results, "hint", [texts.importing]);
    const fail = (msg: string) => {
      lock.run(button, false);
      show(results, "err", [msg]);
    };
    api.importSettings(body).then((result) => {
      if (result.kind !== "data") return fail(failureText(texts, result));
      const answer = result.data;
      if (!answer.ok) return fail(answer.msg);
      // Written: the page again (by the link of the file if it was written); the button stays busy till it goes.
      location.replace(answer.next);
    });
  }
  button.addEventListener("click", () => {
    if (!lock.free(button)) return;
    // The same file chosen again is a change too.
    input.value = "";
    input.click();
  });
  input.addEventListener("change", () => {
    const file = input.files && input.files[0];
    if (!file) return;
    results.textContent = "";
    // Too big for any body (its encoding is never shorter): not read at all.
    if (max && file.size > max) {
      show(results, "err", [texts.importTooBig]);
      return;
    }
    const reader = new FileReader();
    reader.onload = () => send(typeof reader.result === "string" ? reader.result : "");
    reader.onerror = () => show(results, "err", [texts.importUnread]);
    reader.readAsText(file);
  });
}
