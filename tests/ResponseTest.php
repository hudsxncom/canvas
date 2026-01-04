<?php

use PHPUnit\Framework\TestCase;
use Hudsxn\Canvas\Response;
use Hudsxn\Canvas\Objects\Page;

require_once __DIR__ . '/DummyRenderer.php';

final class ResponseTest extends TestCase
{
    /**
     * @description Renderer output lines are correctly imploded.
     */
    public function testResponseImplodesLines(): void
    {
        $renderer = new DummyRenderer(['line1', 'line2']);
        $page     = new Page();

        $response = new Response($renderer, $page);

        $this->assertSame("line1\nline2", $response->render());
    }

    /**
     * @description Compression can be explicitly disabled.
     */
    public function testCompressionDisabled(): void
    {
        $renderer = new DummyRenderer(['hello']);
        $page     = new Page();

        $response = (new Response($renderer, $page))
            ->enableCompression(false);

        $this->assertSame('hello', $response->render());
    }

    /**
     * @description Gzip compression is applied when supported by client and server.
     */
    public function testGzipCompression(): void
    {
        if (!function_exists('gzencode')) {
            $this->markTestSkipped('zlib not available');
        }

        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $renderer = new DummyRenderer(['hello']);
        $page     = new Page();

        $response = new Response($renderer, $page);
        $output   = $response->render();

        $this->assertSame('hello', gzdecode($output));
    }

    /**
     * @description Output is not compressed when client does not support gzip.
     */
    public function testNoGzipWhenClientDoesNotSupport(): void
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'identity';

        $renderer = new DummyRenderer(['hello']);
        $page     = new Page();

        $response = new Response($renderer, $page);

        $this->assertSame('hello', $response->render());
    }
}
