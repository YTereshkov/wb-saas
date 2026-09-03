# Wildberries API contract matrix

Дата discovery: 22 августа 2026 года.

Документ фиксирует внешний контракт для этапов 26–29. Источник истины по
endpoint, scope и лимитам — официальная документация WB API. Sanitized fixtures
находятся в `tests/Fixtures/Wildberries`; реальных токенов и payload продавцов в
репозитории нет.

## Endpoints MVP

| Resource | Endpoint | Token category | Pagination/checkpoint | Ограничение источника |
|---|---|---|---|---|
| Seller | `GET https://common-api.wildberries.ru/api/v1/seller-info` | любая | нет | 1 запрос в минуту; возвращает `sid` и имя продавца |
| Connection | `GET /ping` на Content, Statistics и Analytics hosts | соответствующая категория | нет | не использовать как health polling; только при проверке credential |
| Products | `POST https://content-api.wildberries.ru/content/v2/get/cards/list` | Content или Promotion | `cursor.updatedAt + cursor.nmID`, до 100 карточек | карточки из корзины отсутствуют |
| Orders | `GET https://statistics-api.wildberries.ru/api/v1/supplier/orders` | Statistics | `lastChangeDate`; boundary IDs дедуплицируются | preliminary; обновление около 30 минут; история гарантирована не более 90 дней; до 80 000 строк |
| Sales/returns | `GET https://statistics-api.wildberries.ru/api/v1/supplier/sales` | Statistics | `lastChangeDate`; boundary IDs дедуплицируются | preliminary; цены могут уточняться до 24 часов; для финансовой сверки нужен realization report |
| Stocks | `POST https://seller-analytics-api.wildberries.ru/api/analytics/v1/stocks-report/wb-warehouses` | Analytics, Personal/Service | `offset`, до 250 000 строк | только текущий снимок; обновление около 30 минут; 1 строка = размер + склад |
| Stock history | `POST/GET /api/v2/nm-report/downloads`, `GET /api/v2/nm-report/downloads/file/{downloadId}` | Analytics | UUID отчёта, async status, ZIP/CSV | до 3 месяцев; снимок на 23:59; готовый файл хранится 48 часов; до 20 отчётов в сутки |

Legacy `GET /api/v1/supplier/stocks` не используется: WB объявил его отключение
23 июня 2026 года. SellerScope сохраняет собственные snapshots. При первом
подключении отдельная encrypted queue-job запрашивает `STOCK_HISTORY_DAILY_CSV`,
ждёт готовность без блокировки initial sync и идемпотентно импортирует историю.

## Canonical mapping

- `nmID`, `warehouseId`, `chrtId`, `srid`, `saleID` сохраняются как strings.
- Денежная база operational reports — `finishedPrice`, перевод в целые копейки.
- `saleID`, начинающийся с `R`, нормализуется в `ReturnFact`; остальные записи —
  в `Sale`. Связь возврата с продажей выполняется по `srid`.
- `lastChangeDate` является incremental checkpoint, но не бизнес-датой факта.
- Stocks сохраняются по `product + warehouse + chrtId + snapshot_at`; UI и
  analytics суммируют размеры до товара/склада.
- Исторический CSV содержит динамические колонки `DD.MM.YYYY`. SellerScope
  разворачивает их в отдельные snapshots на 23:59 timezone кабинета. Backfill
  заканчивается вчерашним днём, поэтому текущий WB snapshot не удваивается.
- CSV даёт агрегированный `OfficeName`, но не warehouse ID. Для history rows
  создаётся стабильный synthetic external ID; текущие snapshots сохраняют
реальные `warehouseId` и `regionName`.
- Время без offset из Statistics интерпретируется в `Europe/Moscow`, хранение — UTC.

## Sandbox tokens

Токен с JWT claim `t: true` не отправляется на production hosts. Для него
`WildberriesSandboxClient` использует только:

- `https://content-api-sandbox.wildberries.ru` — товары;
- `https://statistics-api-sandbox.wildberries.ru` — заказы, продажи и возвраты.

У тестового контура нет совместимых `common-api` и `seller-analytics-api`, поэтому
seller ID берётся из подписанного WB-токена и подтверждается успешными `/ping` на
обоих sandbox hosts. Внутри SellerScope ID хранится как `sandbox:<sid>`, чтобы
тестовый и production-кабинеты одного продавца не заменяли друг друга. Префикс
никогда не показывается пользователю.

Stocks и stock history помечаются `unavailable`, initial sync завершается успешно
со статусом кабинета `partial`. WB выдаёт тестовые токены с чтением и записью,
однако SellerScope вызывает только методы чтения. Production `X-Client-Secret`
в sandbox не отправляется. Для sandbox Statistics параметр `dateFrom` передаётся
как `YYYY-MM-DD`: тестовый контур отклоняет production-формат ISO 8601 с offset.
Тестовые наборы Content и Statistics могут быть не связаны: заказ или продажа
иногда ссылается на `nmID`, отсутствующий в sandbox-каталоге. Только для sandbox
SellerScope создаёт временную карточку `Товар WB <nmID>`; последующий импорт
Content заменяет её реальными данными. В production неизвестный товар остаётся
ошибкой целостности и не маскируется.

## Error policy

| HTTP/transport | Classification | Поведение |
|---|---|---|
| `401`, `403` | `invalid_credentials` | credential инвалидируется, кабинет получает соответствующий status |
| `429` | `rate_limited` | job освобождается на `Retry-After`, fallback 60 секунд |
| timeout/connection, `5xx` | retryable upstream error | ограниченный queue retry с backoff |
| `400`, `402`, `404`, `422` | permanent request error | resource/run завершается безопасной ошибкой |
| несовместимая schema | `schema_drift` | импорт страницы останавливается; ранее принятые raw pages остаются для диагностики |
| CSV report `WAITING/PROCESSING/RETRY` | `historical_report_pending` | job освобождается на 20 секунд без polling в HTTP request |

Token не входит в job payload, logs, raw pages или Inertia props. WB hosts берутся
только из server configuration. Запросы используют `Authorization: Bearer`.
Для облачного SellerScope нужен Service Token продавца и выданный WB сервисный
секрет; последний задаётся только серверной переменной `WB_CLIENT_SECRET` и
передаётся как `X-Client-Secret`.

## Official sources

- https://dev.wildberries.ru/openapi/api-information
- https://dev.wildberries.ru/sandbox
- https://dev.wildberries.ru/docs/openapi/work-with-products
- https://dev.wildberries.ru/en/docs/openapi/reports
- https://dev.wildberries.ru/en/openapi/analytics

## Operations

Incremental synchronization реальных кабинетов запускается scheduler каждые
пять минут для кабинетов, чей последний успешный sync старше настроенного
cadence (по умолчанию 30 минут). Временные ошибки используют bounded exponential
backoff с jitter, а 429 следует retry headers WB. Порядок сверки и ограниченного
rollout описан в docs/operations/wildberries-rollout.md.
- https://dev.wildberries.ru/en/news/302
- https://dev.wildberries.ru/en/news/283
