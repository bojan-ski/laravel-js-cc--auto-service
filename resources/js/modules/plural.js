export function pluralize(element, count) {
    const category = new Intl.PluralRules(document.documentElement.lang).select(count);
    const templates = {
        one: element.dataset.pluralOne,
        few: element.dataset.pluralFew,
        other: element.dataset.pluralOther,
    };

    return (templates[category] ?? templates.other ?? '').replace(':count', count);
}
