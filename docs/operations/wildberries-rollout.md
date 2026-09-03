# Ограниченный rollout Wildberries

Документ описывает безопасное подключение первого тестового WB-кабинета и
обязательную сверку данных перед расширением доступа.

## Предварительные условия

- Используется отдельный тестовый кабинет, владелец которого дал согласие.
- Token вводится только через connection wizard SellerScope.
- WB_CLIENT_SECRET и другие server secrets заданы через environment.
- PostgreSQL, Redis, queue worker и scheduler проходят health checks.
- В очереди нет старых failed jobs, а backlog ниже настроенного threshold.

## Первый запуск

1. Подключить кабинет через /settings/cabinets/connect.
2. Дождаться завершения initial sync и отдельного stock-history backfill.
3. Проверить, что кабинет имеет статус active, а resources — completed.
4. Записать внутренний ID кабинета. Token для команд не нужен.
5. Выполнить сверку за доступные интервалы:

    php artisan sellerscope:reconcile ACCOUNT_ID --start=2026-08-16 --end=2026-08-22 --fail-on-drift
    php artisan sellerscope:reconcile ACCOUNT_ID --start=2026-07-24 --end=2026-08-22 --fail-on-drift
    php artisan sellerscope:reconcile ACCOUNT_ID --start=2026-07-01 --end=2026-07-31 --fail-on-drift

Фактические даты заменяются на последние 7 дней, последние 30 дней и предыдущий
полный календарный месяц в timezone кабинета. Для автоматизации доступен --json.

## Критерии допуска

- Все восемь checks каждой сверки имеют статус OK.
- KPI Overview, Sales и Products совпадают для одинакового периода.
- Нет invalid_credentials, зависших sync-runs и failed resources.
- Текущий stock total совпадает с последним импортированным WB snapshot.
- Повторный incremental sync не создаёт дубликатов.
- В логах отсутствуют token, raw authorization headers и payload продавца.
- 429 освобождает job до серверного retry time; временные ошибки получают
  ограниченный exponential backoff.

Operational reports WB предварительные. Сверка SellerScope подтверждает
внутреннюю целостность import → normalized facts → aggregates. Финансовое
совпадение с отчётом реализации проверяется отдельно после добавления этого
источника.

## Наблюдение

- sellerscope:sync-due выбирает только активные real-кабинеты с валидным
  credential; стандартная частота — каждые 30 минут.
- sellerscope:recover-stalled-syncs закрывает зависшие runs безопасной ошибкой.
- queue:monitor пишет warning при превышении backlog threshold.
- Raw WB pages удаляются после retention period командой
  sellerscope:prune-raw-imports.
- Horizon dashboard доступен только email из HORIZON_ALLOWED_EMAILS; scheduler
  сохраняет snapshots каждые пять минут, supervisors разделены по очередям.

## Остановка rollout

При любой устойчивой ошибке:

1. Отключить кабинет через Settings. Это прекращает новые scheduled sync без
   удаления нормализованных данных.
2. Остановить расширение rollout.
3. Зафиксировать только seller_account_id, sync_run_id, resource и безопасный
   error code.
4. Исправить normalizer/formula и пересобрать aggregates.
5. Повторить все сверки до нулевого drift.

Удаление данных или credential для остановки rollout не требуется.
