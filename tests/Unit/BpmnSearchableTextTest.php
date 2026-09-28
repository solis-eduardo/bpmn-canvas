<?php

namespace Tests\Unit;

use App\Support\BpmnSearchableText;
use PHPUnit\Framework\TestCase;

class BpmnSearchableTextTest extends TestCase
{
    public function test_extracts_task_event_gateway_lane_and_pool_names(): void
    {
        $xml = <<<'BPMN'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:collaboration id="Collaboration_1">
    <bpmn:participant id="Participant_1" name="Purchasing Department" processRef="Process_1" />
  </bpmn:collaboration>
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:laneSet id="LaneSet_1">
      <bpmn:lane id="Lane_1" name="Buyer" />
    </bpmn:laneSet>
    <bpmn:startEvent id="StartEvent_1" name="Request Received" />
    <bpmn:userTask id="Task_1" name="Approve Purchase Order" />
    <bpmn:exclusiveGateway id="Gateway_1" name="Is Budget Available?" />
    <bpmn:endEvent id="EndEvent_1" name="Order Placed" />
  </bpmn:process>
</bpmn:definitions>
BPMN;

        $text = BpmnSearchableText::extract($xml);

        foreach (['Purchasing Department', 'Buyer', 'Request Received', 'Approve Purchase Order', 'Is Budget Available?', 'Order Placed'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
    }

    public function test_ignores_elements_without_a_name(): void
    {
        $xml = <<<'BPMN'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
  </bpmn:process>
</bpmn:definitions>
BPMN;

        $this->assertSame('', BpmnSearchableText::extract($xml));
    }

    public function test_deduplicates_repeated_names(): void
    {
        $xml = <<<'BPMN'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:task id="Task_1" name="Review" />
    <bpmn:task id="Task_2" name="Review" />
  </bpmn:process>
</bpmn:definitions>
BPMN;

        $this->assertSame('Review', BpmnSearchableText::extract($xml));
    }

    public function test_returns_an_empty_string_for_invalid_xml_instead_of_throwing(): void
    {
        $this->assertSame('', BpmnSearchableText::extract('not xml at all'));
    }
}
