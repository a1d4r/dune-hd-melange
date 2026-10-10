// The lock of the page: which buttons of requests may go now.

import type { ApiHooks } from "./api";
import { byId, CLS, ID, type Page, query } from "./dom";
import { setBusy, show } from "./ui";

// What may go on the page: no check while a save goes (a save stops "Compare all" instead: Save is never held up
// by a check), nothing after an answer 403 (the token of the link is not the one of the Dune any more).
export interface Lock extends ApiHooks {
  // Holds a button that may not go while a save goes: a check, or Choose of "Compare all" (it sends nothing, but
  // changes the choice under a save). Disabled while its request goes (run), while a save goes and after a 403.
  // One taken off the page (a row of an old table of "Compare all") is let go.
  hold(button: HTMLButtonElement): void;
  run(button: HTMLButtonElement, on: boolean): void;
  saving(): boolean;
  // A save goes or the link expired: no check or save starts.
  blocked(): boolean;
  // The count of saves (and aio_sync) started so far: the probes of JacRed stop once it changed since their start.
  saves(): number;
  // An answer 403 came: every next request gets 403 too.
  expired(): boolean;
  // A click on the button may start it: nothing blocked, its request not going.
  free(button: HTMLButtonElement): boolean;
}

// The save buttons (Save, Reset to the template) disabled while a save goes; after a 403 every control of the form
// disabled and one banner on top (#msg).
export function createLock(page: Page): Lock {
  const { doc, form, texts } = page;
  const saveButton = query<HTMLButtonElement>(form, "." + CLS.save),
    resetButton = byId<HTMLButtonElement>(doc, ID.resetConf);
  const held = new Set<HTMLButtonElement>(),
    running = new Set<HTMLButtonElement>();
  let saving = false,
    expired = false,
    saves = 0;
  function render(): void {
    held.forEach((button) => {
      if (!button.isConnected) held.delete(button);
      else button.disabled = expired || saving || running.has(button);
    });
    saveButton.disabled = resetButton.disabled = expired || saving;
    setBusy(saveButton, saving);
  }
  return {
    hold(button: HTMLButtonElement): void {
      held.add(button);
      render();
    },
    run(button: HTMLButtonElement, on: boolean): void {
      if (on) running.add(button);
      else running.delete(button);
      setBusy(button, on);
      render();
    },
    saving: () => saving,
    blocked: () => saving || expired,
    saves: () => saves,
    expired: () => expired,
    free: (button: HTMLButtonElement) => !saving && !expired && !running.has(button),
    onSaving(on: boolean): void {
      if (on) saves++;
      saving = on;
      render();
    },
    onExpired(): void {
      if (expired) return;
      expired = true;
      const controls = form.elements;
      for (let i = 0; i < controls.length; i++) (controls[i] as HTMLButtonElement).disabled = true;
      show(byId(doc, ID.message), "err", [texts.linkExpired]);
      render();
    },
  };
}
