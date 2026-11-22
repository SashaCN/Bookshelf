# Нічний прогін: соцмережа і читанка

Гілка `feature/reader-social`. Файли користувача, які не чіпаємо: `Makefile`, `README.md` (змінені до старту), `.ddev/commands/`,
`backend/database/seeders/DemoSeeder.php`, `backend/database/seeders/data/`, `backend/tests/Feature/DemoSeederTest.php`, `docs/overnight-prompt.md`.

Базовий стан: Pest 289 зелених, Vitest 351 зелений.

## Слайс 1. Соцмережа (профіль, видимість цитат, підписки, стрічка)

Контракт:
- `users.username` (unique, nullable, `^[a-z0-9_]{3,30}$`), `users.is_public` bool default false. `PATCH /api/me` приймає обидва; `is_public=true` без username → 422 `profile.username_required`.
- `quotes.visibility` enum `private|public` default `private`; в Store/Update запитах і QuoteResource.
- `GET /api/users/{username}` → `{username, name, books_finished_this_year, followers_count, following_count, is_following}`; 404 якщо нема або приватний (власний профіль видно завжди).
- `GET /api/users/{username}/quotes` → публічні цитати, пагінація як у `/api/quotes`.
- `POST/DELETE /api/users/{username}/follow` → 204; не можна на себе (422 `follow.self`); приватний/відсутній → 404.
- `GET /api/users/{username}/followers`, `/following` → пагінація, елементи `{username, name}`, лише публічні профілі.
- `GET /api/feed` → публічні цитати тих, на кого підписаний, нові першими, з `author: {username, name}`.
- Таблиця `follows(follower_id, followed_id, created_at)`, PK на парі.

Статус: бек і фронт у роботі паралельно.

## Слайс 2. Читанка (EPUB)

Контракт:
- Таблиця `reader_documents(id, user_id, title, author nullable, path, chapters (json: список href у порядку spine), chapters_count, position_chapter int default 0, position_fraction decimal(5,4) default 0, timestamps)`. Файл на диску `local` (приватний) у `reader/{user_id}/`.
- `GET /api/reader` → список `{id, title, author, chapters_count, position: {chapter, fraction}, progress (0..100), created_at}`, нові першими, без пагінації (документів мало).
- `POST /api/reader` multipart `file` → 201 документ. Помилки 422: `reader.not_epub`, `reader.too_large` (валідатор max 20 МБ), `reader.zip_bomb`, `reader.invalid_epub` (нема container.xml/OPF/spine).
- `GET /api/reader/{id}` → документ; `DELETE` → 204 і файл видаляється; `PUT /api/reader/{id}/position` `{chapter, fraction}` → документ.
- `GET /api/reader/{id}/chapters/{n}` (n від 0) → `{data: {index, count, title?, html}}`, html це вміст `<body>` розділу як недовірений рядок; поза межами → 404.
- progress = (chapter + fraction) / chapters_count * 100.
- Фронт: iframe `srcdoc` з `sandbox=""` і CSP `default-src 'none'; style-src 'unsafe-inline'`.

Рішення: частку прокрутки в розділі фронт не відстежує. `sandbox=""` без `allow-same-origin` робить прокрутку iframe невидимою для сторінки, а послаблювати пісочницю заради цього не варто. API поле `fraction` зберігає, прогрес рахується за розділами. Записано в «Не робили».
Статус: фронт у роботі; бек стартує після соцбеку (спільна тестова БД).
Фронт читанки готовий (375 тестів, lint/typecheck чисті), чекає коміту разом із соцфронтом через спільні uk.ts/router.
Фронт соцмережі й читанки закомічено (402 тести).
Бек соцмережі закомічено (330 тестів). Коди помилок у errors.<поле>[0]: profile.username_required → is_public, follow.self → username.
Бек читанки закомічено (366 тестів). Ліміти PHP/nginx 20 МБ, образ app перезібрано. Документація етапу 7 написана. Мок демо в роботі.
