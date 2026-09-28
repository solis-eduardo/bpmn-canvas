<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;

/**
 * Extracts the searchable text of a BPMN diagram — the names of its tasks,
 * events, gateways, lanes and pools (CONTEXT.md; issue #41) — so a User can
 * find the process where an activity appears by searching for those names,
 * without exposing any BPMN/XML mechanics to callers.
 */
class BpmnSearchableText
{
    /**
     * Local (namespace-stripped) names of the BPMN elements whose `name`
     * attribute counts as searchable content.
     *
     * @var list<string>
     */
    private const ELEMENT_NAMES = [
        // Tasks (including containers that behave like one: subProcess,
        // callActivity).
        'task', 'userTask', 'serviceTask', 'scriptTask', 'manualTask',
        'sendTask', 'receiveTask', 'businessRuleTask', 'callActivity', 'subProcess',
        // Events.
        'startEvent', 'endEvent', 'intermediateCatchEvent', 'intermediateThrowEvent', 'boundaryEvent',
        // Gateways.
        'exclusiveGateway', 'parallelGateway', 'inclusiveGateway', 'eventBasedGateway', 'complexGateway',
        // Lanes and pools.
        'lane', 'participant',
    ];

    /**
     * Extracts every named element's `name` attribute, deduplicated and
     * joined into one plain-text blob suitable for storing and searching.
     * Returns an empty string for content that can't be parsed as XML,
     * rather than throwing — a Diagram's searchable text is best-effort,
     * never a reason to fail a save.
     */
    public static function extract(string $bpmnXml): string
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);

        try {
            if (! $document->loadXML($bpmnXml)) {
                return '';
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $localNames = implode(' or ', array_map(fn (string $name) => "local-name()='{$name}'", self::ELEMENT_NAMES));
        $nodes = $xpath->query("//*[{$localNames}]/@name");

        if ($nodes === false) {
            return '';
        }

        $names = [];

        foreach ($nodes as $node) {
            $value = trim($node->nodeValue ?? '');

            if ($value !== '') {
                $names[$value] = $value;
            }
        }

        return implode(' ', $names);
    }
}
