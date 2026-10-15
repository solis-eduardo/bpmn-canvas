/*
 * The Editor's modeling checks (ADR-0027): bpmnlint's recommended rules plus
 * ours, wired into the Modeler through bpmn-js-bpmnlint. Shared by both
 * builds: the standalone build imports this module, the server build loads
 * its Vite bundle (the server page runs the vendored, unbundled bpmn-js).
 *
 * Every rule reports as a warning: the checks never block editing or saving.
 */
import lintModule from 'bpmn-js-bpmnlint';
import recommended from 'bpmnlint/config/recommended.js';

import gatewayOutgoingRequired from './lint/gateway-outgoing-required.js';

// bpmnlint's own rules, keyed by rule name (rules/<name>.js); helper.js is not a rule.
const bundled = import.meta.glob(['../../node_modules/bpmnlint/rules/*.js', '!**/helper.js'], { eager: true });

const ruleFactories = {
  'gateway-outgoing-required': gatewayOutgoingRequired,
};
for (const [path, module] of Object.entries(bundled)) {
  ruleFactories[path.split('/').pop().replace(/\.js$/, '')] = module.default;
}

// no-bpmndi is left out: the Modeler draws from the diagram interchange, so a
// diagram that opens on the canvas always has it.
const ruleNames = [
  ...Object.keys(recommended.rules).filter(name => name !== 'no-bpmndi'),
  'gateway-outgoing-required',
];

export const lintConfig = {
  config: { rules: Object.fromEntries(ruleNames.map(name => [name, 'warn'])) },
  resolver: {
    resolveRule: (pkg, name) => ruleFactories[name],
    resolveConfig: () => { throw new Error('Rule configurations are bundled, not resolved'); },
  },
};

/**
 * What createEditor() takes as `lint`: the Modeler module and its options.
 *
 * @param {Object} [options]
 * @param {boolean} [options.active]  whether the checks start shown on the canvas
 */
export function createLinting({ active = true } = {}) {
  return {
    modules: [lintModule],
    options: { linting: { bpmnlint: lintConfig, active } },
  };
}
