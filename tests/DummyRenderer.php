<?php

use Hudsxn\Canvas\Contracts\CanvasPageRenderer;
use Hudsxn\Canvas\Objects\Page;

final class DummyRenderer implements CanvasPageRenderer
{
    /**
     * @var string[]
     */
    private array $lines;

    public function __construct(array $lines)
    {
        $this->lines = $lines;
    }

    public function generateHtml(Page $page, array &$sourceCode): void
    {
        foreach ($this->lines as $line) {
            $sourceCode[] = $line;
        }
    }
}
