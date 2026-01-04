<?php

declare(strict_types=1);

namespace Hudsxn\Canvas;

use Hudsxn\Canvas\Contracts\CanvasPageRenderer;
use Hudsxn\Canvas\Objects\Page;

/**
 * Handles the complete HTTP response lifecycle for Canvas pages.
 *
 * Response orchestrates the rendering process and manages HTTP response
 * delivery, including content compression, header generation, and security
 * policy enforcement. It bridges the gap between your Page object and the
 * final HTTP response sent to the client.
 *
 * ## Purpose
 * Response is responsible for:
 * - **Rendering**: Converting Page objects to HTML using the configured renderer
 * - **Compression**: Automatic gzip compression for faster page loads
 * - **Headers**: Setting appropriate HTTP headers (Content-Type, CSP, caching, etc.)
 * - **Security**: Applying security policies from the Page configuration
 * - **Performance**: Optimizing response delivery with compression and caching
 *
 * ## Key Features
 * - Automatic content negotiation for gzip compression
 * - Configurable compression levels and encoding support
 * - Security header generation from Page flags
 * - SEO header generation from Page metadata
 * - Flexible rendering via dependency injection
 *
 * ## Usage Examples
 *
 * ### Basic Usage
 * ```php
 * $page = new Page();
 * $page->setTitle('Welcome');
 * 
 * $renderer = new BladeRenderer();
 * $response = new Response($renderer, $page);
 * $response->send(); // Renders and sends to browser
 * ```
 *
 * ### Getting HTML Without Sending
 * ```php
 * $response = new Response($renderer, $page);
 * $html = $response->render(); // Just get the HTML string
 * file_put_contents('output.html', $html);
 * ```
 *
 * ### Configuring Compression
 * ```php
 * $response = new Response($renderer, $page);
 * $response->setCompressionLevel(9) // Maximum compression
 *          ->enableCompression();
 * $response->send();
 * ```
 *
 * ### Disabling Compression
 * ```php
 * // Useful for debugging or when proxy handles compression
 * $response = new Response($renderer, $page);
 * $response->enableCompression(false);
 * $response->send();
 * ```
 *
 * ### Custom Status Codes and Headers
 * ```php
 * $response = new Response($renderer, $page);
 * $response->setStatusCode(404)
 *          ->addHeader('X-Custom-Header', 'value')
 *          ->send();
 * ```
 *
 * ### Complete Example with All Features
 * ```php
 * $page = new Page()
 *     ->setTitle('Secure Page')
 *     ->enableCsp()
 *     ->allowScriptFrom("'self'")
 *     ->forceHttps()
 *     ->noCache();
 * 
 * $response = new Response(new BladeRenderer(), $page);
 * $response->setCompressionLevel(6)
 *          ->setStatusCode(200)
 *          ->addHeader('X-Frame-Options', 'DENY')
 *          ->send();
 * ```
 *
 * @package Hudsxn\Canvas
 */
class Response
{
    /**
     * The renderer used to convert Page objects to HTML.
     *
     * @var CanvasPageRenderer
     */
    private CanvasPageRenderer $renderer;

    /**
     * The Page object being rendered.
     *
     * @var Page
     */
    private Page $page;

    /**
     * Whether gzip compression is enabled.
     *
     * @var bool
     */
    private bool $compressionEnabled = true;

    /**
     * Gzip compression level (1-9, where 9 is maximum compression).
     *
     * Higher values produce smaller files but take longer to compress.
     * Level 6 is a good balance between speed and compression ratio.
     *
     * @var int
     */
    private int $compressionLevel = 6;

    /**
     * Supported content encodings for compression.
     *
     * @var string[]
     */
    private array $supportedEncodings = [
        'gzip',
    ];

    /**
     * HTTP status code for the response.
     *
     * @var int
     */
    private int $statusCode = 200;

    /**
     * Additional custom headers to send.
     *
     * @var array<string, string>
     */
    private array $customHeaders = [];

    /**
     * Creates a new Response instance.
     *
     * @param CanvasPageRenderer $renderer The renderer to use for generating HTML
     * @param Page $page The page to render
     *
     * @example
     * ```php
     * $response = new Response(
     *     new BladeRenderer(),
     *     $page
     * );
     * ```
     */
    public function __construct(CanvasPageRenderer $renderer, Page $page)
    {
        $this->renderer = $renderer;
        $this->page     = $page;
    }

    /* -----------------------------------------------------------------
     |  Configuration Methods
     | -----------------------------------------------------------------
     */

    /**
     * Enables or disables gzip compression.
     *
     * Compression is enabled by default and can reduce HTML size by 70-90%.
     * Disable it when debugging or if compression is handled by a reverse proxy.
     *
     * @param bool $enabled Whether to enable compression (default: true)
     * @return self Returns this response for method chaining
     *
     * @example
     * ```php
     * // Disable for debugging
     * $response->enableCompression(false);
     * 
     * // Re-enable
     * $response->enableCompression(true);
     * ```
     */
    public function enableCompression(bool $enabled = true): self
    {
        $this->compressionEnabled = $enabled;

        return $this;
    }

    /**
     * Sets the gzip compression level.
     *
     * @param int $level Compression level (1-9)
     *                   1 = fastest, least compression
     *                   6 = balanced (default)
     *                   9 = slowest, maximum compression
     * @return self Returns this response for method chaining
     *
     * @example
     * ```php
     * // Maximum compression for static assets
     * $response->setCompressionLevel(9);
     * 
     * // Fast compression for dynamic content
     * $response->setCompressionLevel(3);
     * ```
     */
    public function setCompressionLevel(int $level): self
    {
        $this->compressionLevel = max(1, min(9, $level));

        return $this;
    }

    /**
     * Sets the supported compression encodings.
     *
     * Currently only 'gzip' is supported. This method exists for
     * future extensibility (brotli, deflate, etc.).
     *
     * @param string[] $encodings Array of encoding names
     * @return self Returns this response for method chaining
     *
     * @example
     * ```php
     * $response->setSupportedEncodings(['gzip']);
     * ```
     */
    public function setSupportedEncodings(array $encodings): self
    {
        $this->supportedEncodings = $encodings;

        return $this;
    }

    /**
     * Sets the HTTP status code for this response.
     *
     * @param int $code HTTP status code (e.g., 200, 404, 500)
     * @return self Returns this response for method chaining
     *
     * @example
     * ```php
     * // Not found page
     * $response->setStatusCode(404);
     * 
     * // Server error
     * $response->setStatusCode(500);
     * 
     * // Created
     * $response->setStatusCode(201);
     * ```
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;

        return $this;
    }

    /**
     * Adds a custom HTTP header.
     *
     * @param string $name Header name
     * @param string $value Header value
     * @return self Returns this response for method chaining
     *
     * @example
     * ```php
     * $response->addHeader('X-Frame-Options', 'DENY')
     *          ->addHeader('X-Content-Type-Options', 'nosniff')
     *          ->addHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
     * ```
     */
    public function addHeader(string $name, string $value): self
    {
        $this->customHeaders[$name] = $value;

        return $this;
    }

    /**
     * Adds multiple custom headers at once.
     *
     * @param array<string, string> $headers Associative array of headers
     * @return self Returns this response for method chaining
     *
     * @example
     * ```php
     * $response->addHeaders([
     *     'X-Frame-Options' => 'DENY',
     *     'X-Content-Type-Options' => 'nosniff',
     *     'X-XSS-Protection' => '1; mode=block'
     * ]);
     * ```
     */
    public function addHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->customHeaders[$name] = $value;
        }

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Rendering Methods
     | -----------------------------------------------------------------
     */

    /**
     * Renders the page to an HTML string.
     *
     * This method executes the rendering pipeline and returns the final HTML
     * output with compression applied if enabled. Use this when you need the
     * HTML string without sending it as an HTTP response.
     *
     * @return string The rendered HTML (compressed if enabled)
     *
     * @example
     * ```php
     * // Get HTML for saving to file
     * $html = $response->render();
     * file_put_contents('page.html', $html);
     * 
     * // Get HTML for testing
     * $html = $response->render();
     * $this->assertStringContainsString('<title>Test</title>', $html);
     * ```
     */
    public function render(): string
    {
        $sourceCode = [];
        $this->renderer->generateHtml($this->page, $sourceCode);

        $output = implode("\n", $sourceCode);

        return $this->applyCompression($output);
    }

    /**
     * Renders the page and sends it as an HTTP response.
     *
     * This method executes the complete response pipeline:
     * 1. Renders the page using the configured renderer
     * 2. Applies compression if enabled and supported
     * 3. Sends appropriate HTTP headers
     * 4. Outputs the final HTML
     *
     * @return void
     *
     * @example
     * ```php
     * // Simple usage
     * $response->send();
     * 
     * // With configuration
     * $response->setStatusCode(200)
     *          ->enableCompression()
     *          ->send();
     * ```
     */
    public function send(): void
    {
        $output = $this->render();

        $this->sendHeaders(strlen($output));

        echo $output;
    }

    /* -----------------------------------------------------------------
     |  Compression Methods
     | -----------------------------------------------------------------
     */

    /**
     * Applies compression to the output if enabled and supported.
     *
     * @param string $output The uncompressed HTML
     * @return string The compressed or original output
     */
    private function applyCompression(string $output): string
    {
        if (!$this->compressionEnabled) {
            return $output;
        }

        $encoding = $this->negotiateEncoding();

        if ($encoding === null) {
            return $output;
        }

        return match ($encoding) {
            'gzip' => gzencode($output, $this->compressionLevel),
            default => $output,
        };
    }

    /**
     * Negotiates content encoding with the client.
     *
     * Checks the Accept-Encoding header to determine which compression
     * method the client supports and sets appropriate response headers.
     *
     * @return string|null The negotiated encoding, or null if none available
     */
    private function negotiateEncoding(): ?string
    {
        if (
            !isset($_SERVER['HTTP_ACCEPT_ENCODING']) ||
            !function_exists('gzencode')
        ) {
            return null;
        }

        $accept = strtolower($_SERVER['HTTP_ACCEPT_ENCODING']);

        foreach ($this->supportedEncodings as $encoding) {
            if (
                $encoding === 'gzip' &&
                str_contains($accept, 'gzip')
            ) {
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');

                return 'gzip';
            }
        }

        return null;
    }

    /* -----------------------------------------------------------------
     |  Header Generation Methods
     | -----------------------------------------------------------------
     */

    /**
     * Sends all HTTP headers for the response.
     *
     * This includes:
     * - Status code
     * - Content-Type with charset
     * - Security headers (CSP, X-Frame-Options, etc.)
     * - Caching headers
     * - SEO headers (robots meta)
     * - Custom headers
     *
     * @param int $contentLength The content length in bytes
     * @return void
     */
    private function sendHeaders(int $contentLength): void
    {
        // Status code
        http_response_code($this->statusCode);

        // Content-Type
        header('Content-Type: text/html; charset=' . $this->page->getCharset());

        // Only send Content-Length when NOT compressed
        if (!$this->compressionEnabled) {
            header('Content-Length: ' . $contentLength);
        }

        // Security headers
        $this->sendSecurityHeaders();

        // SEO headers
        $this->sendSeoHeaders();

        // Caching headers
        $this->sendCachingHeaders();

        // Custom headers
        foreach ($this->customHeaders as $name => $value) {
            header("$name: $value");
        }
    }

    /**
     * Sends security-related headers based on Page configuration.
     *
     * @return void
     */
    private function sendSecurityHeaders(): void
    {
        // Content Security Policy
        if ($this->page->isCspEnabled()) {
            $cspHeader = $this->buildCspHeader();
            if ($cspHeader !== '') {
                header("Content-Security-Policy: $cspHeader");
            }
        }

        // HTTPS enforcement
        if ($this->hasPageFlag(Page::FORCE_HTTPS)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Common security headers (always sent)
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Sends SEO-related headers.
     *
     * @return void
     */
    private function sendSeoHeaders(): void
    {
        $robotsDirectives = [];

        if ($this->hasPageFlag(Page::NO_INDEX)) {
            $robotsDirectives[] = 'noindex';
        }

        if ($this->hasPageFlag(Page::NO_FOLLOW)) {
            $robotsDirectives[] = 'nofollow';
        }

        if (!empty($robotsDirectives)) {
            header('X-Robots-Tag: ' . implode(', ', $robotsDirectives));
        }
    }

    /**
     * Sends caching-related headers.
     *
     * @return void
     */
    private function sendCachingHeaders(): void
    {
        if ($this->hasPageFlag(Page::NO_CACHE)) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }

    /**
     * Builds the Content-Security-Policy header value.
     *
     * @return string The CSP header value
     */
    private function buildCspHeader(): string
    {
        $csp = $this->page->getCsp();
        $directives = [];

        foreach ($csp as $directive => $values) {
            if (is_array($values)) {
                $directives[] = $directive . ' ' . implode(' ', $values);
            } elseif ($values === '') {
                $directives[] = $directive;
            } else {
                $directives[] = $directive . ' ' . $values;
            }
        }

        return implode('; ', $directives);
    }

    /**
     * Checks if a specific Page flag is set.
     *
     * @param int $flag The flag constant to check
     * @return bool True if the flag is set
     */
    private function hasPageFlag(int $flag): bool
    {
        // Use reflection or a public method to check flags
        // For now, we'll add a public helper to Page class
        return ($this->getPageFlags() & $flag) === $flag;
    }

    /**
     * Gets the flags from the Page (helper method).
     *
     * Note: This assumes Page has a public method to access flags.
     * If not, you'll need to add one to the Page class:
     * public function getFlags(): int { return $this->flags; }
     *
     * @return int The page flags
     */
    private function getPageFlags(): int
    {
        $pageArray = $this->page->toArray();
        return $pageArray['flags'] ?? 0;
    }
}