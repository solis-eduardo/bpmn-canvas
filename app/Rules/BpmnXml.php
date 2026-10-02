<?php

namespace App\Rules;

use Closure;
use DOMDocument;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts only well-formed BPMN 2.0 XML: a `definitions` root element in the
 * BPMN model namespace, with no DOCTYPE and no entity declarations.
 *
 * Diagram content is later embedded in the public Share page and fed to
 * XML parsers (bpmn-js, BpmnSearchableText), so a DTD — the way to declare
 * entities (XXE, "billion laughs") — has no legitimate use here and is
 * rejected outright instead of being parsed.
 */
class BpmnXml implements ValidationRule
{
    public const BPMN_MODEL_NAMESPACE = 'http://www.omg.org/spec/BPMN/20100524/MODEL';

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->isValid($value)) {
            $fail('messages.invalid_bpmn_xml')->translate();
        }
    }

    private function isValid(string $xml): bool
    {
        // Cheap pre-check, before any parser sees the input. Not the only
        // guard: the doctype check below covers encodings this misses.
        if (preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $xml) === 1) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument;

            // Deliberately no LIBXML_NOENT / LIBXML_DTDLOAD: entities are
            // never substituted. LIBXML_NONET blocks network access.
            $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            $hasErrors = collect(libxml_get_errors())
                ->contains(fn ($error) => $error->level >= LIBXML_ERR_ERROR);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || $hasErrors || $dom->doctype !== null) {
            return false;
        }

        $root = $dom->documentElement;

        return $root !== null
            && $root->localName === 'definitions'
            && $root->namespaceURI === self::BPMN_MODEL_NAMESPACE;
    }
}
