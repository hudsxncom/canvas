<?php

use PHPUnit\Framework\TestCase;
use Hudsxn\Canvas\Objects\Page;

require_once __DIR__ . '/../DummyRenderer.php';

final class PageTest extends TestCase
{

    /**
     * @description Page fluent API returns the same instance.
     */
    public function testFluentApi(): void
    {
        $page = new Page();

        $result = $page
            ->setTitle('Test')
            ->setLocale('en-GB')
            ->noIndex();

        $this->assertSame($page, $result);
    }

    /**
     * @description Bitwise flags are correctly set and unset.
     */
    public function testBitwiseFlags(): void
    {
        $page = new Page();

        $page->noIndex(true);
        $this->assertTrue(($page->toArray()['flags'] & Page::NO_INDEX) !== 0);

        $page->noIndex(false);
        $this->assertFalse(($page->toArray()['flags'] & Page::NO_INDEX) !== 0);
    }
}
