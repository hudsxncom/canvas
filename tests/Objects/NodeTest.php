<?php

use PHPUnit\Framework\TestCase;
use Hudsxn\Canvas\Objects\Node;

require_once __DIR__ . '/../DummyRenderer.php';

final class NodeTest extends TestCase
{
    /**
     * @description Node stores and exposes its name and props correctly.
     */
    public function testNodeNameAndProps(): void
    {
        $node = new Node('div', ['class' => 'box']);

        $this->assertSame('div', $node->getName());
        $this->assertSame('box', $node->getProp('class'));
    }

    /**
     * @description getProps returns a copy to prevent external mutation.
     */
    public function testPropsAreReturnedAsCopy(): void
    {
        $node = new Node('div', ['id' => 'a']);

        $props = $node->getProps();
        $props['id'] = 'b';

        $this->assertSame('a', $node->getProp('id'));
    }

    /**
     * @description Adding a child correctly sets parent and child relations.
     */
    public function testAddChildSetsParent(): void
    {
        $parent = new Node('parent');
        $child  = new Node('child');

        $parent->addChild($child);

        $this->assertSame($parent, $child->getParent());
        $this->assertCount(1, $parent->getChildren());
    }
}
