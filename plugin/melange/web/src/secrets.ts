// The secret fields (class sec): masked, the eye of each shows or masks it.

import { byId, CLS, DATA } from "./dom";

// A secret field (class sec) is masked by -webkit-text-security; Firefox has none, it shows the text: there the
// field is type=password while masked (CSS.supports missing: as before, nothing known).
function masksByCss(): boolean {
  return typeof CSS === "undefined" || typeof CSS.supports !== "function" || CSS.supports("-webkit-text-security", "disc");
}

export function setMasked(field: HTMLInputElement, masked: boolean): void {
  field.classList.toggle(CLS.secret, masked);
  if (!masksByCss()) field.type = masked ? "password" : "text";
}

// Where CSS can't mask: the secret fields type=password, kept away from password managers (a key is no login to
// keep or fill in; not type=password elsewhere for that).
export function maskSecrets(doc: Document): void {
  if (masksByCss()) return;
  doc.querySelectorAll<HTMLInputElement>("input." + CLS.secret).forEach((field) => {
    field.setAttribute("autocomplete", "new-password");
    for (const name of ["data-lpignore", "data-1p-ignore", "data-bwignore"]) field.setAttribute(name, "true");
    field.setAttribute("data-form-type", "other");
    setMasked(field, true);
  });
}

// An eye shows or masks the field of its data-for.
export function bindEyes(doc: Document): void {
  doc.querySelectorAll<HTMLButtonElement>("." + CLS.eye).forEach((eye) => {
    eye.addEventListener("click", () => {
      const field = byId<HTMLInputElement>(doc, eye.getAttribute(DATA.eyeFor)!);
      const masked = !field.classList.contains(CLS.secret);
      setMasked(field, masked);
      eye.classList.toggle(CLS.on, !masked);
    });
  });
}
