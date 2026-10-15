// What the page knows of the AIOStreams configs: of each server in each mode (a config per mode), and the template for
// a new one. From #init (confs, tplNew); a save and aio_sync change them here.

import { isObject, type ServerConf } from "./api";

// url: of own server only, the address the config is of.
export interface KnownConf {
  cfg: string;
  login: string | null;
  pass: string | null;
  tpl: string | null;
  url: string | null;
}

export interface Confs {
  // server: the value of its option ("own" for own one), mode: VALUE.debrid or VALUE.p2p; null: no config.
  known(server: string, mode: string): KnownConf | null;
  // After aio_sync; url: own server (else null).
  store(server: string, mode: string, conf: ServerConf | null, url: string | null): void;
  // null: none in #init.
  templateForNew(): string | null;
  storeTemplateForNew(value: string): void;
}

const text = (value: unknown): string | null => (typeof value === "string" ? value : null);

// A conf of #init.confs; null: not one (no cfg).
function parseConf(json: unknown): KnownConf | null {
  if (!isObject(json) || typeof json.cfg !== "string" || json.cfg === "") return null;
  return { cfg: json.cfg, login: text(json.login), pass: text(json.pass), tpl: text(json.tpl) || null, url: text(json.url) };
}

// confs: {<mode>: {<value of an option>: {cfg, login, pass, tpl, url?}}}, tplNew: a string (all of #init).
export function createConfs(confs: unknown, tplNew: unknown): Confs {
  // By "<mode> <server>".
  const byServer = new Map<string, KnownConf>();
  let forNew = text(tplNew);
  if (isObject(confs) && !Array.isArray(confs))
    for (const mode of Object.keys(confs)) {
      const ofMode = confs[mode];
      if (isObject(ofMode) && !Array.isArray(ofMode))
        for (const server of Object.keys(ofMode)) {
          const conf = parseConf(ofMode[server]);
          if (conf) byServer.set(mode + " " + server, conf);
        }
    }
  return {
    known: (server: string, mode: string) => byServer.get(mode + " " + server) || null,
    store(server: string, mode: string, conf: ServerConf | null, url: string | null): void {
      // No cfg: no config, as one without it in #init.
      if (conf && conf.cfg)
        byServer.set(mode + " " + server, { cfg: conf.cfg, login: conf.login, pass: conf.pass, tpl: conf.tpl || null, url: url });
      else byServer.delete(mode + " " + server);
    },
    templateForNew: () => forNew,
    storeTemplateForNew(value: string): void {
      forNew = value;
    },
  };
}
