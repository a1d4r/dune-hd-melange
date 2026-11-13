# Catalog

Melange's own catalog: rows of collections, search and where the data comes from. [← README](../README.en.md)

## How to open

**Apps → Melange** opens the catalog. While AIOStreams is not set up, the settings screen opens instead (see [Getting started](quick-start.en.md)).

On top of the catalog is a strip: the **Movies** and **Series** tabs and, on the right, a magnifier (search) and a gear (settings); press UP from the first row to get there. The catalog opens on Movies.

Settings from the catalog: the gear on the strip. MENU on a poster or in the panel on the left opens the same **Movies**, **Series**, **Search** and **Settings** as the strip, so you do not have to scroll up to it.

<img src="../screenshots/catalog.jpg" alt="Melange catalog: names of the start rows on the left, rows of posters on the right" width="100%">

## Rows

The catalog has rows of collections with up to 20 posters each; the last tile, **Show all**, opens the whole row (up to 1000 posters, loaded as you scroll). In the catalog rows, a poster can show the IMDb rating of the movie in its top left corner, if it has one: turn it on on the settings page, **Appearance** → **IMDb rating on posters**.

**Start rows:** trending movies and series, new digital releases, this year's series, popular and top rated, and Kinopoisk top ("Топ Кинопоиска"). Row names come from the catalog server in Russian.

### Your own set of rows

On the settings page on your phone, in the **Catalog rows** section, there are about 180 rows in two parts, switched at the top (Movies | Series), each in groups (top, fresh releases, Russian, Russian services, genres, themes, studios, decades, years, streaming, countries, runtime, anime): tick the ones you want (the checkbox in the title of a group turns all its rows on or off at once) and order them with **Up** / **Down**; at least one row must stay in each part. The parts are the tabs of the catalog: rows of movies show on the Movies tab, rows of series and anime on Series. Rows are saved together with the other settings, so set up where to get films first. The new set shows the next time the catalog opens.

You can also set up the rows right in the catalog, as in the Dune's own Movies: in the panel on the left, move to the name of a row or to **Menu** at the top, press MENU and choose **Configure List...**. A menu opens with **Row order** and the groups of the current tab, the same as on the settings page; each group shows how many of its rows are on, for example "Genres 3 of 14". **Row order** lists only the rows that are on: **Move** puts a row anywhere in the list, and unticking a row removes it from the catalog. In the window of a group, tick the rows you want: new ones go to the end of the tab, the others keep their places, and **Reset to Default Values** in the window's MENU brings back the start rows of the group. The order changes in **Row order** only. At least one row must stay on the tab. When you close the window, the catalog redraws with the new set. It is the same choice as on the settings page. If **Navigation panel on the left** is off in the Dune settings, row names cannot be selected, and the rows are set up on the settings page only.

## Logo in place of the title

Above the rows, a movie with a Russian logo shows the logo instead of its title, as streaming services do. If the catalog lags or the cards take long to load, turn it off on the settings page: **Appearance** → **Film logo in place of the title**. The title is then shown as text and the Dune needs half the memory; it takes effect the next time the catalog opens. The logo needs firmware r25 or newer: on r24 and older the catalog shows the title as text whatever you choose.

## Search

The magnifier on the strip on top, MENU → **Search** on the main screen of the catalog, or the search key, if your remote has one. Results come in the **Movies** and **Series** rows. If a row has more than 9 results, **Show all** at the end of it lists everything found, 9 posters a row (on r24 firmware, as a grid). On r25, if only movies or only series are found, the results open that way at once.

<img src="../screenshots/search.jpg" alt="Search results in the Melange catalog: Movies and Series rows, a Show all tile at the end of a row" width="100%">

## ENTER on a poster

- If the title has a card in the Dune **Movies** catalog, that card opens: **Play...** → **Melange**, with watch history as usual.
- If there is no card, for a movie Melange creates its own Dune card, and the rest is as usual: **Play...**, watch history. For a series Melange offers to open the release list right away, from the first episode, but without watch history: no percentage, no **Continue Watching**.
- With neither a card nor an IMDb ID, releases cannot be found.

## Melange in the TV tab

Melange catalog rows can show right on the Dune home screen, in the TV tab. Under it come two sub-items, Movies and Series, which are the catalog tabs; the TV item itself opens Movies. A focused tile shows the same info panel as in the catalog. ENTER opens the film card, RETURN brings you back to the same tile. **Show all** at the end of a row opens the whole row. POP UP on a tile offers **Search**, **Open Melange** and **Settings**. The TV button on the remote opens the Melange catalog.

**How to turn it on.** You choose the tab app by hand: on the home screen, move to TV, press POP UP and choose «TV section Setup» → «Another application...» → Melange. The same choice is in «Settings → Applications → TV → Configure...». The sub-items appear at once. Firmware before r24 has no sub-items: the rows of both tabs show in the TV tab as one list ([limitations](limitations.en.md#devices)).

**There is one slot.** If the TV tab showed channels from DUNE-HD.TV or another IPTV app, they leave the tab once you pick Melange. To get the channels back, choose DUNE-HD.TV in the same dialog (needs internet).

The rows in the TV tab refresh by themselves, about once an hour. After the Dune restarts, they show at once, before Melange starts. While Melange is not set up (no source), the TV tab has a single **Set up Melange** tile.

If you remove Melange while it is chosen for the TV tab, the tab goes empty. DUNE-HD.TV does not come back by itself: choose it again in TV section setup.

## Catalog server

Rows, search and posters come from Melange's shared AIOMetadata server `aiometadata.kompotik.com`; no keys or sign-up needed. If the server does not answer, the catalog shows saved copies of the rows, and a row without a copy can be reloaded.

## Data sources

Movies, series and posters come from [TMDB](https://www.themoviedb.org/), streaming service rows from [JustWatch](https://www.justwatch.com/) data. Thanks to the authors of the collections:
- the parameters of most rows (genres, themes, studios, decades, streaming, countries, runtime) are from the AIOMetadata template [luckynumb3rs/stremio-perfect-setup](https://github.com/luckynumb3rs/stremio-perfect-setup); the Russian rows and new releases were put together for Melange;
- Kinopoisk top is an unofficial mirror of the Kinopoisk Top 250, the public list by [lotist on Letterboxd](https://letterboxd.com/lotist/list/250/); it is not Kinopoisk's own rating, and the row has the first 200 movies;
- "Новинки в качестве" and "Новинки в 4K" follow the open list of [KinoTrend](https://kino-trend.ru/), "Свежие серии с переводом" follows [Kinou](https://kinou.ru/);
- "Сезон целиком с переводом" follows the open list of SerialTrend: series that already have a release of a whole season with Russian voice-over; a movie or series without an IMDb ID does not get into the rows of the "Fresh releases" group;
- the "on AniList" anime rows are from [AniList](https://anilist.co/);
- the catalog server is [AIOMetadata](https://github.com/cedya77/aiometadata).
