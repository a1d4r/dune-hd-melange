// "Copy" of the password of the config.

import { byId, CLS, DATA, ID, type Page, query } from "./dom";
import { setMasked } from "./secrets";
import { show } from "./ui";

// Via a hidden textarea, not from the field: its masked text would copy as dots.
function copyByCommand(doc: Document, text: string): boolean {
  const area = doc.createElement("textarea");
  let copied = false;
  area.value = text;
  area.setAttribute("readonly", "");
  area.style.position = "fixed";
  area.style.left = "-9999px";
  area.style.top = "0";
  doc.body.appendChild(area);
  area.focus();
  area.select();
  try {
    area.setSelectionRange(0, area.value.length);
    copied = doc.execCommand("copy");
  } catch (e) {}
  doc.body.removeChild(area);
  return copied;
}

export function setupClipboard(page: Page): void {
  const { doc, texts } = page;
  byId(doc, ID.copyPass).addEventListener("click", () => {
    const pass = byId<HTMLInputElement>(doc, ID.confPass);
    // Not copied: the password is shown to copy by hand.
    function done(copied: boolean): void {
      if (!copied) {
        setMasked(pass, false);
        query(doc, "." + CLS.eye + "[" + DATA.eyeFor + "=" + ID.confPass + "]").classList.add(CLS.on);
      }
      show(byId(doc, ID.copyResult), copied ? "ok" : "warn", [copied ? texts.passCopied : texts.passNotCopied]);
    }
    const byCommand = () => done(copyByCommand(doc, pass.value));
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(pass.value).then(() => done(true), byCommand);
    else byCommand();
  });
}
