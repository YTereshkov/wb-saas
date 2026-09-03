<?php

namespace App\Modules\Notifications;

final class NotificationEvents
{
    /** @var array<string, array{label: string, description: string, threshold: int|null, unit: string|null}> */
    public const DEFINITIONS = [
        'low_stock' => ['label' => 'Товар скоро закончится', 'description' => 'Сообщить, когда прогнозируемого запаса останется меньше заданного срока.', 'threshold' => 7, 'unit' => 'days'],
        'sales_decline' => ['label' => 'Продажи резко снизились', 'description' => 'Сравниваем продажи товара с предыдущим сопоставимым периодом.', 'threshold' => 20, 'unit' => 'percent'],
        'returns_growth' => ['label' => 'Возвраты выросли', 'description' => 'Сообщить о заметном росте доли возвратов.', 'threshold' => 3, 'unit' => 'points'],
        'sync_failed' => ['label' => 'Ошибка синхронизации', 'description' => 'Если новые данные кабинета не удалось загрузить.', 'threshold' => null, 'unit' => null],
        'daily_digest' => ['label' => 'Сводка показателей', 'description' => 'Краткая выручка, продажи и товары, требующие внимания.', 'threshold' => null, 'unit' => null],
    ];

    private function __construct() {}
}
