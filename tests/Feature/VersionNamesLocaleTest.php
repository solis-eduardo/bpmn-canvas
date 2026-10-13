<?php

namespace Tests\Feature;

use App\Support\VersionName;
use Tests\TestCase;

/**
 * A Version without a custom name is called "Version N" in the page's language.
 */
class VersionNamesLocaleTest extends TestCase
{
    public function test_the_default_version_name_follows_the_locale(): void
    {
        $expected = ['en' => 'Version 3', 'pt_BR' => 'Versão 3', 'es' => 'Versión 3'];

        foreach ($expected as $locale => $name) {
            app()->setLocale($locale);

            $this->assertSame($name, VersionName::default(3));
        }
    }

    public function test_every_locale_translates_the_bpmn_element_types_of_the_removed_elements_list(): void
    {
        foreach (['en', 'pt_BR', 'es'] as $locale) {
            $types = __('messages.bpmn_element_types', [], $locale);

            $this->assertIsArray($types);
            $this->assertArrayHasKey('UserTask', $types);
            $this->assertArrayHasKey('SequenceFlow', $types);
        }

        $this->assertSame('Tarefa', __('messages.bpmn_element_types', [], 'pt_BR')['Task']);
    }
}
