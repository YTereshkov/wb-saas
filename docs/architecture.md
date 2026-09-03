# SellerScope — архитектура приложения

Статус: утверждённая архитектурная основа Demo-MVP  
Последнее обновление: 14 августа 2026 года

## 1. Назначение документа

Этот документ фиксирует целевую архитектуру SellerScope на период разработки
Demo-MVP и последующего подключения реального Wildberries API. Он описывает
границы приложения, модулей, данных, фоновых процессов и frontend.

Документ не означает, что все перечисленные компоненты уже реализованы. Работа
выполняется поэтапно, без создания слоёв и классов раньше реальной потребности.

Источники истины:

- [UX/UI Specification](design/ux-ui-spec.md) — продуктовые сценарии,
  терминология и поведение;
- [Design System](design/design-system.md) и утверждённые mockups — внешний вид
  и состояния интерфейса;
- [Метрики и формулы](analytics/metrics.md) — семантика аналитических
  показателей;
- `AGENTS.md` — постоянные правила разработки и контроля scope.

При противоречии реализация не выбирает вариант молча: конфликт фиксируется и
выносится на согласование.

## 2. Архитектурные принципы

SellerScope — модульный монолит:

- один Git-репозиторий;
- одно Laravel-приложение;
- backend и React frontend находятся в одной кодовой базе;
- одна PostgreSQL является источником истины;
- Redis используется для очередей и краткоживущего cache;
- deployment выполняется как единое приложение с отдельными runtime-процессами
  web, queue worker и scheduler.

Базовый стек:

- PHP 8.4 и Laravel 13;
- Inertia.js 3, React 19 и TypeScript;
- Tailwind CSS 4, адаптированный shadcn/ui и Recharts;
- PostgreSQL 17;
- Redis 7.4;
- Laravel Queue, Horizon и Scheduler;
- Docker Compose для локального окружения.

В MVP не создаются отдельный REST/GraphQL API для собственного frontend,
микросервисы, CQRS, Event Sourcing или универсальный repository layer над
Eloquent.

## 3. Поток HTTP-запроса

```text
HTTP request
→ route + middleware
→ FormRequest + Policy
→ thin Controller
→ Application Query или Action
→ Eloquent / Analytics / Synchronization
→ page-specific ViewModel
→ Inertia props
→ React Page
```

Ответственность уровней:

- middleware устанавливает пользователя и общий аналитический контекст;
- FormRequest валидирует input и выполняет request-level authorization;
- Policy защищает tenant-resource;
- Controller принимает запрос и вызывает один прикладной use case;
- Action изменяет состояние приложения;
- Query читает данные и формирует готовую модель конкретного экрана;
- domain/application service содержит формулы или координацию, которую нельзя
  корректно разместить в модели;
- React отображает authoritative props и хранит только временное UI-состояние.

Контроллеры не содержат аналитику, синхронизацию или сложные Eloquent-запросы.
Eloquent-модели и сырые payload Wildberries не передаются в Inertia напрямую.

## 4. Модули backend

Целевая структура:

```text
app/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Modules/
│   ├── Identity/
│   ├── SellerAccounts/
│   ├── Catalog/
│   ├── Sales/
│   ├── Inventory/
│   ├── Analytics/
│   ├── Wildberries/
│   ├── Synchronization/
│   └── Notifications/
└── Support/
```

Внутри модуля создаются только реально используемые каталоги `Models`,
`Actions`, `Queries`, `Data`, `Jobs`, `Contracts` и `Services`. Пустая
унифицированная структура заранее не генерируется.

| Модуль          | Ответственность                                                                          |
| --------------- | ---------------------------------------------------------------------------------------- |
| Identity        | Регистрация, пароль, magic link, email verification, сессии и профиль пользователя       |
| Seller Accounts | Кабинеты продавца, активный кабинет, credentials, WB permissions и lifecycle подключения |
| Catalog         | Товары, категории, изображения и внешние идентификаторы WB                               |
| Sales           | Заказы, состоявшиеся продажи и возвраты как отдельные факты                              |
| Inventory       | Склады, снимки остатков, движение, покрытие и рекомендации поставки                      |
| Analytics       | KPI, сравнение периодов, временные ряды, вклад товаров и главный сигнал                  |
| Wildberries     | Client contract, demo/HTTP реализации, DTO, normalizers и классификация ошибок           |
| Synchronization | Sync-runs, cursors, orchestration, импорт и запуск агрегации                             |
| Notifications   | Настройки событий, email-уведомления и история доставки                                  |

Billing, Teams, Organizations, Admin, Exports и самостоятельный Recommendations
module не входят в MVP.

## 5. Внутренние контракты

Стабильные границы вводятся там, где они изолируют источник данных или
повторяющуюся семантику:

- `WildberriesClientInterface` — нормализованная граница внешнего источника;
- `AnalyticsPeriod` — текущий и сравнительный диапазоны, timezone и
  granularity;
- `PrimarySignalData` — тип, приоритет, факт, риск и рекомендация;
- `SyncStatusData` — состояние, доступность ресурсов, прогресс и timestamps;
- `PaginatedResultData<T>` — результат backend pagination;
- page-specific ViewModel и соответствующий TypeScript-тип для каждого
  Inertia-экрана.

Контракты не используются как повод создать repository для каждой модели.

## 6. Маршруты и Inertia-страницы

Все пользовательские маршруты находятся в `web.php`, используют session
authentication, CSRF и Inertia. Фильтры, sorting, pagination и выбранный view
передаются через query string.

| Method и route                                   | Controller/action                          | Inertia Page или результат                                    |
| ------------------------------------------------ | ------------------------------------------ | ------------------------------------------------------------- |
| `GET /`                                          | `EntryPointController@index`               | redirect: verification, connection, initial sync или overview |
| `GET /login`                                     | `AuthenticatedSessionController@create`    | `Auth/Login`                                                  |
| `POST /login`                                    | `AuthenticatedSessionController@store`     | redirect в приложение                                         |
| `POST /login/magic-link`                         | `MagicLinkController@store`                | Login с success-state                                         |
| `GET /login/magic-link/{token}`                  | `MagicLinkController@consume`              | одноразовый вход и redirect                                   |
| `GET /register`                                  | `RegisteredUserController@create`          | `Auth/Register`                                               |
| `POST /register`                                 | `RegisteredUserController@store`           | redirect на email verification                                |
| `GET /email/verify`                              | `EmailVerificationController@notice`       | `Auth/VerifyEmail`                                            |
| `GET /email/verify/{id}/{hash}`                  | `EmailVerificationController@verify`       | redirect к подключению                                        |
| `GET /forgot-password`                           | `PasswordResetLinkController@create`       | `Auth/ForgotPassword`                                         |
| `POST /forgot-password`                          | `PasswordResetLinkController@store`        | success-state                                                 |
| `GET /reset-password/{token}`                    | `NewPasswordController@create`             | `Auth/ResetPassword`                                          |
| `POST /reset-password/{token}`                   | `NewPasswordController@store`              | redirect на login                                             |
| `GET /overview`                                  | `OverviewController@index`                 | `Overview/Index`                                              |
| `GET /products`                                  | `ProductController@index`                  | `Products/Index`, view `all`                                  |
| `GET /products/attention`                        | `ProductController@attention`              | `Products/Index`, view `attention`                            |
| `GET /products/decline`                          | `ProductController@decline`                | `Products/Index`, view `decline`                              |
| `GET /products/low-stock`                        | `ProductController@lowStock`               | `Products/Index`, view `low-stock`                            |
| `GET /products/{product}`                        | `ProductDetailsController@overview`        | `Products/Show/Overview`                                      |
| `GET /products/{product}/sales`                  | `ProductDetailsController@sales`           | `Products/Show/SalesBuyout`                                   |
| `GET /products/{product}/stocks`                 | `ProductDetailsController@stocks`          | `Products/Show/Stocks`                                        |
| `GET /sales`                                     | `SalesController@dynamics`                 | `Sales/Dynamics`                                              |
| `GET /sales/orders-sales`                        | `SalesController@ordersSales`              | `Sales/OrdersSales`                                           |
| `GET /sales/buyout-returns`                      | `SalesController@buyoutReturns`            | `Sales/BuyoutReturns`                                         |
| `GET /sales/products`                            | `SalesController@products`                 | `Sales/Products`                                              |
| `GET /stocks`                                    | `StockController@index`                    | `Stocks/Index`                                                |
| `GET /stocks/supply`                             | `StockController@supply`                   | `Stocks/Supply`                                               |
| `GET /stocks/out-of-stock`                       | `StockController@outOfStock`               | `Stocks/OutOfStock`                                           |
| `GET /stocks/no-movement`                        | `StockController@noMovement`               | `Stocks/NoMovement`                                           |
| `GET /settings/cabinets`                         | `CabinetController@index`                  | `Settings/Cabinets/Index`                                     |
| `GET /settings/cabinets/connect`                 | `CabinetConnectionController@create`       | `Settings/Cabinets/Connect/Token`                             |
| `POST /settings/cabinets/connect/verify`         | `CabinetConnectionController@verify`       | redirect на Verification                                      |
| `GET /settings/cabinets/{cabinet}/verification`  | `CabinetConnectionController@verification` | `Settings/Cabinets/Connect/Verification`                      |
| `POST /settings/cabinets/{cabinet}/initial-sync` | `CabinetConnectionController@startSync`    | redirect на Loading                                           |
| `GET /settings/cabinets/{cabinet}/initial-sync`  | `CabinetConnectionController@loading`      | `Settings/Cabinets/Connect/Loading`                           |
| `GET /settings/cabinets/{cabinet}`               | `CabinetController@show`                   | `Settings/Cabinets/Show`                                      |
| `PATCH /settings/cabinets/{cabinet}`             | `CabinetController@update`                 | redirect back                                                 |
| `POST /settings/cabinets/{cabinet}/sync`         | `CabinetSyncController@store`              | redirect back                                                 |
| `PUT /settings/cabinets/{cabinet}/credentials`   | `CabinetCredentialController@update`       | redirect back                                                 |
| `DELETE /settings/cabinets/{cabinet}`            | `CabinetController@disconnect`             | redirect к списку                                             |
| `GET /settings/notifications`                    | `NotificationSettingsController@index`     | `Settings/Notifications`                                      |
| `PATCH /settings/notifications`                  | `NotificationSettingsController@update`    | redirect back                                                 |
| `GET /settings/profile`                          | `ProfileController@edit`                   | `Settings/Profile`                                            |
| `PATCH /settings/profile`                        | `ProfileController@update`                 | redirect back                                                 |
| `PUT /settings/profile/password`                 | `PasswordController@update`                | redirect back                                                 |
| `DELETE /settings/profile/sessions`              | `SessionController@destroyOthers`          | redirect back                                                 |
| `PATCH /preferences/analytics-context`           | `AnalyticsContextController@update`        | redirect на текущий экран                                     |

Ссылка на товар сохраняет browser history. При прямом открытии карточки fallback
для возврата ведёт в `/products`.

Согласованные правила навигации:

- подключение кабинета выполняется отдельным трёхшаговым wizard;
- inline token form не размещается в списке кабинетов;
- на всех страницах Settings видны три вкладки: «Кабинеты», «Уведомления» и
  «Профиль»;
- email verification завершается до подключения первого кабинета.

## 7. Frontend

Целевая структура:

```text
resources/js/
├── Pages/
│   ├── Auth/
│   ├── Overview/
│   ├── Products/
│   ├── Sales/
│   ├── Stocks/
│   └── Settings/
├── Layouts/
│   ├── AppLayout.tsx
│   ├── AuthLayout.tsx
│   └── SettingsLayout.tsx
├── Features/
│   ├── AnalyticsContext/
│   ├── ProductTable/
│   ├── ProductDetails/
│   ├── SalesAnalytics/
│   ├── StockAnalytics/
│   ├── CabinetConnection/
│   └── Synchronization/
├── Components/
│   ├── ui/
│   ├── data-display/
│   ├── charts/
│   ├── tables/
│   ├── filters/
│   └── feedback/
├── Types/
└── Lib/
```

Активный кабинет, период, comparison и sync-state передаются как shared
Inertia props. Выбор пользователя сохраняется в `user_preferences`, а контекст
конкретного экрана отражается в URL.

React хранит только временное состояние: открытый dropdown, draft фильтров,
выбранные строки и состояние локального overlay. Authoritative KPI, временные
ряды, counters и главный сигнал приходят с backend.

Смена периода загружает единый согласованный набор props. Inertia partial reload
не используется для атомарной смены периода; он допустим для повтора одной
ошибочной секции или ограниченного обновления sync-status.

Обязательные состояния data-heavy экранов: skeleton, empty, filtered-empty,
section error, stale, partial sync, missing permissions и disconnected account.

Responsive-диапазоны и точные визуальные правила определены в
`docs/design/`. Компоненты shadcn/ui используются как primitives и не заменяют
утверждённый дизайн SellerScope.

## 8. Модель данных

Каждая tenant-таблица содержит `seller_account_id`. Внешние WB identifiers
хранятся как строки и не используются как внутренние route IDs.

| Сущность                  | Ключевые данные и связи                                                                           |
| ------------------------- | ------------------------------------------------------------------------------------------------- |
| `User`                    | Имя, email, password hash, email verification; владеет несколькими кабинетами                     |
| `PasswordlessLoginToken`  | Hash токена, expiry и `used_at`; принадлежит User                                                 |
| `SellerAccount`           | Имя, строковый WB ID, source, status, timezone и sync timestamps; принадлежит User                |
| `SellerAccountCredential` | Encrypted token, fingerprint, доступные секции и `verified_at`; один активный credential кабинета |
| `UserPreference`          | Активный кабинет, период и comparison пользователя                                                |
| `SavedView`               | Раздел, filters, columns, sorting и page size для User + SellerAccount                            |
| `Category`                | Нормализованная категория в границе SellerAccount                                                 |
| `Product`                 | nmID, vendor code, title, brand, category, image reference и active status                        |
| `Warehouse`               | Внешний ID, название и тип в границе SellerAccount                                                |
| `Order`                   | `srid`, product, `ordered_at`, quantity, amount, cancellation/status                              |
| `Sale`                    | Внешний ID, `srid`, product, `sold_at`, amount и optional Order                                   |
| `ReturnFact`              | Внешний ID, product, `returned_at`, amount и optional Sale                                        |
| `StockSnapshot`           | Product, Warehouse, snapshot date/time и quantity                                                 |
| `ImportBatch`             | Source, resource, range, cursor, status, checksum и SyncRun                                       |
| `RawImportPage`           | Временный JSONB payload и page metadata для real API replay                                       |
| `SyncRun`                 | Тип, status, progress, started/finished и safe error summary                                      |
| `SyncResourceState`       | Resource, cursor, last success и availability кабинета                                            |
| `AccountDailyMetric`      | Revenue, orders, sales и returns по business date                                                 |
| `ProductDailyMetric`      | Те же показатели по Product и business date                                                       |
| `ProductSignal`           | Приоритетный сигнал, evidence, recommendation и `calculated_at`                                   |
| `NotificationPreference`  | Event, threshold, enabled и channel для User + SellerAccount                                      |
| `NotificationDelivery`    | Signal, status, `sent_at` и safe error для User + SellerAccount                                   |

Правила хранения:

- деньги хранятся целыми копейками;
- доли хранятся как decimal или basis points, но не binary float;
- timestamps хранятся в UTC, business dates вычисляются в timezone кабинета;
- unique constraints внешних идентификаторов всегда включают кабинет;
- raw payload имеет ограниченный срок хранения и не читается UI;
- отключение кабинета меняет status и останавливает sync, но не удаляет данные
  немедленно.

Поток данных:

```text
Raw WB page
→ canonical DTO
→ Product / Order / Sale / ReturnFact / StockSnapshot
→ daily aggregates + ProductSignal
→ Application Query
→ Inertia ViewModel
→ React
```

## 9. Tenant isolation и безопасность

Один пользователь MVP владеет несколькими кабинетами. Organization и roles не
создаются.

Обязательные правила:

- доступ к tenant-resource проверяется Policy или эквивалентным backend guard;
- route model binding дополнительно ограничивается кабинетами пользователя;
- любой ID из request считается недоверенным;
- controller, query и job проверяют или получают уже авторизованный
  `seller_account_id`;
- подмена ID возвращает `404` или `403`, но никогда не раскрывает чужие данные;
- React не получает credentials, raw payload или internal error;
- session cookies используют secure production settings, HTTP-only и SameSite;
- state-changing web routes защищены CSRF;
- login, registration, reset, magic link и credential verification имеют rate
  limiting.

Email подтверждается до подключения Wildberries. Magic link одноразовый,
хранится в виде hash и действует 15 минут. Password reset действует 30 минут.
После смены пароля остальные сессии завершаются.

WB token хранится в `TEXT` через Laravel encrypted cast. Рядом сохраняется
только необратимый fingerprint. Token не попадает в job payload, Inertia props
или logs; job загружает credential по внутреннему ID. Для production требуется
отдельный порядок ротации `APP_KEY` с повторным шифрованием credentials.

WB hosts задаются конфигурацией приложения и не принимаются из пользовательского
input.

## 10. Wildberries integration

Контракт источника:

```text
WildberriesClientInterface
├── checkConnection()
├── fetchProducts(cursor)
├── fetchOrders(period, cursor)
├── fetchSales(period, cursor)
└── fetchStocks(cursor)
```

Реализации:

- `DemoWildberriesClient` возвращает стабильные canonical DTO из
  детерминированного dataset;
- `WildberriesHttpClient` обращается к разрешённым WB hosts, временно сохраняет
  raw pages и преобразует их через отдельные normalizers;
- `WildberriesClientResolver` выбирает реализацию по `SellerAccount.source`.

UI, application queries и Analytics не знают, какая реализация выбрана.

Состояния подключения:

```text
pending
→ verified
→ initial_sync
→ active
   ├── partial
   ├── invalid_credentials
   └── disconnected
```

Initial sync:

```text
Products
→ Orders + Sales + Stocks
→ normalization / idempotent upsert
→ daily aggregates
→ primary signals
→ SellerAccount becomes active
```

Требования надёжности:

- один sync одного resource для кабинета выполняется без overlap;
- повторный импорт продолжает работу по cursor и использует idempotent upsert;
- `401/403` переводит credentials в invalid state;
- `429` учитывает endpoint-specific retry/reset headers;
- timeout и `5xx` получают ограниченный exponential backoff с jitter;
- schema/record error фиксируется как permanent для конкретной записи или
  страницы и не запускает бесконечный retry;
- logs содержат только `seller_account_id`, `sync_run_id`, resource и safe error
  code.

Оперативные отчёты WB не считаются постоянным историческим хранилищем.
SellerScope сохраняет собственные нормализованные факты и регулярные stock
snapshots.

## 11. Synchronization и фоновые задачи

Очереди Redis:

| Queue           | Назначение                           |
| --------------- | ------------------------------------ |
| `sync`          | Внешние запросы и импорт             |
| `analytics`     | Daily aggregates, signals и counters |
| `notifications` | Создание и отправка email delivery   |
| `maintenance`   | Pruning, stale checks и recovery     |

Группы jobs:

1. orchestration initial и incremental sync;
2. импорт products, orders, sales и stocks;
3. retry временных ошибок и продолжение с cursor;
4. rebuild account/product daily metrics и signals;
5. notifications по новому или изменившемуся критическому сигналу;
6. pruning raw imports, stale-account checks и recovery зависших sync-runs.

Jobs используют unique/encrypted capabilities Laravel, middleware для overlap и
rate limiting, ограниченные attempts и явные timeout. Notifications подавляются
во время initial sync.

Scheduler только выбирает кабинеты, которым пора обновиться, и dispatch-ит
jobs. Долгая работа внутри scheduler не выполняется. Horizon предназначен для
мониторинга Redis queues после его подключения на соответствующем этапе.

## 12. Analytics и согласованность данных

Семантика и примеры находятся в [metrics.md](analytics/metrics.md).

Заранее рассчитываются:

- `AccountDailyMetric`;
- `ProductDailyMetric`;
- актуальные `ProductSignal`;
- counters вкладок;
- последний stock snapshot;
- ежедневное значение запасов, сохранённое SellerScope.

По запросу рассчитываются KPI выбранного диапазона, comparison, day/week
grouping, фильтры, sorting, pagination, вклад товара и актуальный stock forecast.

После успешного импорта затронутые daily metrics и signals перестраиваются через
queue. Ночной safety-rebuild повторно считает последние изменённые дни. Backfill
используется после исправления normalizer или формулы.

Redis cache не является источником истины. Cache key включает кабинет, период,
filters и data revision. Успешная синхронизация увеличивает revision, поэтому
старые результаты больше не читаются.

## 13. Ошибки и наблюдаемость

Пользователь получает понятное состояние data section или sync, но не stack
trace и не детали внешнего payload.

Application logs структурированы вокруг:

- `seller_account_id`;
- `sync_run_id`;
- resource;
- safe error code;
- attempt и duration, когда применимо.

Секреты, authorization headers и raw payload в обычные logs не записываются.
Ошибки отдельных resources допускают состояние partial sync вместо потери всей
страницы.

## 14. Тестирование

Backend Feature tests защищают authentication, verification, magic link,
основные Inertia routes/props, connection lifecycle, query-string context и
tenant isolation.

Unit tests используются для формул, signal priority, stock forecast,
normalizers, error classification, idempotency keys и cursor progression.

Один contract test запускается для Demo и HTTP Wildberries clients. HTTP client
проверяется с безопасными fixtures на pagination, `429`, `401/403`, timeout,
`5xx` и schema drift. Live smoke-test допускается только вручную с отдельным test
credential.

Frontend typecheck и production build обязательны. Component tests добавляются
для компонентов с собственной логикой, а не ради coverage.

Критические E2E-сценарии:

- registration → verification → connection → initial sync → overview;
- смена кабинета и периода;
- signal → filtered list → product → back;
- settings и disconnect confirmation;
- desktop/mobile navigation, keyboard focus и отсутствие console errors.

UI-этап считается завершённым только после сравнения реального экрана с
применимыми утверждёнными mockups. Базовые проверяемые viewport: `1536×1024`,
`1280×800`, `1024×768`, `768×1024` и `390×844`.

## 15. Границы MVP и дальнейшее развитие

Не входят в Demo-MVP:

- реальный Wildberries HTTP client;
- production deployment;
- billing, teams, organizations и roles;
- admin panel, exports и public API;
- дополнительные notification channels.

Переход к реальному WB начинается только после стабильного Demo-MVP: сначала API
discovery и sanitized fixtures, затем catalog, incremental orders/sales,
returns, stock snapshots, reconciliation и ограниченный rollout. Demo и real
accounts продолжают использовать те же normalized entities, Analytics queries и
React pages.
