<?php

namespace Hudsxn\Canvas\Renderers;

use Hudsxn\Canvas\Contracts\CanvasPageRenderer;
use Hudsxn\Canvas\Objects\Page;

/**
 * Renders pages as Single Page Application shells with JSON state injection.
 *
 * This renderer generates a minimal HTML shell designed for client-side frameworks
 * like React, Vue, or Angular. It serializes the page state to JSON and injects it
 * into the document for client-side hydration, while maintaining CSP compliance
 * through nonce-based script execution.
 *
 * ## Purpose
 * - Generate lightweight HTML shells for SPAs
 * - Inject serialized page state for client-side hydration
 * - Maintain Content Security Policy compliance with nonces
 * - Support external JavaScript and CSS bundles
 * - Provide viewport and mobile-optimized defaults
 *
 * ## Key Features
 * - **Nonce-based CSP**: Generates unique nonces per render for inline scripts
 * - **Configurable Assets**: Control JavaScript and CSS bundle URLs
 * - **State Injection**: Serializes complete page state to `window.__PAGE_STATE__`
 * - **SEO Metadata**: Includes standard meta tags for search engines and social media
 * - **Mobile Optimized**: Includes viewport configuration by default
 *
 * ## Usage Examples
 *
 * ### Basic SPA Shell
 * ```php
 * $renderer = new SinglePageApplicationRenderer();
 * $renderer->setJsUrl('/dist/app.js')
 *          ->setCssUrl('/dist/app.css');
 * 
 * $response = new Response($renderer, $page);
 * $response->send();
 * ```
 *
 * ### With Multiple CSS Files
 * ```php
 * $renderer = new SinglePageApplicationRenderer();
 * $renderer->setJsUrl('/dist/app.js')
 *          ->setCssUrls([
 *              '/dist/vendor.css',
 *              '/dist/app.css',
 *              'https://fonts.googleapis.com/css2?family=Inter'
 *          ]);
 * ```
 *
 * ### With Multiple JS Files (Vendor + App)
 * ```php
 * $renderer = new SinglePageApplicationRenderer();
 * $renderer->setJsUrls([
 *     '/dist/vendor.js',
 *     '/dist/app.js'
 * ])->setCssUrl('/dist/app.css');
 * ```
 *
 * ### Production Configuration
 * ```php
 * $renderer = new SinglePageApplicationRenderer();
 * $renderer->setJsUrl('https://cdn.example.com/app.min.js')
 *          ->setCssUrl('https://cdn.example.com/app.min.css')
 *          ->setMountElement('root')
 *          ->setStateVariable('APP_STATE');
 * ```
 *
 * ### Custom Mount Point and State Variable
 * ```php
 * $renderer = new SinglePageApplicationRenderer();
 * $renderer->setMountElement('react-root')
 *          ->setStateVariable('INITIAL_STATE');
 * // Generates: <div id="react-root"></div>
 * // And: window.INITIAL_STATE = {...};
 * ```
 *
 * ## Generated HTML Structure
 *
 * The renderer produces HTML in this structure:
 * ```html
 * <!DOCTYPE html>
 * <html lang="en-US">
 * <head>
 *     <meta charset="UTF-8">
 *     <meta name="viewport" content="width=device-width, initial-scale=1">
 *     <title>Page Title</title>
 *     <link rel="stylesheet" href="/dist/app.css">
 * </head>
 * <body>
 *     <div id="app"></div>
 *     <script nonce="BASE64_NONCE">
 *         window.__PAGE_STATE__ = {"title":"...","canvas":{...}};
 *     </script>
 *     <script src="/dist/app.js" nonce="BASE64_NONCE" defer></script>
 * </body>
 * </html>
 * ```
 *
 * ## CSP Compliance
 *
 * When using this renderer with CSP enabled:
 * ```php
 * $page->enableCsp()
 *      ->allowScriptFrom("'self'", 'https://cdn.example.com')
 *      ->allowStyleFrom("'self'", 'https://fonts.googleapis.com');
 * 
 * // The renderer will automatically add nonce-based CSP directives
 * // for the inline state injection script
 * ```
 *
 * @package Hudsxn\Canvas\Renderers
 */
final class SinglePageApplicationRenderer implements CanvasPageRenderer
{
    /**
     * Single JavaScript bundle URL.
     *
     * @var string
     */
    private string $jsUrl = '';

    /**
     * Multiple JavaScript bundle URLs (loaded in order).
     *
     * @var string[]
     */
    private array $jsUrls = [];

    /**
     * Single CSS stylesheet URL.
     *
     * @var string
     */
    private string $cssUrl = '';

    /**
     * Multiple CSS stylesheet URLs (loaded in order).
     *
     * @var string[]
     */
    private array $cssUrls = [];

    /**
     * The ID of the DOM element where the SPA will mount.
     *
     * @var string
     */
    private string $mountElement = 'app';

    /**
     * The global variable name for injected page state.
     *
     * @var string
     */
    private string $stateVariable = '__PAGE_STATE__';

    /**
     * Whether to include a default viewport meta tag.
     *
     * @var bool
     */
    private bool $includeViewport = true;

    /**
     * Sets the JavaScript bundle URL.
     *
     * Use this for a single JS file. For multiple files, use setJsUrls().
     *
     * @param string $url The JavaScript bundle URL (relative or absolute)
     * @return self Returns this renderer for method chaining
     *
     * @example
     * ```php
     * $renderer->setJsUrl('/dist/app.bundle.js');
     * $renderer->setJsUrl('https://cdn.example.com/app.min.js');
     * ```
     */
    public function setJsUrl(string $url): self
    {
        $this->jsUrl = $url;
        $this->jsUrls = []; // Clear multiple URLs

        return $this;
    }

    /**
     * Sets multiple JavaScript bundle URLs.
     *
     * Scripts are loaded in the order provided. Use this for vendor/app splitting.
     *
     * @param string[] $urls Array of JavaScript URLs
     * @return self Returns this renderer for method chaining
     *
     * @example
     * ```php
     * $renderer->setJsUrls([
     *     '/dist/vendor.js',
     *     '/dist/runtime.js',
     *     '/dist/app.js'
     * ]);
     * ```
     */
    public function setJsUrls(array $urls): self
    {
        $this->jsUrls = $urls;
        $this->jsUrl = ''; // Clear single URL

        return $this;
    }

    /**
     * Sets the CSS stylesheet URL.
     *
     * Use this for a single CSS file. For multiple files, use setCssUrls().
     *
     * @param string $url The CSS stylesheet URL (relative or absolute)
     * @return self Returns this renderer for method chaining
     *
     * @example
     * ```php
     * $renderer->setCssUrl('/dist/app.css');
     * $renderer->setCssUrl('https://cdn.example.com/styles.min.css');
     * ```
     */
    public function setCssUrl(string $url): self
    {
        $this->cssUrl = $url;
        $this->cssUrls = []; // Clear multiple URLs

        return $this;
    }

    /**
     * Sets multiple CSS stylesheet URLs.
     *
     * Stylesheets are loaded in the order provided.
     *
     * @param string[] $urls Array of CSS URLs
     * @return self Returns this renderer for method chaining
     *
     * @example
     * ```php
     * $renderer->setCssUrls([
     *     'https://fonts.googleapis.com/css2?family=Inter',
     *     '/dist/vendor.css',
     *     '/dist/app.css'
     * ]);
     * ```
     */
    public function setCssUrls(array $urls): self
    {
        $this->cssUrls = $urls;
        $this->cssUrl = ''; // Clear single URL

        return $this;
    }

    /**
     * Sets the DOM element ID where the SPA will mount.
     *
     * @param string $elementId The element ID (without '#')
     * @return self Returns this renderer for method chaining
     *
     * @example
     * ```php
     * $renderer->setMountElement('root'); // <div id="root"></div>
     * $renderer->setMountElement('react-app'); // <div id="react-app"></div>
     * ```
     */
    public function setMountElement(string $elementId): self
    {
        $this->mountElement = $elementId;

        return $this;
    }

    /**
     * Sets the global variable name for the injected page state.
     *
     * @param string $variableName The global variable name (without 'window.')
     * @return self Returns this renderer for method chaining
     *
     * @example
     * ```php
     * $renderer->setStateVariable('INITIAL_STATE');
     * // Generates: window.INITIAL_STATE = {...};
     * 
     * $renderer->setStateVariable('APP_DATA');
     * // Generates: window.APP_DATA = {...};
     * ```
     */
    public function setStateVariable(string $variableName): self
    {
        $this->stateVariable = $variableName;

        return $this;
    }

    /**
     * Controls whether to include a default viewport meta tag.
     *
     * The default viewport tag is: width=device-width, initial-scale=1
     *
     * @param bool $include Whether to include the viewport tag (default: true)
     * @return self Returns this renderer for method chaining
     *
     * @example
     * ```php
     * // Disable if you're setting a custom viewport via Page::setMeta()
     * $renderer->includeViewport(false);
     * ```
     */
    public function includeViewport(bool $include = true): self
    {
        $this->includeViewport = $include;

        return $this;
    }

    /**
     * Generates the HTML shell with injected page state.
     *
     * @param Page $page The page to render
     * @param array<int, string> &$sourceCode Numeric array for appending HTML fragments
     * @return void
     */
    public function generateHtml(Page $page, array &$sourceCode): void
    {
        // Generate a random nonce per render for CSP compliance
        $nonce = $this->generateNonce();

        // HTML document start
        $sourceCode[] = '<!DOCTYPE html>';
        $sourceCode[] = '<html lang="' . htmlspecialchars($page->getLocale()) . '">';
        $sourceCode[] = '<head>';
        
        // Character encoding
        $sourceCode[] = '<meta charset="' . htmlspecialchars($page->getCharset()) . '">';
        
        // Viewport (mobile optimization)
        if ($this->includeViewport && !isset($page->getMetaTags()['viewport'])) {
            $sourceCode[] = '<meta name="viewport" content="width=device-width, initial-scale=1">';
        }
        
        // Page title
        $sourceCode[] = '<title>' . htmlspecialchars($page->getTitle()) . '</title>';

        // Standard meta tags
        foreach ($page->getMetaTags() as $name => $content) {
            $sourceCode[] = '<meta name="' . htmlspecialchars($name) . '" content="' . htmlspecialchars($content) . '">';
        }

        // SEO tags (Open Graph, Twitter Cards, etc.)
        foreach ($page->getSeoTags() as $property => $content) {
            $sourceCode[] = '<meta property="' . htmlspecialchars($property) . '" content="' . htmlspecialchars($content) . '">';
        }

        // CSS stylesheets
        $this->renderStylesheets($sourceCode);

        $sourceCode[] = '</head>';
        $sourceCode[] = '<body>';

        // SPA mounting point
        $sourceCode[] = '<div id="' . htmlspecialchars($this->mountElement) . '"></div>';

        // Inline page state injection (nonce-protected for CSP)
        $sourceCode[] = '<script nonce="' . htmlspecialchars($nonce) . '">';
        $sourceCode[] = 'window.' . $this->stateVariable . ' = ';
        $sourceCode[] = json_encode(
            $this->buildPageState($page),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $sourceCode[] = ';';
        $sourceCode[] = '</script>';

        // External JavaScript bundles
        $this->renderScripts($sourceCode, $nonce);

        $sourceCode[] = '</body>';
        $sourceCode[] = '</html>';
    }

    /**
     * Renders CSS stylesheet link tags.
     *
     * @param array<int, string> &$sourceCode The source code array
     * @return void
     */
    private function renderStylesheets(array &$sourceCode): void
    {
        // Render multiple CSS files
        if (!empty($this->cssUrls)) {
            foreach ($this->cssUrls as $url) {
                $sourceCode[] = '<link rel="stylesheet" href="' . htmlspecialchars($url) . '">';
            }
        }
        // Render single CSS file
        elseif ($this->cssUrl !== '') {
            $sourceCode[] = '<link rel="stylesheet" href="' . htmlspecialchars($this->cssUrl) . '">';
        }
    }

    /**
     * Renders JavaScript script tags.
     *
     * @param array<int, string> &$sourceCode The source code array
     * @param string $nonce The CSP nonce for script tags
     * @return void
     */
    private function renderScripts(array &$sourceCode, string $nonce): void
    {
        // Render multiple JS files
        if (!empty($this->jsUrls)) {
            foreach ($this->jsUrls as $url) {
                $sourceCode[] = '<script src="' . htmlspecialchars($url) . '" nonce="' . htmlspecialchars($nonce) . '" defer></script>';
            }
        }
        // Render single JS file
        elseif ($this->jsUrl !== '') {
            $sourceCode[] = '<script src="' . htmlspecialchars($this->jsUrl) . '" nonce="' . htmlspecialchars($nonce) . '" defer></script>';
        }
    }

    /**
     * Generates a cryptographically secure nonce for CSP.
     *
     * The nonce is a base64-encoded random string (16 bytes = 128 bits of entropy).
     * This provides sufficient security for Content Security Policy script-src directives.
     *
     * @return string A base64-encoded nonce
     */
    private function generateNonce(): string
    {
        return base64_encode(random_bytes(16));
    }

    /**
     * Builds the page state object for JSON serialization.
     *
     * This state is injected into the page as a global variable and can be
     * used by the client-side framework for hydration or initial rendering.
     *
     * @param Page $page The page to extract state from
     * @return array<string, mixed> The complete page state
     */
    private function buildPageState(Page $page): array
    {
        return [
            'title'  => $page->getTitle(),
            'locale' => $page->getLocale(),
            'meta'   => $page->getMetaTags(),
            'seo'    => $page->getSeoTags(),
            'canvas' => $page->getCanvas()->toArray(),
        ];
    }
}