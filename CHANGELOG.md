# Changelog

**English version**

## 0.43.0 — not released yet
- New settings page. The main screen shows whether everything is set to watch (or what is left to do) and the list of sections, each with its current value and a status dot; a section opens as its own screen ("‹ Settings" or the phone's Back goes back). The first choice is one question with three answers, **"Where to get films"**: Debrid, "Torrents" or your own AIOStreams config; on the first run the main screen asks it right away. With both Debrid keys empty there is a Real-Debrid / TorBox switch and one field, the second key is added by a link. With "Torrents" the TorrServer field is on the same screen. "Check" is a small button inside the field, the result is a line under it. **"Compare speed"** of JacRed writes the reply time and the number of releases into the line of each JacRed. One **"Save"** button for the whole page, at the bottom of any screen, appears only when something is changed and shows the number of changes; when saving fails, the section of the field opens and the field is highlighted. The page follows the phone's light or dark theme. The config template is now **"Where to look for releases"**: "Everywhere" (was "JacRed + addons") or "Russian trackers only" (was "JacRed only"). Without JavaScript the sections open by links, and saving works as before.
- Without Debrid: the answer **"Torrents"** in "Where to get films" on the settings page (next to Debrid, as before, and your own config). No key is needed: releases are found by Torrentio, JacRed, TorrentsDB, Comet and StremThru Torz and play via TorrServer on the Dune; releases are sorted by seeders (0–2, 3–9, 10–29, 30 and more), and a heavy release (high bit rate) needs more seeders to stay on top; releases with no seeder count ("?" in the list) count as 0 seeders and go last. This applies to new "Torrents" configs and to "Reset to the template"; existing configs do not change. The Debrid keys are kept in Melange but not put in the config. On our server `aiostreams.kompotik.com` the server itself searches JacRed; on the other servers AIOStreams searches it through the third-party Magnetio addon (`magnetio.peterdsp.dev`), and with "Torrents" the JacRed address and key are passed to that addon. A server keeps a config for Debrid and one for "Torrents": switching between them does not replace the config, Melange switches to the config of that answer (it is created on the first save with it), and your edits in the AIOStreams web settings stay in both. Debrid needs a key until the server has a Debrid config.
- Release list: a P2P release shows its number of seeders in place of the "?" icon (over 999 as "999+"): dim for 0–2, brighter for 3–9 and 10–29, green for 30 and more; the card on the left shows them too: "P2P · 79 seeders". A Debrid release and a P2P release without a seeder count keep their icon.
- Versions 0.42.2 and older cannot read the settings of the new format: if you go back to an older version, Melange forgets its AIOStreams configs and makes new ones, and the old ones stay on the server.
- English interface: clearer error and hint wording, the settings path now reads Setup → Apps as in the firmware, and an item is consistently called a release.
- Documentation: README in English (README.en.md), CHANGELOG in two languages.

## 0.42.2 — 15.10.2026
- Comet's placeholder no longer plays as a movie. When Debrid refuses (for example, Real-Debrid will not serve a release because of copyright), Comet sends a short clip "Provider is currently unavailable." instead of the movie, and it used to open in the player. Now the player does not start, and Melange shows "Debrid returned a placeholder video" with the size of the clip; the history and Continue Watching are not spoiled. Comet's reply does not say why it refused.
- Internal: when JacRed is skipped for lack of time, the log now says "jacred: skipped, the operation took N s" instead of "play_action took": the skip also happens on paging, refresh and episode choice. Melange behaves the same.

## 0.42.1 — 15.10.2026
- "Download the log": the file now starts with the Dune model and the versions of the firmware and the Dune HD app; after the Melange log comes the tail of the Dune shell log (`shell.log` and the previous `shell.log.old`, up to 1 MB each): it shows why the player or Melange closed in the middle of playback. The shell log hides the serial number, MAC and IP addresses (except `127.0.0.1` and `::1`), names of computers in the home network, and the values of keys, passwords, tokens and cookies; links keep only the server (`http://server:port/…`). Look through the file before sending it: the shell log has the names of your favorites shortcuts, the list of installed plugins and apps, and catalog movie IDs.
- If there is no Melange log (after the Dune reboots), the file still has the Dune details and the shell log.

## 0.42.0 — 13.10.2026
- Melange's own servers come first in the lists on the settings page and are chosen by default when no choice has been saved yet (a new install, or settings of an older version with no server chosen): the AIOStreams server `aiostreams.kompotik.com` (with its own TMDB, no TMDB key needed) and JacRed `jacred.kompotik.com`. If the chosen JacRed does not answer, Melange now asks `jacred.kompotik.com` instead of `jacred.stream`.
- If you have already chosen and saved an AIOStreams server or JacRed, nothing changes: the config, the link and the choice stay as they were.
- The AIOStreams config template no longer hides releases that are not cached on Real-Debrid (`RD⏳`): with Real-Debrid alone, new Russian series often have one cached release or none. Such a release is first downloaded to Real-Debrid. This applies to new configs and to "Reset to the template"; existing configs do not change.
- The warning "jacred.stream refuses the chosen AIOStreams server" (with `aiostreams.fortheweak.cloud`) is shown only when `jacred.stream` is chosen.

## 0.41.0 — 10.10.2026
- Settings page: when saving fails, the wrong field is highlighted and the page scrolls to it (for a download server, to its whole block); the highlight goes away as soon as you start editing. The same happens when, after saving, the config on the AIOStreams server was not updated because there is no Debrid key or your own AIOStreams server cannot be reached.
- If the config on the AIOStreams server could not be updated, the page says "Melange settings are saved on the Dune." instead of "Saved", and config errors name the AIOStreams server directly. If the Dune is slow to answer, the page says "The Dune did not answer within N s: try again" instead of "No connection"; if the connection drops during "Creating the config…", it tells you which button to press again.
- If the page link is out of date, one message appears at the top and the form is locked, instead of every button answering with the same message. While saving, the "Check" buttons are disabled; saving during "Compare all" stops the measurement (rows not measured yet show "stopped").
- A table of contents at the top of the page (AIOStreams, JacRed, TorrServer, "Advanced"); an opened "Advanced" section no longer collapses after saving; the "Compare all" table has a "Choose" button and fits a phone screen. Reloading the page after saving no longer shows "Saved" again.
- In Firefox, keys and passwords on the page are hidden behind dots, and the eye button shows them. Screen readers read out check results. A disabled "Duplicate" button has the hint "All 5 server slots are in use". A broken character in a field no longer leaves the "Check" button disabled.
- "Release details" (INFO): if AIOStreams reports which addons found the same release, the "Origin" column shows "Addons" with their list, and "(cached)" next to the addons that reported the release cached on its Debrid service.
- Internal: the settings page script is rewritten in TypeScript and covered by tests, the page's PHP is split into files; Melange on the Dune behaves the same.

## 0.40.0 — 09.10.2026
- Config template choice on the settings page, under the AIOStreams server: **"JacRed + addons"** (the default, as since 0.38.0: JacRed, Torrentio, MediaFusion, Comet and StremThru Torz) or **"JacRed only"** (Russian trackers, faster reply; few releases with Real-Debrid alone). A new config is made from the chosen template.
- The choice shows the template of the config already made on the chosen server: Melange recognizes it by the config's addons on the server, on every "Save". If the set of addons was changed in the AIOStreams web settings, it shows "Custom set of addons (changed in AIOStreams)". For configs made before 0.40.0 the template becomes known after the first "Save"; until then the choice shows "Unknown - press "Save"". Without a config, it shows the template for a new config.
- **"Reset to the template"** button next to the config login and password: the config on the server is replaced with the chosen template, with your keys and JacRed. The login, password and link stay the same, but edits made in the AIOStreams web settings are lost (including keys entered there by hand). The page asks for confirmation before the reset. Choosing a template other than the config's and pressing "Save" also resets it, with the same question.
- If your config was made before 0.38.0 (it has only JacRed), choose "JacRed + addons" and press "Save" to get the Torrentio, MediaFusion, Comet and StremThru Torz addons.
- A release "cached on Real-Debrid" from MediaFusion sometimes failed at once with a playback error: Real-Debrid refuses to serve some releases flagged for copyright, and MediaFusion sent the player to its placeholder video instead of the movie. Now the player does not start, and Melange shows "Debrid blocked the release: copyright"; the history and Continue Watching are not spoiled. The same goes for such placeholders of Torrentio, StremThru, AIOStreams and MediaFusion, for the next episode in the player and from Continue Watching, if the release is cached; an uncached release with a placeholder goes, as before, to TorrServer or to the list.
- Other such placeholders of ElfHosted addons (MediaFusion, AIOStreams, StremThru; for example, "the release is still being cached") also do not start the player: Melange shows "Debrid returned a placeholder video" with its title.

## 0.39.0 — 09.10.2026
- A Debrid placeholder video no longer spoils the history. Before, a "Cached" release sometimes played a 2-minute or 30-second placeholder (a TorBox limit, an outdated "cached" flag on Real-Debrid, a Torrentio failure), and its progress overwrote the movie position on the "Melange [NN%]" button and in Continue Watching; a placeholder watched to the end marked the movie as watched. Now, before playing via Debrid, Melange asks once for the release link (AIOStreams, Torrentio, MediaFusion, StremThru). If it gets a placeholder instead of the movie, the player does not start, and Melange shows the reason: "Debrid has not downloaded the release yet, try again later", "Debrid limit reached" or "Debrid returned a placeholder video" with the file name. For the next episode in the player, the window "Episode … failed to load" with a "Stop" button appears. The same from Continue Watching.
- If Debrid gave a file link at once, the release plays with history even when it is marked "Not cached" or its status is unknown. The next episodes of such a release also play in a row in the player if Debrid gives links for them too, and Continue Watching opens it via Debrid; if a placeholder comes instead of a link, it goes as before via TorrServer or to the list.
- Comet's placeholder cannot be recognized this way: Comet sends it at once as a video. Nothing changes for TorrServer.

## 0.38.0 — 09.10.2026
- A new config made by Melange searches for releases not only in JacRed but also in Torrentio, MediaFusion, Comet and StremThru Torz. More releases are cached on Debrid, especially on Real-Debrid: before, with Real-Debrid alone the list was almost empty. AIOStreams' first reply for a movie takes longer: 6–9 seconds instead of 5; a repeated one takes 1–3 seconds.
- Configs already made do not change. To get the new sources, add these addons in the AIOStreams web settings (the link, login and password are on the Melange settings page), or delete the config there: on the next save Melange reports that it has forgotten it, and "Save and create the config" makes a new one.

## 0.37.1 — 09.10.2026
- The trackers column in the release list disappeared when it was a few pixels short of the width it needs (for example, when a release in the list has 17 languages). Now the trackers fit more often.

## 0.37.0 — 09.10.2026
- The name of the AIOStreams addon a release came from is now always shown: in the details on the left, at the bottom to the right of the poster (if there is no room there, under the poster when the release has no trackers); in "Release details" (INFO), in the "Origin" column after the trackers. Before, the addon was shown only for releases without trackers.

## 0.36.0 — 09.10.2026
- Online updates: the Dune itself checks GitHub Pages for a new Melange version (after a reboot and when you open "Setup → Apps → Melange") and offers to install it. Install 0.36.0 itself by hand, as before; if an update does not come, install the new zip over the old one. Settings are kept on update.

## 0.35.10 — 09.10.2026
- Setup from a phone on older Realtek/Amlogic models answered 403 Forbidden on any phone: the page could not find the plugin data folder. Now it finds it. If the folder is not found at all, the page says so; please tell the author your Dune model and firmware version.

## 0.35.9 — 09.10.2026
- Language flags in the release list and the "Languages" line on the left now follow the release's audio tracks when they are known (from the Debrid service or from JacRed). Before, JacRed releases with Cyrillic in the name were shown as RU, for example Ukrainian ones with UA and EN tracks.

## 0.35.8 — 08.10.2026
First public release. What it does:
- a "Melange" item in the "Play..." menu of a movie or series card in the "Movies" catalog: a list of up to 100 releases from your AIOStreams config with quality badges, size, languages and release details;
- playback in the Dune's built-in player with history: the percentage on the button, the Continue Watching row, resume from where you stopped;
- series: the episodes of a season play in a row in the player (P+ / P− and auto-advance), episodes can be flipped in the list with ← / →;
- playback via TorrServer, without Debrid (P2P releases) or for an uncached release;
- studio voice-overs from the release name and its tracks, and optionally tracks from JacRed;
- setup from a phone by a QR code: Melange makes the AIOStreams config by itself from a Debrid key; "Download the log" is there too.

---

**Russian version**

## 0.43.0 — ещё не выпущена
- Новая страница настроек. Главный экран показывает, всё ли готово к просмотру (или что ещё сделать), и список разделов — в строке текущее значение и точка состояния; раздел открывается отдельным экраном («‹ Настройки» или «Назад» телефона — обратно). Главный выбор — один вопрос с тремя ответами, **«Откуда брать фильмы»**: Debrid, «Торренты» или свой конфиг AIOStreams; при первом запуске главный экран сразу спрашивает его. Пока оба ключа Debrid пусты — переключатель Real-Debrid / TorBox и одно поле, второй ключ добавляется ссылкой. При «Торрентах» поле TorrServer — на том же экране. «Проверить» — маленькая кнопка внутри поля, результат — строкой под ним. **«Сравнить скорость»** JacRed пишет время ответа и число раздач в строку каждого JacRed. Одна кнопка **«Сохранить»** на всю страницу — внизу любого экрана, только когда что-то изменено, с числом изменений; при ошибке сохранения открывается раздел с полем, поле подсвечено. Тема — светлая или тёмная, как на телефоне. Шаблон конфига теперь — **«Где искать раздачи»**: «Везде» (было «JacRed + аддоны») или «Только русские трекеры» (было «Только JacRed»). Без JavaScript разделы открываются по ссылкам, сохранение работает как раньше.
- Без Debrid: на странице настроек в «Откуда брать фильмы» — ответ **«Торренты»** (рядом с Debrid, как раньше, и своим конфигом). Ключ не нужен: раздачи ищут Torrentio, JacRed, TorrentsDB, Comet и StremThru Torz, играют через TorrServer на Дюне; раздачи сортируются по сидам (0–2, 3–9, 10–29, 30 и больше), а тяжёлой раздаче (высокий битрейт) нужно больше сидов, чтобы остаться наверху; раздачи без данных о сидах (в списке «?») считаются как с 0 сидов и стоят в конце. Это касается новых конфигов «Торрентов» и «Сбросить к шаблону»; существующие конфиги не меняются. Ключи Debrid хранятся в Melange, но в конфиг не идут. На нашем сервере `aiostreams.kompotik.com` в JacRed ищет сам сервер, на других — AIOStreams ищет через сторонний аддон Magnetio (`magnetio.peterdsp.dev`): при «Торрентах» адрес и ключ JacRed передаются этому аддону. У сервера по конфигу на Debrid и на «Торренты»: переключение не заменяет конфиг — Melange переключается на конфиг этого ответа (он создаётся при первом сохранении с ним), правки в веб-настройках AIOStreams остаются в обоих. Debrid без ключа — только если на сервере уже есть конфиг Debrid.
- Список раздач: у раздачи P2P вместо иконки «?» — число сидов (больше 999 — «999+»): тусклое при 0–2, ярче при 3–9 и 10–29, зелёное при 30 и больше; в карточке слева — тоже: «P2P · 79 сидов». У раздач Debrid и у P2P без числа сидов — иконка, как раньше.
- Версии 0.42.2 и более ранние не читают настройки нового формата: при откате на старую версию Melange забудет конфиги AIOStreams и создаст новые, а старые останутся на сервере.
- Английский интерфейс: исправлены формулировки ошибок и подсказок, путь к настройкам — Setup → Apps, как в прошивке; раздача везде называется release.
- Документация: README на английском (README.en.md), CHANGELOG на двух языках.

## 0.42.2 — 15.10.2026
- Заглушка Comet больше не играет как фильм. Когда Debrid отказывает (например, Real-Debrid не отдаёт раздачу из-за авторских прав), Comet присылает вместо фильма короткий ролик «Provider is currently unavailable.» — раньше он открывался в плеере. Теперь плеер не запускается, а Melange показывает «Debrid вернул заглушку» с размером ролика; история и «Продолжить просмотр» не портятся. Причину отказа Comet в ответе не сообщает.
- Внутреннее: в логе пропуск JacRed по времени — «jacred: skipped, the operation took N s» вместо «play_action took»: пропуск бывает и при перелистывании, обновлении и выборе серии. Поведение Melange не менялось.

## 0.42.1 — 15.10.2026
- «Скачать лог»: в начале файла — модель Дюны, версия прошивки и приложения Dune HD; после лога Melange — конец лога оболочки Дюны (`shell.log` и предыдущий `shell.log.old`, до 1 МБ каждого): по нему видно, почему плеер или Melange закрылись посреди просмотра. В логе оболочки скрыты серийный номер, MAC- и IP-адреса (кроме `127.0.0.1` и `::1`), имена компьютеров домашней сети, значения ключей, паролей, токенов и cookie, а у ссылок остаётся только сервер (`http://сервер:порт/…`). Перед отправкой просмотрите файл: в логе оболочки есть названия ярлыков избранного, список установленных плагинов и приложений, ID фильмов каталога.
- Если лога Melange нет (после перезагрузки Дюны), файл всё равно содержит сведения о Дюне и лог оболочки.

## 0.42.0 — 13.10.2026
- Свои серверы Melange — первые в списках на странице настроек и выбраны по умолчанию, если свой выбор ещё не сохранён (новая установка или настройки старой версии, где сервер не выбран): сервер AIOStreams `aiostreams.kompotik.com` (со своим TMDB, ключ TMDB не нужен) и JacRed `jacred.kompotik.com`. Если выбранный JacRed не ответил, Melange теперь спрашивает `jacred.kompotik.com`, а не `jacred.stream`.
- У кого сервер AIOStreams или JacRed уже выбран и сохранён — ничего не меняется: конфиг, ссылка и выбор остаются прежними.
- Шаблон конфига AIOStreams больше не скрывает раздачи, которых нет в кэше Real-Debrid (`RD⏳`): с одним Real-Debrid у новых русских сериалов в кэше бывает одна раздача или ни одной. Такая раздача сначала скачивается на Real-Debrid. Касается новых конфигов и «Сбросить к шаблону»; существующие конфиги не меняются.
- Предупреждение «jacred.stream не пускает выбранный сервер AIOStreams» (при `aiostreams.fortheweak.cloud`) показывается, только когда выбран `jacred.stream`.

## 0.41.0 — 10.10.2026
- Страница настроек: при ошибке сохранения неверное поле подсвечивается и страница прокручивается к нему, а у сервера скачивания — весь его блок; подсветка пропадает, как только начнёте править. Так же — если после сохранения конфиг на сервере AIOStreams не обновился из-за отсутствия ключа Debrid или из-за недоступного своего сервера AIOStreams.
- Если конфиг на сервере AIOStreams обновить не удалось, страница пишет «Настройки Melange сохранены на Дюне.» вместо «Сохранено», а ошибки конфига прямо называют сервер AIOStreams. Если Дюна долго не отвечает — «Дюна не ответила за N с — повторите», а не «Нет связи»; если связь пропала во время «Создаю конфиг…» — какую кнопку нажать ещё раз.
- Если ссылка на страницу устарела, сверху появляется одно сообщение и форма блокируется, а не отвечает тем же на каждую кнопку. Пока идёт сохранение, кнопки «Проверить» неактивны; сохранение во время «Сравнить все» останавливает замер (недомеренные строки — «прервано»).
- Оглавление наверху страницы (AIOStreams, JacRed, TorrServer, «Дополнительно»); раскрытое «Дополнительно» не сворачивается после сохранения; в таблице «Сравнить все» — кнопка «Выбрать», таблица помещается на экран телефона. После сохранения перезагрузка страницы больше не показывает «Сохранено» повторно.
- В Firefox ключи и пароли на странице скрыты точками, «глазок» их показывает. Результаты проверок озвучивает экранный диктор. У неактивной «Дублировать» — подсказка «Все 5 блоков заняты». Испорченный символ в поле больше не оставляет кнопку «Проверить» неактивной.
- «Сведения о раздаче» (INFO): если AIOStreams сообщает, в каких аддонах нашлась та же раздача, в колонке «Откуда» — «Аддоны» со списком и пометкой «(кэш)» у аддонов, сообщивших кэш на сервисе раздачи.
- Внутреннее: скрипт страницы настроек переписан на TypeScript и покрыт тестами, PHP страницы разложен по файлам; поведение Melange на Дюне не менялось.

## 0.40.0 — 09.10.2026
- Выбор шаблона конфига на странице настроек, под сервером AIOStreams: **«JacRed + аддоны»** (по умолчанию, как с 0.38.0: JacRed, Torrentio, MediaFusion, Comet и StremThru Torz) или **«Только JacRed»** (русские трекеры, ответ быстрее; с одним Real-Debrid раздач мало). Новый конфиг создаётся по выбранному шаблону.
- Выбор показывает шаблон уже созданного конфига выбранного сервера: Melange узнаёт его по аддонам конфига на сервере, при каждом «Сохранить». Если набор аддонов меняли в веб-настройках AIOStreams — «Свой набор аддонов (изменён в AIOStreams)». У конфигов, созданных до 0.40.0, шаблон станет известен после первого «Сохранить», а до этого выбор показывает «Не определён — нажмите «Сохранить»». Без конфига — шаблон для нового конфига.
- Кнопка **«Сбросить к шаблону»** рядом с логином и паролем конфига: конфиг на сервере заменяется выбранным шаблоном с вашими ключами и JacRed. Логин, пароль и ссылка не меняются, а правки в веб-настройках AIOStreams пропадают (в том числе ключи, вписанные там вручную). Перед сбросом страница спрашивает подтверждение. Выбрали шаблон, отличный от шаблона конфига, и нажали «Сохранить» — тоже сброс, с тем же вопросом.
- Конфиг создан до 0.38.0 (в нём только JacRed) — выберите «JacRed + аддоны» и нажмите «Сохранить», чтобы получить аддоны Torrentio, MediaFusion, Comet и StremThru Torz.
- Раздача «в кэше Real-Debrid» от MediaFusion иногда сразу выдавала «Ошибка воспроизведения»: Real-Debrid отказывается отдавать часть раздач, помеченных как нарушающие авторские права, и MediaFusion вместо фильма отправлял плеер на свою видео-заглушку. Теперь плеер не запускается, а Melange показывает «Debrid заблокировал раздачу: авторские права»; история и «Продолжить просмотр» не портятся. Так же — для таких заглушек Torrentio, StremThru, AIOStreams и MediaFusion, у следующей серии в плеере и из «Продолжить просмотр», если раздача в кэше; раздача не из кэша при заглушке, как и раньше, уходит в TorrServer или в список.
- Другие такие заглушки аддонов ElfHosted (MediaFusion, AIOStreams, StremThru; например, «раздача ещё кэшируется») — тоже без плеера: «Debrid вернул заглушку» с её заголовком.

## 0.39.0 — 09.10.2026
- Видео-заглушка Debrid больше не портит историю. Раньше раздача «В кэше» иногда играла заглушку на 2 минуты или 30 секунд (лимит TorBox, устаревшая метка «в кэше» у Real-Debrid, сбой Torrentio), и её прогресс затирал позицию фильма на кнопке «Melange [NN%]» и в «Продолжить просмотр»; досмотренная заглушка отмечала фильм просмотренным. Теперь перед запуском через Debrid Melange один раз спрашивает ссылку раздачи (AIOStreams, Torrentio, MediaFusion, StremThru). Если вместо фильма заглушка, плеер не запускается, а Melange показывает причину: «Debrid ещё не скачал раздачу, попробуйте позже», «Лимит Debrid» или «Debrid вернул заглушку» с именем файла. У следующей серии в плеере — окно «Серия … не загрузилась» с кнопкой «Стоп». Так же из «Продолжить просмотр».
- Если Debrid сразу отдал ссылку на файл, раздача играет с историей, даже когда она помечена «Нужно скачать» или статус неизвестен. Следующие серии такой раздачи в плеере тоже идут подряд, если Debrid отдаёт ссылку и на них, а «Продолжить просмотр» открывает её через Debrid; если вместо ссылки заглушка — как раньше, через TorrServer или список.
- Заглушку Comet так не распознать: Comet отдаёт её сразу как видео. Через TorrServer ничего не меняется.

## 0.38.0 — 09.10.2026
- Новый конфиг, который создаёт Melange, ищет раздачи не только в JacRed, но и в Torrentio, MediaFusion, Comet и StremThru Torz. Раздач в кэше Debrid становится больше, особенно у Real-Debrid: раньше с одним Real-Debrid список был почти пустым. Первый ответ AIOStreams на фильм стал дольше: 6–9 секунд вместо 5, повторный — 1–3 секунды.
- Уже созданные конфиги не меняются. Чтобы получить новые источники, добавьте эти аддоны в веб-настройках AIOStreams (ссылка, логин и пароль — на странице настроек Melange) или удалите там конфиг: при следующем сохранении Melange сообщит, что забыл его, а «Сохранить и создать конфиг» создаст новый.

## 0.37.1 — 09.10.2026
- Колонка трекеров в списке раздач пропадала, когда до нужной ширины не хватало нескольких пикселей (например, в списке есть раздача с 17 языками). Теперь трекерам хватает места чаще.

## 0.37.0 — 09.10.2026
- Имя аддона AIOStreams, из которого пришла раздача, теперь видно всегда: в подробностях слева — справа от постера внизу (если там не хватает места — под постером, когда у раздачи нет трекеров), в «Сведениях о раздаче» (INFO) — в колонке «Откуда» после трекеров. Раньше аддон показывался только у раздач без трекеров.

## 0.36.0 — 09.10.2026
- Онлайн-обновление: Дюна сама проверяет новую версию Melange на GitHub Pages — после перезагрузки и при входе в «Настройки → Приложения → Melange» — и предлагает её поставить. Саму 0.36.0 ставьте вручную, как раньше; если обновление не пришло — поставьте новый zip поверх старого. Настройки при обновлении сохраняются.

## 0.35.10 — 09.10.2026
- Настройки с телефона на старых моделях на Realtek/Amlogic отвечали 403 Forbidden на любом телефоне: страница не находила папку данных плагина. Теперь находит. Если папки не нашлось совсем, страница так и пишет — сообщите автору модель Дюны и версию прошивки.

## 0.35.9 — 09.10.2026
- Флаги языков в списке раздач и строка «Языки» слева — по аудиодорожкам раздачи, если они известны (от Debrid-сервиса или из JacRed). Раньше раздачи из JacRed с кириллицей в названии показывались как RU — например, украинские с дорожками UA и EN.

## 0.35.8 — 08.10.2026
Первый публичный выпуск. Что умеет:
- пункт «Melange» в меню «Смотреть…» карточки фильма или сериала в каталоге «Фильмы»: список до 100 раздач из вашего конфига AIOStreams со значками качества, размером, языками и подробностями раздачи;
- просмотр во встроенном плеере Дюны с историей: процент на кнопке, ряд «Продолжить просмотр», возврат с того же места;
- сериалы: серии сезона подряд в плеере (P+ / P− и автопереход), листание серий в списке клавишами ← / →;
- просмотр через TorrServer — без Debrid (раздачи P2P) или для раздачи не из кэша;
- озвучки студий по названию раздачи и её дорожкам, по желанию — дорожки из JacRed;
- настройка с телефона по QR-коду: Melange сам создаёт конфиг AIOStreams по ключу Debrid; там же — «Скачать лог».
