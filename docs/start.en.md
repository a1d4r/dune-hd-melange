# Quick start

Melange is a movie and series catalog for Dune HD. Pick a movie, and Melange finds it in torrents and plays it in the Dune player right away, without downloading. [← Contents](README.en.md)

We will install Melange and TorrServe (the Dune plays torrents through it) and open the catalog. TorrServe is an app, and TorrServer runs inside it: Melange messages use both names.

<img src="../screenshots/overview-en.png" alt="Diagram: you pick a movie in the Melange catalog, Melange searches torrents for releases, you pick a release, the movie plays in the Dune player via TorrServe or Debrid" width="100%">

You need: a Dune HD on Android with firmware r22 or newer, r24 or r25 recommended. How firmware versions differ: [Known limitations](limitations.en.md#devices-and-firmware).

If you have a Real-Debrid or TorBox subscription, go to the [Debrid](debrid.en.md) chapter after installing Melange: you don't need TorrServe then.

You can install with the remote [via Dune BackUp](#via-dune-backup) or [manually](#manually), with files from GitHub.

## Via Dune BackUp

1. If Dune BackUp is not installed yet, install it: Dune Store → "Apps" → "Community apps" → "Ignore and continue", move to "Dune BackUp", press **ENTER** and install the app.

   <img src="../screenshots/start-store-community.jpg" alt="Dune Store, Apps: focus on Community apps" width="100%">

   <img src="../screenshots/start-store-dunebackup.jpg" alt="Dune Store, Community apps: focus on Dune BackUp" width="100%">

2. Open Dune BackUp → "Plugin collection for Dune HD".

   <img src="../screenshots/start-dunebackup-menu.jpg" alt="Dune BackUp: focus on Plugin collection for Dune HD" width="100%">

3. Move to the "Plugin" field and press **ENTER**: a list opens. In the Movies section, choose "Melange" and press "Install plugin". If Melange is already installed, answer "Yes" to "Replace the app?".

   <img src="../screenshots/start-dunebackup.jpg" alt="Dune BackUp, Plugin collection: the Movies section, focus on Melange" width="100%">

4. Open Melange in "Apps". The Dune offers a new version: press "Update".

   <img src="../screenshots/start-update.jpg" alt="App update: Melange 0.57.0 → 0.59.0, focus on Update" width="100%">

5. TorrServe is installed in the same place: Dune BackUp → "Android app collection" → "App" → the top "TorrServe MatriX" in the For torrents section → "Install app". Any MatriX version will do.

   <img src="../screenshots/start-android-torrserve.jpg" alt="Android app collection, App list: the For torrents section, focus on TorrServe MatriX 140" width="100%">

Next: [first setup](#first-setup).

## Manually

1. Download `dune_plugin_melange_<version>.zip` from the latest release (Assets section): https://github.com/a1d4r/dune-hd-melange/releases. For TorrServe, download `TorrServe_MatriX.145.Client.apk` (Assets section): https://github.com/YouROK/TorrServe/releases/tag/MatriX.145.Client. Any MatriX version will do.

2. Copy both files to the Dune any way you like: from a phone via Send Files, over the network or on a USB stick. Don't unpack the zip.

3. On the Dune, open **"Files"**, find the zip in the folder you copied it to, move to it and press **ENTER**.

   <img src="../screenshots/start-files-zip.jpg" alt="Files → Internal storage: focus on dune_plugin_melange_0.59.0.zip" width="100%">

4. Wait for "Installed successfully" and press "OK".

   <img src="../screenshots/start-install.jpg" alt="App installation: Installed successfully, focus on OK" width="100%">

5. Install the TorrServe apk the same way, via **"Files"**.

## First setup

1. Open TorrServe: "Apps" → ANDROID tab → TorrServe. If it asks to install the server, install it. In TorrServe settings, turn on **"Start server on boot"** and, below it, **"Run server as root"**.

   <img src="../screenshots/start-torrserve-autostart.jpg" alt="TorrServe settings: Start server on boot and Run server as root are on" width="100%">

2. Open **"Apps"** → **"Melange"**.

   <img src="../screenshots/start-apps-melange.jpg" alt="Apps: focus on the Melange icon" width="100%">

3. Press **"Quick setup: torrents"**.

   <img src="../screenshots/setup-dune.jpg" alt="Melange settings screen, focus on Quick setup: torrents" width="100%">

   "AIOStreams - Melange config: not set" is expected. AIOStreams is the server that searches for releases, and the config is your settings on it. Melange creates it by itself.

4. The Melange catalog opens: you can pick a movie.

   <img src="../screenshots/catalog.jpg" alt="Melange catalog: Movies and Series tabs at the top, clock, heart, magnifier and gear on the right, Favorites, Trending and other rows below" width="100%">

   If you see "TorrServer does not answer", open TorrServe (it starts the server), then Melange again. If that does not help, check step 1.

The Dune offers new Melange versions by itself.

Next: [Watching](watch.en.md) · [Contents](README.en.md)

Bugs: Issues at https://github.com/a1d4r/dune-hd-melange/issues
