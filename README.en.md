[Русский](README.md) | **English**

# Melange for Dune HD

Melange turns your Dune into an online cinema. Movie and series collections like on a streaming service: trending, new digital releases, Kinopoisk top, genres, plus search. Pick a movie, and Melange finds releases, shows their quality and voice-overs, and plays it in the Dune's built-in player. Series play episode after episode; stop, and later you pick up where you left off. Melange also plugs into the Dune's own **Movies** catalog: a movie or series card gets a **Melange** item in its **Play...** menu.

Releases are found by AIOStreams, a service that queries torrent search engines (JacRed for Russian trackers, Torrentio and others) and returns one combined list; what to search is set by the AIOStreams config. You can watch through Debrid, a paid service that downloads torrents on its side and serves them as ordinary video, or for free through TorrServer on the Dune (the TorrServe app).

What it does:
- its own movie and series catalog: rows of collections (trending, new releases, Russian, genres, streaming services and more; you choose which to show) and search;
- a list of up to 100 releases with details: resolution, source, HDR / Dolby Vision, audio, size, languages, voice-overs, whether the release is cached in Debrid; sorting with the colored remote buttons;
- Dune history: the percentage on the **Melange [NN%]** button, the **Continue Watching** row, resuming from the same spot;
- series: the episodes of a season play in a row on the same release, and you flip episodes right in the list with ← / →;
- Debrid (Real-Debrid or TorBox) or, without a subscription, torrents via TorrServer;
- studio voice-overs (dubbing studios), from the release name and its tracks, plus tracks and voice-overs from JacRed;
- setup from a phone via a QR code, with **Download the log** on the same page.

<img src="screenshots/catalog.jpg" alt="Melange catalog: rows of collections and posters" width="100%">

<img src="screenshots/film-list.jpg" alt="Left: the release list with Debrid, a lightning bolt on cached releases, details of the selected release" width="49%"> <img src="screenshots/film-list-p2p.jpg" alt="Right: the same movie in Torrents mode, seeder counts in the first column, P2P with 407 seeders on the left" width="49%">

## Requirements

- **A Dune HD on Android with firmware r22 or newer.**
- **Does not work** on Sigma / Linux Dunes (Solo 4K, Duo 4K, Base 3D, TV-303D and older): they have no **Movies** catalog with **Play...** items and no Android apps. On models with 1 GB of memory (SmartBox 4K, SmartBox 4K Plus, Traveler, Neo 4K, 4K Neo, Neo 4K T2) it is not guaranteed to work.
- **A phone on the same home network** as the Dune, for the settings page.
- **A source of releases**, one of three options:

| | Debrid | Torrents | Own AIOStreams config |
|---|---|---|---|
| For whom | you want a quick start and lots of releases | you want to try it without a subscription | you have already tuned AIOStreams for yourself |
| What you need | a Real-Debrid or TorBox subscription and its key | the TorrServe app on the Dune | a link to your config |
| Price | paid subscription | free | as with your config |
| Video start | cached releases start at once | slower, depends on seeders | as with your config |
| AIOStreams config | created by Melange | created by Melange | yours, Melange only reads it |
| Where releases are searched | JacRed, Torrentio, MediaFusion, Comet, StremThru Torz | by default only JacRed (Russian trackers); the **Everywhere** template adds Torrentio, TorrentsDB, Comet, StremThru Torz | as you set it up |

An own config without Debrid (P2P releases) also plays via TorrServer. More on the options and on what Melange does with the config: [Release sources](docs/sources.en.md).

JacRed needs no setup: the default is `jacred.kompotik.com`, Melange's JacRed.

## Installation

1. Download the latest release from https://github.com/a1d4r/dune-hd-melange/releases, the `dune_plugin_melange_<version>.zip` file under Assets. Copy it to a USB stick or to the Dune's internal storage.
2. On the Dune, open **Sources**, find the zip and press ENTER.
3. In about half a minute, a **Melange** item appears in the **Play...** menu of cards, no reboot needed. If a card was open during the install, leave it and open it again.

**Updates.** The Dune checks for a new Melange version on GitHub Pages by itself, after a reboot and when you enter **Setup → Apps → Melange**, and offers to install it. If no update arrives, install the new zip over the old one the same way. Settings and the settings page link are kept.

**Uninstall** from the Dune's plugin list. The plugin's settings are erased with it; to keep them, save a [backup](docs/setup.en.md#backup) first.

## Quick start

1. If you will watch without Debrid, install the TorrServe app (TorrServer MatriX) on the Dune and set it to start when the Dune boots.
2. Open **Apps → Melange**. While nothing is set up, the Melange settings screen opens.
3. Torrents only and no phone at hand: press **Quick setup: torrents**. Melange creates the config itself and opens the catalog. Go to step 6.
4. Otherwise press **Set up from a phone** and scan the QR code: the settings page opens.
5. On the page, choose **where to get films** (Debrid, **Torrents** or your own config), enter the Debrid key or the config link and press **Save and create the config** (**Save** for your own config). The QR window on the TV closes by itself.
6. Open a movie in the Dune **Movies** catalog → **Play...** → **Melange**.
7. Pick a release and press ENTER.

The same path step by step, with screenshots: [Getting started](docs/quick-start.en.md).

## How to watch

- **Pick a movie** in the [Melange catalog](docs/catalog.en.md) (**Apps → Melange**: rows of collections and search) or in the Dune **Movies** catalog. On the card: **Play...** → **Melange**. For a series, first choose an episode via **Seasons**.
- **In the release list:** up / down moves through releases, P+ / P− through pages, ENTER plays, INFO shows release details, ← / → goes to the neighboring episode. The colored buttons change the order: Auto, Seeders (Cached with Debrid), Size, Date.
- **Release status** on the left: **Cached in …** starts playing at once, **Not cached** means it is not in the Debrid cache, **P2P** plays via TorrServer.
- **MENU** opens **Actions**: refresh the list, choose an episode, watch via TorrServer, open in another app, download to your server, settings.
- **Continue watching:** a started movie or episode shows up in the Dune's **Continue Watching** row and opens on the same release from the same spot.

Everything about the list, series, TorrServer and history: [Watching](docs/watching.en.md).

## Troubleshooting

- **"No AIOStreams config — open Melange settings"**: the config was not created or another server is selected. In the window, press **Quick setup: torrents** (torrents only) or open the settings page and press **Save and create the config**.
- **"AIOStreams found no streams"**: with the Melange config, a common cause is that JacRed did not answer the AIOStreams server. Choose another JacRed (the **Compare speed** button in the **JacRed** section) or another AIOStreams server.
- **"AIOStreams is not responding"** and other server errors: try again later.
- **Does not open via TorrServer**: install and start TorrServe on the Dune; **Check** in the **TorrServer** section of the settings page tests the connection.
- Still stuck: download the log on the settings page (**Download the log**) and open an issue: https://github.com/a1d4r/dune-hd-melange/issues

All messages and what to do about them: [Troubleshooting and the log](docs/troubleshooting.en.md).

## Documentation

- [Getting started](docs/quick-start.en.md): from installation to the first movie.
- [Settings](docs/setup.en.md): the settings screen on the Dune and every section of the settings page on the phone.
- [Release sources and the AIOStreams config](docs/sources.en.md): Debrid, torrents, your own config, AIOStreams servers, source profiles, what Melange changes in the config.
- [Watching](docs/watching.en.md): the release list, sorting, series, TorrServer, history.
- [Catalog](docs/catalog.en.md): rows of collections, search, data sources.
- [Troubleshooting and the log](docs/troubleshooting.en.md): error messages, the log, where to report.
- [Known limitations](docs/limitations.en.md)

## License

MIT, see the `LICENSE` file in the repository. Quality badges are from [github.com/kingsizew/badges](https://github.com/kingsizew/badges). Sources of the catalog rows: [Catalog](docs/catalog.en.md#data-sources).
