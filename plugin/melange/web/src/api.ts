// The endpoints of settings.php (all under the action of the form, ?t=<token>) and their answers.
// An answer is checked here: a caller gets typed data or why there is none.

// ts_check, aio_check, aio_status, key_check. level: "ok" | "warn" | "err" (not all send it), else "".
export type Check = { ok: boolean; level: string; msg: string };
export type SrvCheck = { ok: boolean; msg: string; lines: string[] };
// count: of releases found (n), as shown; badFormat: the address, key or id is of a wrong form (bad: "format").
export type JrSpeed = { ok: boolean; msg: string; count: string; ms: number; badFormat?: true };

export type SaveNote = { level: string; msg: string };
// field: the name= of the form field the error is about (s<n>_url: of server block n; of a pair, the first one), null: none.
export type SaveError = { msg: string; field: string | null };
// What the save does to the config of the chosen server next (aio_sync).
export type ConfHow = "create" | "update" | "reset";
// aio: "" - nothing (another value sent is taken as that).
export type Save = { ok: true; next: string; aio: ConfHow | ""; notes: SaveNote[] } | { ok: false; errors: SaveError[] };

export type ServerConf = { cfg: string; login: string; pass: string; tpl: string | null };
// field: of a failure, as of a SaveError.
export type Sync = { ok: boolean; msg: string; skip: boolean; base: string | null; conf: ServerConf | null; field: string | null };

// http: no answer of the endpoint (not JSON, not its shape) with this status; timeout (after seconds), net: no answer at all.
export type Failure = { kind: "http"; status: number } | { kind: "timeout"; seconds: number } | { kind: "net" };
export type Result<T> = { kind: "data"; data: T } | Failure;

type Json = { [key: string]: unknown };
type Parse<T> = (json: Json) => T | null;

// Arrays pass too: then their fields are missing.
export function isObject(value: unknown): value is Json {
  return typeof value === "object" && value !== null;
}

function parseCheck(json: Json): Check | null {
  if (typeof json.ok !== "boolean") return null;
  return { ok: json.ok, level: typeof json.level === "string" ? json.level : "", msg: String(json.msg) };
}

function parseSrvCheck(json: Json): SrvCheck | null {
  if (typeof json.ok !== "boolean") return null;
  return { ok: json.ok, msg: String(json.msg), lines: Array.isArray(json.lines) ? json.lines.map((line) => String(line)) : [] };
}

function parseJrSpeed(json: Json): JrSpeed | null {
  if (typeof json.ok !== "boolean") return null;
  const speed: JrSpeed = { ok: json.ok, msg: String(json.msg), count: String(json.n), ms: Number(json.ms) };
  if (json.bad === "format") speed.badFormat = true;
  return speed;
}

// The items of a list that are objects, each through parse (others skipped).
const parseItems = <T>(list: unknown, parse: (item: Json) => T): T[] => (Array.isArray(list) ? list.filter(isObject).map(parse) : []);
const parseNote = (note: Json): SaveNote => ({ level: typeof note.level === "string" ? note.level : "", msg: String(note.msg) });
const parseSaveError = (error: Json): SaveError => ({ msg: String(error.msg), field: typeof error.field === "string" ? error.field : null });

const isConfHow = (aio: unknown): aio is ConfHow => aio === "create" || aio === "update" || aio === "reset";

function parseSave(json: Json): Save | null {
  if (json.ok === true && typeof json.next === "string")
    return { ok: true, next: json.next, aio: isConfHow(json.aio) ? json.aio : "", notes: parseItems(json.notes, parseNote) };
  const errors = parseItems(json.errors, parseSaveError);
  return errors.length ? { ok: false, errors: errors } : null;
}

function parseSync(json: Json): Sync | null {
  if (typeof json.ok !== "boolean") return null;
  const conf = json.conf;
  return {
    ok: json.ok,
    msg: String(json.msg),
    skip: !!json.skip,
    base: typeof json.base === "string" ? json.base : null,
    conf: isObject(conf)
      ? { cfg: String(conf.cfg), login: String(conf.login), pass: String(conf.pass), tpl: conf.tpl ? String(conf.tpl) : null }
      : null,
    field: typeof json.field === "string" ? json.field : null,
  };
}

// One request, the body is a form (null: none). No AbortController: no timeout.
function request<T>(method: string, url: string, body: string | null, parse: Parse<T>, timeoutMs = 40000): Promise<Result<T>> {
  const ctl = typeof AbortController === "function" ? new AbortController() : null;
  const timer = ctl ? setTimeout(() => ctl.abort(), timeoutMs) : 0;
  const init: RequestInit = { method: method, body: body };
  if (body !== null) init.headers = { "Content-Type": "application/x-www-form-urlencoded" };
  if (ctl) init.signal = ctl.signal;
  return fetch(url, init)
    .then((res) =>
      res.text().then((text): Result<T> => {
        let json: unknown = null;
        try {
          json = JSON.parse(text);
        } catch (e) {}
        const data = isObject(json) ? parse(json) : null;
        return data !== null ? { kind: "data", data: data } : { kind: "http", status: res.status };
      }),
    )
    .then(
      (result) => {
        clearTimeout(timer);
        return result;
      },
      (): Result<T> => {
        clearTimeout(timer);
        return ctl && ctl.signal.aborted ? { kind: "timeout", seconds: timeoutMs / 1000 } : { kind: "net" };
      },
    );
}

// What the page is told of the requests.
export interface ApiHooks {
  // true: a save or aio_sync went out (none was in flight), false: the last one came back.
  onSaving(on: boolean): void;
  // An answer 403: the link is of an old token, every next request gets 403 too.
  onExpired(): void;
}

// base: the action of the form, settings?t=<token>.
export function createApi(base: string | null, hooks: ApiHooks) {
  // Saves and aio_sync in flight now.
  let inFlight = 0;
  function call<T>(method: string, query: string, body: string | null, parse: Parse<T>, timeoutMs?: number): Promise<Result<T>> {
    return request(method, base + query, body, parse, timeoutMs).then((result) => {
      if (result.kind === "http" && result.status === 403) hooks.onExpired();
      return result;
    });
  }
  const post = <T>(query: string, body: string, parse: Parse<T>) => call("POST", query, body, parse);
  function save<T>(query: string, body: string, parse: Parse<T>, timeoutMs?: number): Promise<Result<T>> {
    if (!inFlight++) hooks.onSaving(true);
    return call("POST", query, body, parse, timeoutMs).then((result) => {
      if (!--inFlight) hooks.onSaving(false);
      return result;
    });
  }
  return {
    tsCheck: (ts: string) => call("GET", "&a=ts_check&ts=" + encodeURIComponent(ts), null, parseCheck),
    aioCheck: (manifest: string) => post("&a=aio_check&ajax=1", "manifest=" + encodeURIComponent(manifest), parseCheck),
    aioStatus: (url: string) => post("&a=aio_status&ajax=1", "url=" + encodeURIComponent(url), parseCheck),
    keyCheck: (what: "rd" | "tb" | "tmdb", key: string) =>
      post("&a=key_check&ajax=1", "what=" + what + "&key=" + encodeURIComponent(key), parseCheck),
    // fields: url=…&user=…&… of a server block.
    srvCheck: (fields: string) => post("&a=srv_check&ajax=1", fields, parseSrvCheck),
    // target: b=<id> or b=&url=…&key=…; probe: 0..2.
    jrSpeed: (target: string, probe: number) => post("&a=jr_speed&ajax=1", target + "&q=" + probe, parseJrSpeed),
    save: (form: string) => save("&ajax=1", form, parseSave),
    aioSync: (reset: boolean) => save("&a=aio_sync&ajax=1", reset ? "reset=1" : "", parseSync, 60000),
  };
}

export type Api = ReturnType<typeof createApi>;
