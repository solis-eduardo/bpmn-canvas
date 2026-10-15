import { describe, expect, it } from 'vitest';
import { summarizeDiff } from '../../public/assets/diff-summary.js';

const model = (id, $type, name) => ({ id, $type, name });
const change = (m, attrs) => ({ model: m, attrs });

describe('summarizeDiff', () => {
  it('lists added and removed elements with their id, type and name', () => {
    const summary = summarizeDiff({
      added: { T1: model('T1', 'bpmn:UserTask', 'Review') },
      removed: { E1: model('E1', 'bpmn:EndEvent') },
      changed: {},
      layoutChanged: {},
    });

    expect(summary.added).toEqual([{ id: 'T1', type: 'bpmn:UserTask', name: 'Review' }]);
    expect(summary.removed).toEqual([{ id: 'E1', type: 'bpmn:EndEvent', name: undefined }]);
  });

  it('reports a name change as renamed, keeping the previous name', () => {
    const summary = summarizeDiff({
      added: {}, removed: {}, layoutChanged: {},
      changed: { T1: change(model('T1', 'bpmn:Task', 'New'), { name: { oldValue: 'Old', newValue: 'New' } }) },
    });

    expect(summary.renamed).toEqual([{ id: 'T1', type: 'bpmn:Task', name: 'New', oldName: 'Old' }]);
    expect(summary.reconnected).toEqual([]);
  });

  it('reports a changed source or target as reconnected', () => {
    const summary = summarizeDiff({
      added: {}, removed: {}, layoutChanged: {},
      changed: {
        F1: change(model('F1', 'bpmn:SequenceFlow'), { targetRef: { oldValue: '#ref:B', newValue: '#ref:A' } }),
        F2: change(model('F2', 'bpmn:SequenceFlow'), { sourceRef: { oldValue: '#ref:B', newValue: '#ref:A' } }),
      },
    });

    expect(summary.reconnected.map(e => e.id)).toEqual(['F1', 'F2']);
    expect(summary.renamed).toEqual([]);
  });

  it('reports a layout change as moved, except for elements added or removed anyway', () => {
    const summary = summarizeDiff({
      added: { N: model('N', 'bpmn:Task') },
      removed: { G: model('G', 'bpmn:Task') },
      changed: {},
      layoutChanged: { M: model('M', 'bpmn:Task', 'Moved'), N: model('N', 'bpmn:Task'), G: model('G', 'bpmn:Task') },
    });

    expect(summary.moved).toEqual([{ id: 'M', type: 'bpmn:Task', name: 'Moved' }]);
  });

  it('lists an element under every category that applies to it', () => {
    const summary = summarizeDiff({
      added: {}, removed: {},
      changed: { T1: change(model('T1', 'bpmn:Task', 'B'), { name: { oldValue: 'A', newValue: 'B' } }) },
      layoutChanged: { T1: model('T1', 'bpmn:Task', 'B') },
    });

    expect(summary.renamed).toHaveLength(1);
    expect(summary.moved).toHaveLength(1);
  });

  it('ignores attribute changes that are neither a rename nor a reconnection', () => {
    const summary = summarizeDiff({
      added: {}, removed: {}, layoutChanged: {},
      changed: { T1: change(model('T1', 'bpmn:Task'), { documentation: { oldValue: 'a', newValue: 'b' } }) },
    });

    expect(summary).toEqual({ added: [], removed: [], renamed: [], moved: [], reconnected: [] });
  });
});
