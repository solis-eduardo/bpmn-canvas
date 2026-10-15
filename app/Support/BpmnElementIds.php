<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;

/**
 * The ids of a BPMN diagram's model elements (tasks, gateways, flows, ...),
 * not those of its diagram-interchange shapes. A Comment Thread anchored to an
 * element is orphaned once its id is no longer among them (ADR-0022).
 */
class BpmnElementIds
{
    private const MODEL_NAMESPACE = 'http://www.omg.org/spec/BPMN/20100524/MODEL';

    /**
     * Empty for content that can't be parsed: a broken file must not make
     * every Thread look healthy, but it must not fail a listing either.
     *
     * @return list<string>
     */
    public static function extract(string $bpmnXml): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $document->loadXML($bpmnXml)) {
                return [];
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $nodes = (new DOMXPath($document))->query("//*[@id and namespace-uri()='".self::MODEL_NAMESPACE."']/@id");
        $ids = [];

        foreach ($nodes ?: [] as $node) {
            $ids[$node->nodeValue] = true;
        }

        return array_map('strval', array_keys($ids));
    }
}
