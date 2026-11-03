# SonarQube: статичний аналіз і покриття

Аналіз читає код (баги, вразливості, code smells, дублювання) і показує покриття тестами зі звітів Pest та Vitest.
Налаштування лежать у `sonar-project.properties`. Є два способи запуску, вони не залежать один від одного.

## Локально (Docker)

```bash
make sonar-up      # SonarQube + Postgres, перший запуск 1-2 хвилини
```

1. Відкрийте <http://localhost:9002>, увійдіть як `admin` / `admin` і змініть пароль.
2. Створіть токен: *My Account → Security → Generate Tokens* (тип User або Global Analysis).
3. Запустіть аналіз:

```bash
SONAR_TOKEN=<токен> make sonar
```

`make sonar` спочатку збирає покриття (`make coverage`), потім запускає сканер. Результат: проєкт **Bookshelf** на
<http://localhost:9002/dashboard?id=bookshelf>. Зупинка: `make sonar-down` (дані зберігаються). Інший порт: `SONAR_PORT=9010`.

## У CI (GitHub Actions)

Job `sonar` у `.github/workflows/ci.yml` пропускається, доки не задано змінну репозиторію `SONAR_ENABLED=true`.

1. Створіть проєкт на <https://sonarcloud.io> (SonarQube Cloud), імпортувавши репозиторій з GitHub.
2. У проєкті вимкніть *Automatic Analysis* (*Administration → Analysis Method*), інакше буде конфлікт з CI.
3. У GitHub: *Settings → Secrets and variables → Actions*:
   - secret `SONAR_TOKEN` - токен з SonarQube Cloud;
   - variables `SONAR_ORGANIZATION`, `SONAR_PROJECT_KEY` (показані в налаштуваннях проєкту) і `SONAR_ENABLED=true`.
4. Для власного сервера замість Cloud додайте змінну `SONAR_HOST_URL`.

Pull request з форків не отримують секрети, тому для них аналіз пропускається.
