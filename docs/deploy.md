# Деплой на Azure for Students

Цей документ описує, як один раз підготувати сервер і підключити автоматичний деплой. Після цього кожен мерж у `main`
з зеленим CI сам збирає образи, викочує їх на сервер і накочує міграції.

> **Статус перевірки.** Образи, `docker-compose.prod.yml` і Caddy перевірені локально (реєстрація, сесія, API, SPA), але на Mac з
> архітектурою arm64, тоді як сервер буде x64. Для PHP, nginx, MySQL і Caddy це різниця лише в збірці, проте саме x64-образи
> локально не запускались. Робота з GitHub Actions, GHCR, Azure і Let's Encrypt не перевірялась, бо для цього потрібен справжній сервер.
> Якщо на якомусь кроці щось іде не так, дивіться розділ «Якщо щось не працює» внизу.

## Як це влаштовано

```
Інтернет ──► Caddy (HTTPS, сертифікат Let's Encrypt)
               └─► web (nginx: SPA + проксі /api)
                     └─► app (php-fpm, Laravel) ──► mysql
```

Усе працює в Docker на одній віртуальній машині. Секрети лежать лише на сервері у файлі `/opt/bookshelf/production.env`,
а в GitHub зберігається тільки SSH-ключ для деплою.

## 1. Azure for Students

1. Активуйте пропозицію: <https://azure.microsoft.com/free/students/> (потрібна студентська пошта університету).
   Кредит $100 діє 12 місяців, банківська картка не потрібна.
2. У <https://portal.azure.com> відкрийте **Cost Management → Budgets** і створіть бюджет на $20 зі сповіщенням на пошту.
   Так ви побачите, якщо щось почне швидко витрачати кредит.

## 2. Ключ для SSH

На вашому Mac створіть окремий ключ для цього сервера (без пароля, щоб його міг використовувати GitHub Actions):

```bash
ssh-keygen -t ed25519 -f ~/.ssh/bookshelf_deploy -C bookshelf-deploy -N ""
```

Приватний ключ `~/.ssh/bookshelf_deploy` нікому не передавайте, крім секрету GitHub (крок 5).

## 3. Віртуальна машина

**Virtual machines → Create → Azure virtual machine**:

| Поле | Значення |
|---|---|
| Region | **Denmark East.** Підписка Azure for Students дозволяє розгортання лише в п'яти регіонах (політика «Allowed resource deployment regions»): Austria East, Denmark East, Italy North, Norway East, Switzerland North. У решті регіонів валідація падає з `RequestDisallowedByAzure`, хоча самі регіони в списку є. З цих п'яти B2ats_v2 для підписки доступний саме в Denmark East; в Italy North квота vCPU нульова, а в Norway East і Switzerland North цей розмір недоступний. Austria East не перевірявся |
| Availability options | **No infrastructure redundancy required.** За замовчуванням стоїть «Availability zone» (Zone 1), і тоді в списку розмірів усе недоступне з поясненням «Unsupported availability zone» |
| Image | Ubuntu Server 24.04 LTS, **x64 Gen2** (VM architecture: x64) |
| Size | **B2ats_v2** (2 vCPU, **1 ГБ RAM**). Він входить у безкоштовні 750 годин на місяць протягом 12 місяців. Розмір за замовчуванням `Standard_D2s_v3` недоступний для вашої підписки, його треба замінити через «See all sizes» |
| Authentication | SSH public key, Username `azureuser`, вставте вміст `~/.ssh/bookshelf_deploy.pub` |
| Inbound ports | SSH (22), HTTP (80), HTTPS (443) |
| OS disk | **Standard SSD** (за замовчуванням стоїть Premium SSD, він дорожчий), 30 ГБ |
| Public IP | **Static**, інакше IP зміниться після зупинки машини, і ламаються DNS та сертифікат |

Вимкніть автоматичне вимкнення (auto-shutdown), якщо воно запропоноване. Після створення скопіюйте публічну IP-адресу.

**Про 1 ГБ RAM.** Усі безкоштовні розміри (B1s, B2pts_v2 Arm, B2ats_v2) мають лише 1 ГБ пам'яті. Тому стек налаштований економно:
MySQL з буфером 64 МБ, без бінарного журналу і з вимкненою performance schema, PHP-FPM запускає працівників на вимогу, а `bootstrap-server.sh` додає swap на 2 ГБ.
Для навчального проєкту з малим навантаженням цього вистачає, але запас невеликий. Якщо сервер почне «задихатись», варіанти такі:
розмір із 4 ГБ (`B2als_v2`, але він платний і витрачає кредит $100, ціна в порталі для цієї підписки не показується) або інший хостинг.
Arm-розмір `B2pts_v2` у Denmark East для вашої підписки не з'являвся, тому образи x64.

> Збірка образів у `ci.yml` розрахована на x64 (`linux/amd64`). Якщо ви колись перейдете на Arm-сервер, змініть там `platforms`
> на `linux/arm64`, а `runs-on` у job `images` на `ubuntu-24.04-arm`.

## 4. Домен

Потрібна адреса, яка вказує на ваш сервер. Є два шляхи:

- **Без домену (найшвидше).** Використайте IP, записаний через дефіси, плюс `.sslip.io`. Для IP `20.1.2.3` це
  `20-1-2-3.sslip.io`. Адреса сама резолвиться в ваш IP, і Caddy отримає для неї справжній сертифікат.
- **Свій безкоштовний домен.** У GitHub Student Pack є домен на рік від Name.com (понад 25 розширень) і `.me` від Namecheap.
  Після реєстрації додайте DNS-запис типу **A** з вашим IP.

## 5. Підготовка сервера (один раз)

З вашого Mac, з кореня репозиторію (підставте IP і домен):

```bash
scp -i ~/.ssh/bookshelf_deploy deploy/bootstrap-server.sh azureuser@<IP>:~
ssh -i ~/.ssh/bookshelf_deploy azureuser@<IP>
```

Далі вже на сервері:

```bash
sudo bash bootstrap-server.sh <домен>
exit
```

Скрипт ставить Docker, створює `/opt/bookshelf` і записує `production.env` зі свіжезгенерованими паролями й ключем
застосунку. Якщо в машині мало RAM, він ще додає swap. Потім вийдіть і зайдіть знову, щоб застосувалась група `docker`.

## 6. Налаштування GitHub

У репозиторії: **Settings → Environments → New environment**, назва `production`. За бажанням увімкніть
*Required reviewers*, тоді кожен деплой треба буде підтверджувати кнопкою.

У цьому environment додайте **секрети**:

| Секрет | Значення |
|---|---|
| `DEPLOY_HOST` | IP-адреса сервера |
| `DEPLOY_USER` | `azureuser` |
| `DEPLOY_SSH_KEY` | повний вміст файлу `~/.ssh/bookshelf_deploy` (приватний ключ, разом з рядками BEGIN/END) |
| `DEPLOY_KNOWN_HOSTS` | результат команди `ssh-keyscan -t ed25519 <IP>` з вашого Mac |

Потім **Settings → Secrets and variables → Actions → Variables** (змінні репозиторію):

| Змінна | Значення |
|---|---|
| `DEPLOY_DOMAIN` | ваш домен, наприклад `20-1-2-3.sslip.io` |
| `DEPLOY_ENABLED` | `true` (це вмикає публікацію образів і деплой; доки змінної немає, ці кроки пропускаються) |

## 7. Перший деплой

Зробіть будь-який коміт у `main` або перезапустіть останній прогін CI (**Actions → CI → Re-run all jobs**).
Після зелених перевірок запустяться `Publish images` і `Deploy to the server`. Останній крок перевіряє, що
`https://<домен>/up` відповідає.

Відкрийте `https://<домен>`, зареєструйтесь і додайте книгу.

## Повсякденне

- **Нова версія:** мерж у `main`, більше нічого.
- **Відкат:** відкрийте старий успішний прогін у Actions і натисніть **Re-run**, або на сервері:
  `bash /opt/bookshelf/rollout.sh ghcr.io/sashacn <sha коміту>`. Образ має бути вже завантажений на сервер або доступний у реєстрі
  після `docker login ghcr.io`. Міграції автоматично не відкочуються.
- **Бекап бази:** `/opt/bookshelf/backup.sh` робить стиснений дамп у `/opt/bookshelf/backups` і видаляє дампи старші
  за 14 днів. Щоб запускалось щоночі, виконайте `crontab -e` і додайте рядок `0 3 * * * /opt/bookshelf/backup.sh`.
  Дампи лежать на тому ж диску, тож раз на якийсь час забирайте їх до себе: `scp -i ~/.ssh/bookshelf_deploy azureuser@<IP>:/opt/bookshelf/backups/* .`
- **Логи:** `docker compose --env-file production.env -f docker-compose.prod.yml logs -f app caddy`.

## Витрати й обмеження

- 750 годин на місяць покривають одну машину, що працює цілодобово. Дві такі машини вже вийдуть за ліміт.
- Пам'яті мало: на свіжому сервері з 842 МБ уже зайнято близько 430 (ОС і Docker). Після першого деплою перегляньте `free -m`; частина стеку піде в swap, і це нормально, доки сайт відповідає. Якщо swap росте постійно, потрібна машина з більшою пам'яттю.
- Диск, публічна IP-адреса й вихідний трафік списуються з кредиту $100. Слідкуйте за бюджетом із кроку 1.
- Через 12 місяців безкоштовна частина закінчується: або платите за машину, або переїжджаєте на інший хостинг.
  Образи й `docker-compose.prod.yml` працюють на будь-якому сервері з Docker, тож переїзд це нова машина, `bootstrap-server.sh`
  і нові значення секретів.

## Якщо щось не працює

| Симптом | Що перевірити |
|---|---|
| Крок `Publish images` не стартує | Змінна `DEPLOY_ENABLED` має бути рівно `true`, а пуш іти в `main` |
| `exec format error` у логах `app` | Образ зібраний під іншу архітектуру, ніж сервер (образи x64, див. примітку в кроці 3) |
| Валідація падає з `RequestDisallowedByAzure` | Регіон не входить у дозволені для підписки (див. таблицю в кроці 3). Оберіть Denmark East |
| У списку розмірів усе сіре («Unsupported availability zone», «Size not available») | Поставте Availability options = No infrastructure redundancy required, перевірте, що VM architecture = x64, спробуйте інший з п'яти дозволених регіонів |
| Контейнер `mysql` перезапускається або сервер «вішається» | Ймовірно, не вистачає пам'яті: `free -m`, `docker stats`; перевірте, що swap увімкнений (`swapon --show`) |
| Сайт не відкривається, у `caddy` помилки сертифіката | Домен має вказувати на IP сервера, а порти 80 і 443 бути відкриті в Azure (Networking → Inbound rules) |
| Крок `Roll out` падає на `docker login` або `pull` | Перевірте, що пакети `bookshelf-app` і `bookshelf-web` зʼявились у Packages репозиторію й привʼязані до нього |
| Крок `Copy the deployment files` падає з кодом 255 | Дивіться анотацію червоного кроку на сторінці прогону (її видно без входу в GitHub): там повний текст помилки ssh. Типові причини: порожній або неправильно названий секрет, неповний `DEPLOY_SSH_KEY`, чужий відбиток у `DEPLOY_KNOWN_HOSTS`, закритий порт 22 в Azure |
| `Permission denied (publickey)` у анотації помилки | `DEPLOY_SSH_KEY` має містити приватний ключ повністю, а публічний має бути в VM |
| `Host key verification failed` у анотації помилки | Оновіть `DEPLOY_KNOWN_HOSTS` (він змінюється, якщо створити машину заново) |
| Вхід у застосунок не тримається | `production.env` має містити правильний `APP_DOMAIN`; сесійна кука працює лише по HTTPS |
