# Debrid

Connect Real-Debrid or TorBox so that movies start right away, with no wait for downloading. [← Contents](README.en.md)

You need: a Real-Debrid or TorBox subscription and a phone on the same network as the Dune.

No subscription yet? Get one through the author's link to support Melange: [Real-Debrid](https://real-debrid.com/?id=20188550), [TorBox](https://torbox.app/subscription?referral=2339fa70-a03e-41f0-a414-9c96f0791bb1).

1. In the Melange catalog, open the plugin settings (the gear on the bar at the top) and choose "Set up from a phone". Scan the QR code with your phone. On the first Melange launch, press "Set up from a phone" on the settings screen (not "Quick setup: torrents"). More about the page: [Settings page on a phone](phone-page.en.md).

   <img src="../screenshots/setup-qr.jpg" alt="Set up from a phone window with a QR code" width="100%">

2. On the page, choose "Debrid" and press "Start".

   <img src="../screenshots/debrid-start.jpg" alt="Where to get films: Debrid is selected, a Start button below it" width="300">

3. Open the service website on the phone, sign in and copy the key. Real-Debrid: https://real-debrid.com/apitoken; TorBox: https://torbox.app/settings.

   <img src="../screenshots/debrid-site-rd.jpg" alt="Real-Debrid: API Private Token block with the key in the field" width="100%">

   <img src="../screenshots/debrid-site-torbox.jpg" alt="TorBox: API Key block with a Copy API Key button" width="100%">

4. At the top, choose your service, Real-Debrid or TorBox, paste the key into the field and press "Check". A green line "the key is valid, premium" should appear.

   <img src="../screenshots/debrid-key.jpg" alt="Real-Debrid key field, below it a green line: Real-Debrid: the key is valid, premium until 01.03.2027" width="300">

5. If you have both subscriptions, press "+ Add TorBox" (or "+ Add Real-Debrid") and paste the second key. In "Main Debrid", choose the service to start a release through when both have it.

   <img src="../screenshots/debrid-both.jpg" alt="Both key fields, below them the highlighted Main Debrid block with Real-Debrid and TorBox options" width="300">

6. In "Where to look for releases", leave "Everywhere" and press "Save and create the config".

   <img src="../screenshots/debrid-where.jpg" alt="Where to look for releases: Everywhere is selected, a Save and create the config button at the bottom" width="300">

   "All set to watch" appears, the QR code window on the Dune closes by itself, and the Melange catalog opens.

   <img src="../screenshots/debrid-ready.jpg" alt="Home screen of the page: green line All set to watch" width="300">

7. Open a movie on the Dune. A lightning bolt on a release means it is cached (already downloaded by the Debrid service) and starts right away.

   <img src="../screenshots/film-list.jpg" alt="Release list for Dune: Part Two: releases with a lightning icon, Cached in Real-Debrid and TorBox on the left" width="100%">

8. A release without a lightning bolt can play via TorrServer: move to it, press **POP UP** and choose "Watch via TorrServer". This needs TorrServe on the Dune. To install it, see [Quick start](start.en.md#via-dune-backup), step 5; to set it up, [First setup](start.en.md#first-setup), step 1.

   <img src="../screenshots/debrid-menu-torrserver.jpg" alt="Release list for Dune: Part Two, menu open, focus on Watch via TorrServer" width="100%">

## If it didn't work

- "the key is not accepted": copy the key again, in full, without spaces.
- "the key is valid, but no premium": renew the subscription on the service website.
- A release doesn't start or says "Not cached": see [Known limitations](limitations.en.md) and [Troubleshooting](troubleshooting.en.md).

Next: [Torrents and TorrServer](torrserver.en.md) · [Contents](README.en.md)
