import { BpmnModdle } from 'bpmn-moddle';
import { Linter } from 'bpmnlint';
import { describe, expect, it } from 'vitest';

import { createLinting, lintConfig } from '../../resources/js/editor-lint.js';

const diagram = body => `<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" id="Defs">
  <bpmn:process id="P" isExecutable="false">${body}</bpmn:process>
</bpmn:definitions>`;

async function lint(body) {
  const { rootElement } = await new BpmnModdle().fromXML(diagram(body));

  return new Linter(lintConfig).lint(rootElement);
}

const GOOD = `
  <bpmn:startEvent id="S" name="Begin"><bpmn:outgoing>F1</bpmn:outgoing></bpmn:startEvent>
  <bpmn:task id="T" name="Do it"><bpmn:incoming>F1</bpmn:incoming><bpmn:outgoing>F2</bpmn:outgoing></bpmn:task>
  <bpmn:endEvent id="E" name="Finish"><bpmn:incoming>F2</bpmn:incoming></bpmn:endEvent>
  <bpmn:sequenceFlow id="F1" sourceRef="S" targetRef="T" />
  <bpmn:sequenceFlow id="F2" sourceRef="T" targetRef="E" />`;

describe('Editor modeling checks', () => {
  it('reports nothing for a well-formed process', async () => {
    expect(await lint(GOOD)).toEqual({});
  });

  it('reports a disconnected element, a missing start and end event and an unlabeled task', async () => {
    const reports = await lint('<bpmn:task id="T" />');

    expect(Object.keys(reports)).toEqual(expect.arrayContaining([
      'no-disconnected', 'start-event-required', 'end-event-required', 'label-required',
    ]));
  });

  it('reports a gateway that is entered but has no outgoing flow', async () => {
    const reports = await lint(`
      <bpmn:startEvent id="S"><bpmn:outgoing>F1</bpmn:outgoing></bpmn:startEvent>
      <bpmn:exclusiveGateway id="G"><bpmn:incoming>F1</bpmn:incoming></bpmn:exclusiveGateway>
      <bpmn:sequenceFlow id="F1" sourceRef="S" targetRef="G" />`);

    expect(reports['gateway-outgoing-required'].map(r => r.id)).toEqual(['G']);
  });

  it('reports every finding as a warning, never an error', async () => {
    const reports = await lint('<bpmn:task id="T" />');
    const categories = new Set(Object.values(reports).flat().map(r => r.category));

    expect([...categories]).toEqual(['warn']);
  });

  it('hands the Modeler the lint module and the rules, active by default', () => {
    const { modules, options } = createLinting();

    expect(modules).toHaveLength(1);
    expect(options.linting.bpmnlint).toBe(lintConfig);
    expect(options.linting.active).toBe(true);
    expect(createLinting({ active: false }).options.linting.active).toBe(false);
  });
});
