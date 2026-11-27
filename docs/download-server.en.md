# Your own torrent client

Send a release from the Dune to qBittorrent, Transmission, Synology Download Station or rdt-client on a NAS or a computer. [← Contents](README.en.md)

You need: a torrent client with its web UI turned on.

1. On the settings page ([Settings page on a phone](phone-page.en.md)), open "Download to your server". Choose the client, enter a name, the web UI address (`http://host:port`; Transmission usually uses port 9091), the login and the password. Press "Check": a green line "OK: logged in · version …" should appear.

   For Synology Download Station the address is `http://NAS:5000`; https with the factory Synology certificate will not work. In "Download folder: shared folder/subfolder" you can enter a folder that already exists on the NAS, for example `video/Torrents`. If you leave the field empty, downloads go to the default folder of Download Station.

   <img src="../screenshots/download-server.jpg" alt="Download to your server: Server 1 qBittorrent, address http://192.168.1.10:8080, a green line at the bottom: OK: logged in · version v5.0.4" width="300">

2. Press "Save". You will see "Saved. The Dune picks up the settings automatically."

   <img src="../screenshots/phone-saved.jpg" alt="A green line at the top of the page: Saved. The Dune picks up the settings automatically." width="300">

3. On the Dune, in the release list, move to a release and press **POP UP** → "Download to server…".

   <img src="../screenshots/download-server-menu.jpg" alt="Release list for Dune: Part Two, the POP UP menu is open, focus on Download to server…" width="100%">

4. Choose the server. You will see "Added to <name>".

   <img src="../screenshots/download-server-pick.jpg" alt="A window with the list of servers: qBittorrent, rdt-client TB, rdt-client RD, Transmission MacOS; focus on rdt-client TB" width="100%">

   <img src="../screenshots/download-server-added.jpg" alt="Message Added to rdt-client TB with an OK button" width="100%">

If you have several servers, choose in "For the cache of" which one to use for releases from the Debrid cache.

You may not need a separate client. The built-in torrent client of Keenetic routers is Transmission, usually on port 8090. On a TerraMaster NAS you can install qBittorrent or Transmission from the app center.

## If it did not work

- "Check" shows a red line: see [Troubleshooting](troubleshooting.en.md).
- A download was added to Download Station but stays waiting: set the default folder for that user in Download Station ([Troubleshooting](troubleshooting.en.md)).
- The POP UP menu has no "Download to server…": not every release has it, only those where the source sent a torrent. With "No torrents" it is not there at all ([Advanced](advanced-options.en.md)).

Next: [Appearance](appearance.en.md) · [Contents](README.en.md)
