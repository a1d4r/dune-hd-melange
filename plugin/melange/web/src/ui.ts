// Small helpers of the page: result lines, aria-busy.

// The classes of result lines (settings.css).
export type LineClass = "hint" | "ok" | "warn" | "err";

// Replaces the content of el with lines, a <p class="cls"> each.
export function show(el: HTMLElement, cls: LineClass, lines: string[]): void {
  el.textContent = "";
  for (const line of lines) addLine(el, cls, line);
}

export function addLine(el: HTMLElement, cls: LineClass, text: string): HTMLParagraphElement {
  const line = el.ownerDocument.createElement("p");
  setLine(line, cls, text);
  el.appendChild(line);
  return line;
}

// A line of addLine() replaced.
export function setLine(line: HTMLElement, cls: LineClass, text: string): void {
  line.className = cls;
  line.textContent = text;
}

// make(), else undefined and the line badChars in out: a field it encodes has a lone surrogate (encodeURIComponent
// throws URIError).
export function encodedOr<T>(out: HTMLElement, badChars: string, make: () => T): T | undefined {
  try {
    return make();
  } catch (e) {
    if (!(e instanceof URIError)) throw e;
    show(out, "err", [badChars]);
    return undefined;
  }
}

export function setBusy(el: HTMLElement, on: boolean): void {
  if (on) el.setAttribute("aria-busy", "true");
  else el.removeAttribute("aria-busy");
}
