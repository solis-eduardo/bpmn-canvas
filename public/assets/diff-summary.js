/*
 * Turns the raw result of compareBpmnXml() into the categorised list shown in
 * the comparison's summary panel: elements added, removed, renamed, moved and
 * reconnected. Pure and DOM-free, so it is unit-tested on its own.
 */

/**
 * @typedef {{id: string, type: string, name?: string, oldName?: string}} SummaryEntry
 */

const entry = model => ({ id: model.id, type: model.$type, name: model.name });

/**
 * @param {{added: object, removed: object, changed: object, layoutChanged: object}} result
 * @returns {{added: SummaryEntry[], removed: SummaryEntry[], renamed: SummaryEntry[], moved: SummaryEntry[], reconnected: SummaryEntry[]}}
 */
export function summarizeDiff({ added, removed, changed, layoutChanged }) {
  const renamed = [];
  const reconnected = [];

  Object.values(changed).forEach(({ model, attrs }) => {
    if (attrs.name) renamed.push({ ...entry(model), oldName: attrs.name.oldValue });
    if (attrs.sourceRef || attrs.targetRef) reconnected.push(entry(model));
  });

  return {
    added: Object.values(added).map(entry),
    removed: Object.values(removed).map(entry),
    renamed,
    // An element that is new or gone has no position to compare.
    moved: Object.values(layoutChanged).filter(m => !added[m.id] && !removed[m.id]).map(entry),
    reconnected,
  };
}
