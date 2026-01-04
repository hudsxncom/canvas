<?php

use PHPUnit\Framework\TestCase;
use Hudsxn\Canvas\Objects\Canvas;

require_once __DIR__ . '/../DummyRenderer.php';

final class CanvasTest extends TestCase
{
    /**
     * @description Canvas stores template and behaves as a specialised Node.
     */
    public function testCanvasTemplate(): void
    {
        $canvas = new Canvas('layouts/app');

        $this->assertSame('layouts/app', $canvas->getTemplate());
        $this->assertSame('canvas', $canvas->getName());
    }

    /**
     * @description Canvas serialization includes template metadata.
     */
    public function testCanvasToArrayIncludesTemplate(): void
    {
        $canvas = new Canvas('layout');

        $array = $canvas->toArray();

        $this->assertArrayHasKey('template', $array);
        $this->assertSame('layout', $array['template']);
    }
}
