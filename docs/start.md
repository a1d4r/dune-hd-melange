# Быстрый старт

Melange — каталог фильмов и сериалов для Dune HD. Выберите фильм — Melange найдёт его в торрентах и сразу включит в плеере Дюны, без скачивания. [← Оглавление](README.md)

Поставим Melange и TorrServe (через него Дюна играет торренты) и откроем каталог. TorrServe — приложение, внутри него работает TorrServer: в сообщениях Melange встречаются оба названия.

<img src="../screenshots/overview.png" alt="Схема: выбрали фильм в каталоге Melange, Melange ищет раздачи в торрентах, выбрали раздачу, фильм идёт в плеере Дюны через TorrServe или Debrid" width="100%">

Нужно заранее: Dune HD на Android с прошивкой r24 или r25. Рекомендуется r25 (если доступна). Чего нет на r24 — [Известные ограничения](limitations.md#устройства-и-прошивки).

Если у вас есть подписка Real-Debrid или TorBox, после установки Melange переходите к главе [Debrid](debrid.md): TorrServe тогда не обязателен.

Ставить можно пультом [через Dune BackUp](#через-dune-backup) или [вручную](#вручную), файлами с GitHub.

## Через Dune BackUp

1. Если Dune BackUp ещё не установлен, поставьте его: Dune Store → «Приложения» → «Комьюнити приложения» → «Игнорировать и продолжить», встаньте на «Dune BackUp», нажмите **ENTER** и установите приложение.

   <img src="../screenshots/start-store-community.jpg" alt="Dune Store, «Приложения»: фокус на «Комьюнити приложения»" width="100%">

   <img src="../screenshots/start-store-dunebackup.jpg" alt="Dune Store, «Комьюнити приложения»: фокус на Dune BackUp" width="100%">

2. Откройте Dune BackUp → «Коллекция плагинов для Dune HD».

   <img src="../screenshots/start-dunebackup-menu.jpg" alt="Dune BackUp: фокус на «Коллекция плагинов для Dune HD»" width="100%">

3. Встаньте на поле «Плагин» и нажмите **ENTER**: откроется список. В разделе «--- Фильмы ---» выберите «Melange» и нажмите «Установить плагин». Если Melange уже стоит, на вопрос «Заменить приложение?» ответьте «Да».

   <img src="../screenshots/start-dunebackup.jpg" alt="Dune BackUp, «Коллекция плагинов»: раздел «--- Фильмы ---», фокус на «Melange»" width="100%">

4. Откройте Melange в «Приложениях». Дюна предложит новую версию — нажмите «Обновить».

   <img src="../screenshots/start-update.jpg" alt="«Обновление приложения»: Melange 0.57.0 → 0.59.0, фокус на «Обновить»" width="100%">

5. TorrServe ставится там же: Dune BackUp → «Коллекция приложений Android» → «Приложение» → верхний «TorrServe MatriX» в разделе «--- Для Торрентов ---» → «Установить приложение». Подойдёт любая версия MatriX.

   <img src="../screenshots/start-android-torrserve.jpg" alt="«Коллекция приложений Android», список «Приложение»: раздел «--- Для Торрентов ---», фокус на «TorrServe MatriX 140»" width="100%">

Дальше: [первая настройка](#первая-настройка).

## Вручную

1. Скачайте `dune_plugin_melange_<версия>.zip` последнего релиза (раздел Assets): https://github.com/a1d4r/dune-hd-melange/releases. Для TorrServe скачайте `TorrServe_MatriX.145.Client.apk` (раздел Assets): https://github.com/YouROK/TorrServe/releases/tag/MatriX.145.Client. Подойдёт любая версия MatriX.

2. Скопируйте оба файла на Дюну любым способом: с телефона через Send Files, по сети или флешкой. Zip не распаковывайте.

3. На Дюне откройте **«Файлы»**, найдите zip в той папке, куда его скопировали, встаньте на него и нажмите **ENTER**.

   <img src="../screenshots/start-files-zip.jpg" alt="«Файлы» → «Встроенная память»: фокус на dune_plugin_melange_0.59.0.zip" width="100%">

4. Дождитесь «Установлено успешно» и нажмите «OK».

   <img src="../screenshots/start-install.jpg" alt="«Установка приложения»: «Установлено успешно», фокус на OK" width="100%">

5. Так же, через **«Файлы»**, установите apk TorrServe.

## Первая настройка

1. Откройте TorrServe: «Приложения» → вкладка ANDROID → TorrServe. Если он попросит установить сервер — установите. В настройках TorrServe включите **«Запускать сервер при загрузке»** и ниже **«Запускать сервер от root»**.

   <img src="../screenshots/start-torrserve-autostart.jpg" alt="Настройки TorrServe: включены «Запускать сервер при загрузке» и «Запускать сервер от root»" width="100%">

2. Откройте **«Приложения»** → **«Melange»**.

   <img src="../screenshots/start-apps-melange.jpg" alt="«Приложения»: фокус на значке Melange" width="100%">

3. Нажмите **«Быстрая настройка: торренты»**.

   <img src="../screenshots/setup-dune.jpg" alt="Экран настроек Melange, фокус на «Быстрая настройка: торренты»" width="100%">

   «AIOStreams — конфиг Melange: не задан» — так и должно быть. AIOStreams — сервер, который ищет раздачи, а конфиг — ваши настройки на нём. Melange создаст его сам.

4. Откроется каталог Melange: можно выбирать фильм.

   <img src="../screenshots/catalog.jpg" alt="Каталог Melange: сверху вкладки «Фильмы» и «Сериалы», справа сердечко, лупа и шестерёнка, ниже ряды «Продолжить», «Избранное», «В тренде»" width="100%">

   Если появилось «TorrServer не отвечает», откройте TorrServe (он запустит сервер), потом снова Melange. Не помогло — проверьте шаг 1.

Новые версии Melange Дюна предложит сама.

Дальше: [Просмотр](watch.md) · [Оглавление](README.md)

Ошибки — в Issues: https://github.com/a1d4r/dune-hd-melange/issues
