# Known limitations

What Melange cannot do, or does with caveats. [← README](../README.en.md)

## Devices

- You need a Dune HD on Android with firmware r22 or newer. It does not work on Sigma / Linux Dunes and is not guaranteed on models with 1 GB of memory (the list of models is in the [README](../README.en.md#requirements)).
- On r22, the **Continue Watching** row may not show movies from Melange: the firmware added that row for online movies in r24. Not tested.
- The **Skip Intro** and **Next Episode** buttons are shown by the r25 firmware player.
- The film logo in place of the title in the catalog needs r25 firmware; on r24 and older the catalog shows the title as text.
- The Movies and Series sub-items under the TV tab need r24 firmware; on older firmware the rows of both tabs show in the TV tab as one list.
- The plugin interface is in Russian and English, following the Dune's language; the name is "Melange" in both.

## Movies and the release list

- Only movies and series with an IMDb ID: on the Dune card or, from the catalog, on the poster.
- The screen shows at most 100 releases; the rest are not shown. An AIOStreams reply over 6 MB does not open.

## Watch history

- **Not cached** releases and releases with unknown status started via Debrid play with history only if Debrid gave the file link at once. Otherwise (for example, the stream goes through the AIOStreams proxy) they play **without history**: no percentage on the button, no **Continue Watching**. Via TorrServer, with history.
- Series from the Melange catalog without a Dune card play without history. For a movie without a Dune card Melange creates a Dune card itself: it shows in the Dune's user data and stays after Melange is removed. If the Dune card server did not answer at that moment, the movie opens without history: try again later.

## Debrid

- **Real-Debrid and Not cached** (in your own config and in the Melange config). Each start of such a release adds a torrent to your RD account. RD does not serve releases with `WEB-DL` in the name (instead of the movie, a "File was removed…" placeholder or an error), and after several new releases in a row it temporarily refuses all additions. Do not start such releases more than 1–2 times in a row: wait a few minutes.
- For releases from Comet, Melange does not know why Debrid refused (for example, copyright): it just shows "Debrid didn't return the video — pick another release".

## Series

- The next episode plays only from the same release. If the release does not have it (for example, the season is over), Melange does not look for another release by itself: choose one in the list. Via Debrid, episodes play in a row only if the release is cached or Debrid gives the episode's file at once.
- **Continue Watching** counts an episode as watched if you watched it almost to the end: then it starts the next episode, and after the last episode of the series it opens the list of the first one. The card button also counts an episode marked as watched on the Dune.
- If you switched episodes in the player via TorrServer, after Stop the list of the episode you started with opens.
