const dayPlural = new Intl.PluralRules('ru-RU');
const dayForms: Record<Intl.LDMLPluralRule, string> = {
    zero: 'дней',
    one: 'день',
    two: 'дня',
    few: 'дня',
    many: 'дней',
    other: 'дня',
};

export function formatDays(days: number) {
    return `${days} ${dayForms[dayPlural.select(days)]}`;
}
