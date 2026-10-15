/**
 * A gateway that is entered but never left strands the process: the token
 * stops there. Gateways with no connections at all are left to bpmnlint's
 * `no-disconnected`.
 *
 * @type {import('bpmnlint').RuleFactory}
 */
export default function gatewayOutgoingRequired() {
  function check(node, reporter) {
    if (!node.$instanceOf('bpmn:Gateway')) return;

    if ((node.incoming || []).length && !(node.outgoing || []).length) {
      reporter.report(node.id, 'Gateway has no outgoing sequence flow');
    }
  }

  return { check };
}
