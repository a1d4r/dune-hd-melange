# Troubleshooting and the log

What Melange messages mean, how to download the log and where to send it. [← README](../README.en.md)

## Messages when opening the list

| Message | What it means and what to do |
|---|---|
| "No AIOStreams config …" | the selected server has no config: it was not created (server limit, no connection) or another server is selected. In the window, press **Quick setup: torrents**, or on the settings page press **Save and create the config** |
| "The link to your AIOStreams config is not set" | your own config is selected, but there is no link: paste it in **Where to get films** |
| "AIOStreams found no streams" | nothing was found for this movie. With the Melange config, a common cause is that JacRed did not answer the AIOStreams server: choose another JacRed (**Compare speed** in the [JacRed](setup.en.md#jacred) section) or another [AIOStreams server](sources.en.md#aiostreams-server). With your own config, check its filters and services. Why sources sent no releases is shown by the [**Source errors**](setup.en.md#advanced) setting |
| "AIOStreams is not responding" / "AIOStreams returned an error" / "Unexpected reply from AIOStreams" | a problem on the server or network side; try again later, details are in the log |
| "Reply too large …" | the reply is over 6 MB: lower the result limit in the web settings of the AIOStreams config ([the Melange config too](sources.en.md#tuning-the-config-in-aiostreams)) |
| "This title has no IMDb ID" | the card in the Dune catalog has no IMDb ID, so Melange cannot find this movie |
| "The list is outdated, open it again" | open the movie from its card again |

Messages when starting a release are in [Watching](watching.en.md): [via Debrid](watching.en.md#playing-via-debrid) and [via TorrServer](watching.en.md#watch-via-torrserver).

## Common situations

- **Few or no releases.** Compare JacRed servers with **Compare speed** and choose a fast one. With Debrid and the **Russian trackers only** template there are few releases, especially with Real-Debrid alone: try **Everywhere**. On a **no TMDB** server there are fewer series releases: set a TMDB key ([AIOStreams server](sources.en.md#aiostreams-server)).
- **Quick setup did not work** (the Melange server does not answer, is busy or did not create the config): no connection or the server limit. Try again in a minute; if that does not help, set up from a phone.
- **Does not open via TorrServer.** Press **Check** in the [TorrServer](setup.en.md#torrserver) section: the answer points to the cause.
- **Episodes take long to start.** Turn off **Skip intro and credits** in the [Advanced](setup.en.md#advanced) section.
- **The settings page bookmark does not open.** If the Dune's address on the network changed, open the QR code again: **Set up from a phone**. The page opens only from your home network.
- **The link to your own config stopped working** after the config password was changed in AIOStreams: enter the new link.
- **The TV tab is empty.** Most likely Melange was removed while it was chosen for the TV tab. On the home screen, move to «ТВ», press POP UP → «Настройка раздела ТВ» (TV section setup) and choose DUNE-HD.TV, or Melange again if you reinstalled it ([Melange in the TV tab](catalog.en.md#melange-in-the-tv-tab)).
- **Real-Debrid refuses Not cached releases**: see [Known limitations](limitations.en.md).

## The log

When you need it: to figure out what went wrong, or to attach to an issue.

### How to download

On the settings page, press **Download the log**. The log starts over after the Dune reboots, so download it **before** a reboot.

### What's in the log

The plugin version, the Dune model and firmware, which AIOStreams server, JacRed and TorrServer are selected, what Melange did since the Dune was last switched on (which movies were opened, how many releases came, errors) and the end of the Dune shell log.

### What is hidden

The serial number, MAC and IP addresses, names of computers on your home network, keys, passwords, tokens, the addresses of AIOStreams configs and of your own servers (JacRed, AIOStreams, TorrServer, torrent clients), and playback links with Debrid tokens. Other links keep only the server.

### Where to send it

Open an issue on GitHub, https://github.com/a1d4r/dune-hd-melange/issues, describe what you did and what went wrong, and attach the log.

Before attaching the file, look it over: it contains the titles of the movies and episodes you opened, and the shell log also has the names of your favorites shortcuts, the list of installed plugins and apps, and catalog movie IDs.
