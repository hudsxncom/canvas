<?php

declare(strict_types=1);

namespace Hudsxn\Canvas\Objects;

/**
 * Represents a complete, response-ready document with rendering metadata.
 *
 * Page is the top-level container that combines a Canvas (the render tree) with
 * all the necessary document metadata, security policies, and behavioral rules
 * needed to generate a complete HTTP response.
 *
 * ## Purpose
 * Page handles everything about the document EXCEPT the actual rendering:
 * - **Content Structure**: Owns the Canvas tree
 * - **Document Metadata**: Title, locale, charset, meta tags
 * - **SEO Configuration**: Open Graph, Twitter Cards, structured data
 * - **Security Policies**: Content Security Policy (CSP) directives
 * - **Behavioral Rules**: Indexing, caching, HTTPS enforcement
 *
 * ## Key Design Principles
 * - **Separation of Concerns**: Page contains NO rendering logic
 * - **Transport Ready**: Easily serializable to JSON via toArray()
 * - **Performance Optimized**: Uses bitwise flags for boolean settings
 * - **Fluent Interface**: All setters return $this for method chaining
 *
 * ## Usage Examples
 *
 * ### Basic Page Setup
 * ```php
 * $canvas = new Canvas('layouts/app');
 * $page = new Page($canvas);
 * 
 * $page->setTitle('Welcome to My Site')
 *      ->setLocale('en-US')
 *      ->setMeta('description', 'A great website')
 *      ->setMeta('viewport', 'width=device-width, initial-scale=1');
 * ```
 *
 * ### SEO Configuration
 * ```php
 * $page->setSeo('og:title', 'My Amazing Article')
 *      ->setSeo('og:description', 'Learn about...')
 *      ->setSeo('og:image', 'https://example.com/image.jpg')
 *      ->setSeo('twitter:card', 'summary_large_image');
 * ```
 *
 * ### Security & CSP
 * ```php
 * $page->enableCsp()
 *      ->allowScriptFrom('https://cdn.example.com')
 *      ->allowStyleFrom('https://fonts.googleapis.com')
 *      ->allowImageFrom('https:', 'data:')
 *      ->allowInlineStyles()
 *      ->blockAllMixed();
 * ```
 *
 * ### Behavioral Flags
 * ```php
 * // Prevent search engine indexing (staging environment)
 * $page->noIndex()->noFollow();
 * 
 * // Disable caching for dynamic content
 * $page->noCache();
 * 
 * // Enforce HTTPS redirects
 * $page->forceHttps();
 * ```
 *
 * ### Complete Example
 * ```php
 * $page = new Page(new Canvas('layouts/blog'))
 *     ->setTitle('10 Tips for Better Code')
 *     ->setMeta('description', 'Improve your coding skills')
 *     ->setSeo('og:type', 'article')
 *     ->setSeo('article:author', 'Jane Developer')
 *     ->enableCsp()
 *     ->allowScriptFrom("'self'", 'https://cdn.jsdelivr.net')
 *     ->allowStyleFrom("'self'", 'https://fonts.googleapis.com')
 *     ->forceHttps();
 * ```
 *
 * @package Hudsxn\Canvas\Objects
 */
class Page
{
    /* -----------------------------------------------------------------
     |  Flags (bitwise constants)
     | -----------------------------------------------------------------
     */

    /** Enable Content Security Policy enforcement */
    public const CSP_ENABLED      = 1 << 0;
    
    /** Prevent search engines from indexing this page */
    public const NO_INDEX         = 1 << 1;
    
    /** Prevent search engines from following links on this page */
    public const NO_FOLLOW        = 1 << 2;
    
    /** Disable browser and proxy caching */
    public const NO_CACHE         = 1 << 3;
    
    /** Force HTTPS redirects for this page */
    public const FORCE_HTTPS      = 1 << 4;

    /**
     * Bitwise flags storage for boolean settings.
     *
     * Using bitwise flags is more memory-efficient than individual boolean
     * properties and provides fast flag checking via bitwise operations.
     *
     * @var int
     */
    private int $flags = 0;

    /* -----------------------------------------------------------------
     |  Core Properties
     | -----------------------------------------------------------------
     */

    /**
     * The Canvas render tree for this page.
     *
     * @var Canvas
     */
    private Canvas $canvas;

    /**
     * Page title (appears in browser tab and search results).
     *
     * @var string
     */
    private string $title   = '';
    
    /**
     * Document locale/language code (e.g., 'en-US', 'fr-FR').
     *
     * @var string
     */
    private string $locale  = 'en-GB';
    
    /**
     * Character encoding for the document.
     *
     * @var string
     */
    private string $charset = 'UTF-8';

    /* -----------------------------------------------------------------
     |  Metadata Collections
     | -----------------------------------------------------------------
     */

    /**
     * Standard HTML meta tags (name => content).
     *
     * Common examples:
     * - 'description' => 'Page description'
     * - 'viewport' => 'width=device-width, initial-scale=1'
     * - 'author' => 'John Doe'
     * - 'keywords' => 'php, canvas, framework'
     *
     * @var array<string, string>
     */
    private array $metaTags = [];

    /**
     * SEO-specific meta tags (property => content).
     *
     * Typically includes Open Graph and Twitter Card tags:
     * - 'og:title' => 'Page Title'
     * - 'og:description' => 'Page description'
     * - 'og:image' => 'https://example.com/image.jpg'
     * - 'twitter:card' => 'summary_large_image'
     *
     * @var array<string, string>
     */
    private array $seoTags = [];

    /**
     * Content Security Policy directives.
     *
     * Maps CSP directives to their values:
     * - 'default-src' => ["'self'"]
     * - 'script-src' => ["'self'", 'https://cdn.example.com']
     * - 'style-src' => ["'self'", "'unsafe-inline'"]
     *
     * @var array<string, string|string[]>
     */
    private array $csp = [];

    /* -----------------------------------------------------------------
     |  Constructor
     | -----------------------------------------------------------------
     */

    /**
     * Creates a new Page instance.
     *
     * @param Canvas $canvas The Canvas render tree for this page
     *
     * @example
     * ```php
     * $canvas = new Canvas('layouts/default');
     * $page = new Page($canvas);
     * ```
     */
    public function __construct()
    {
        // this assumes a template like this, it can and in most cases
        // will change. 
        $this->canvas = new Canvas("page/default");
    }

    /* -----------------------------------------------------------------
     |  Canvas Management
     | -----------------------------------------------------------------
     */

    /**
     * Gets the Canvas render tree.
     *
     * @return Canvas The current canvas instance
     */
    public function getCanvas(): Canvas
    {
        return $this->canvas;
    }

    /**
     * Sets or replaces the Canvas render tree.
     *
     * @param Canvas $canvas New canvas instance
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->setCanvas(new Canvas('layouts/mobile'));
     * ```
     */
    public function setCanvas(Canvas $canvas): self
    {
        $this->canvas = $canvas;

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Document Properties (Title / Locale / Charset)
     | -----------------------------------------------------------------
     */

    /**
     * Gets the page title.
     *
     * @return string The page title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Sets the page title.
     *
     * The title appears in browser tabs, bookmarks, and search engine results.
     * Keep titles concise (50-60 characters) for optimal SEO.
     *
     * @param string $title Page title
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->setTitle('About Us | Company Name');
     * ```
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Gets the document locale.
     *
     * @return string Current locale code (e.g., 'en-GB', 'fr-FR')
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Sets the document locale/language.
     *
     * Use standard language codes (ISO 639-1) with optional region codes.
     * This affects the HTML lang attribute and helps with accessibility.
     *
     * @param string $locale Locale code (e.g., 'en-US', 'ja-JP', 'pt-BR')
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->setLocale('fr-CA'); // French (Canadian)
     * ```
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Gets the character encoding.
     *
     * @return string Current charset (e.g., 'UTF-8')
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * Sets the character encoding.
     *
     * UTF-8 is recommended for modern web applications as it supports all
     * languages and special characters.
     *
     * @param string $charset Character encoding (typically 'UTF-8')
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->setCharset('UTF-8');
     * ```
     */
    public function setCharset(string $charset): self
    {
        $this->charset = $charset;

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Standard Meta Tags
     | -----------------------------------------------------------------
     */

    /**
     * Gets all standard meta tags.
     *
     * @return array<string, string> Associative array of meta tag names and content
     *
     * @example
     * ```php
     * foreach ($page->getMetaTags() as $name => $content) {
     *     echo "<meta name=\"$name\" content=\"$content\">";
     * }
     * ```
     */
    public function getMetaTags(): array
    {
        return $this->metaTags;
    }

    /**
     * Sets a standard meta tag.
     *
     * @param string $name Meta tag name (e.g., 'description', 'viewport')
     * @param string $content Meta tag content
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->setMeta('description', 'Learn about our products and services')
     *      ->setMeta('viewport', 'width=device-width, initial-scale=1')
     *      ->setMeta('author', 'Jane Developer')
     *      ->setMeta('keywords', 'web, development, php');
     * ```
     */
    public function setMeta(string $name, string $content): self
    {
        $this->metaTags[$name] = $content;

        return $this;
    }

    /**
     * Removes a meta tag.
     *
     * @param string $name Meta tag name to remove
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->removeMeta('keywords');
     * ```
     */
    public function removeMeta(string $name): self
    {
        unset($this->metaTags[$name]);

        return $this;
    }

    /* -----------------------------------------------------------------
     |  SEO Tags (Open Graph, Twitter Cards, etc.)
     | -----------------------------------------------------------------
     */

    /**
     * Gets all SEO meta tags.
     *
     * @return array<string, string> Associative array of SEO property names and content
     *
     * @example
     * ```php
     * foreach ($page->getSeoTags() as $property => $content) {
     *     echo "<meta property=\"$property\" content=\"$content\">";
     * }
     * ```
     */
    public function getSeoTags(): array
    {
        return $this->seoTags;
    }

    /**
     * Sets an SEO meta tag.
     *
     * Commonly used for Open Graph (og:*) and Twitter Card (twitter:*) tags
     * that control how your page appears when shared on social media.
     *
     * @param string $name Property name (e.g., 'og:title', 'twitter:card')
     * @param string $content Property content
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * // Open Graph tags
     * $page->setSeo('og:title', 'Amazing Article Title')
     *      ->setSeo('og:description', 'This article will blow your mind')
     *      ->setSeo('og:image', 'https://example.com/featured.jpg')
     *      ->setSeo('og:url', 'https://example.com/article')
     *      ->setSeo('og:type', 'article');
     *
     * // Twitter Card tags
     * $page->setSeo('twitter:card', 'summary_large_image')
     *      ->setSeo('twitter:site', '@yourhandle')
     *      ->setSeo('twitter:creator', '@authorhandle');
     * ```
     */
    public function setSeo(string $name, string $content): self
    {
        $this->seoTags[$name] = $content;

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Content Security Policy (CSP) - Core Methods
     | -----------------------------------------------------------------
     */

    /**
     * Enables or disables Content Security Policy.
     *
     * When enabled, CSP headers will be generated from the configured directives.
     * This helps prevent XSS attacks, data injection, and other security threats.
     *
     * @param bool $enabled Whether to enable CSP (default: true)
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->enableCsp();
     * ```
     */
    public function enableCsp(bool $enabled = true): self
    {
        return $this->setFlag(self::CSP_ENABLED, $enabled);
    }

    /**
     * Checks if Content Security Policy is enabled.
     *
     * @return bool True if CSP is enabled
     */
    public function isCspEnabled(): bool
    {
        return $this->hasFlag(self::CSP_ENABLED);
    }

    /**
     * Sets a raw CSP directive.
     *
     * For more convenient CSP configuration, use the helper methods like
     * allowScriptFrom(), allowStyleFrom(), etc.
     *
     * @param string $directive CSP directive name (e.g., 'default-src', 'script-src')
     * @param string|string[] $value Single value or array of allowed sources
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->setCsp('default-src', ["'self'"]);
     * $page->setCsp('script-src', ["'self'", 'https://cdn.example.com']);
     * ```
     */
    public function setCsp(string $directive, string|array $value): self
    {
        $this->csp[$directive] = $value;

        return $this;
    }

    /**
     * Gets all CSP directives.
     *
     * @return array<string, string|string[]> Map of directive names to their values
     */
    public function getCsp(): array
    {
        return $this->csp;
    }

    /* -----------------------------------------------------------------
     |  Content Security Policy (CSP) - Helper Methods
     | -----------------------------------------------------------------
     */

    /**
     * Allows scripts from specified sources.
     *
     * @param string ...$sources One or more script sources to allow
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * // Allow scripts from your domain and CDN
     * $page->allowScriptFrom("'self'", 'https://cdn.jsdelivr.net');
     * 
     * // Allow inline scripts (use cautiously!)
     * $page->allowScriptFrom("'self'", "'unsafe-inline'");
     * ```
     */
    public function allowScriptFrom(string ...$sources): self
    {
        return $this->addCspSources('script-src', $sources);
    }

    /**
     * Allows stylesheets from specified sources.
     *
     * @param string ...$sources One or more style sources to allow
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->allowStyleFrom("'self'", 'https://fonts.googleapis.com');
     * ```
     */
    public function allowStyleFrom(string ...$sources): self
    {
        return $this->addCspSources('style-src', $sources);
    }

    /**
     * Allows images from specified sources.
     *
     * @param string ...$sources One or more image sources to allow
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * // Allow images from anywhere over HTTPS and data URIs
     * $page->allowImageFrom('https:', 'data:');
     * 
     * // Allow images from specific domains
     * $page->allowImageFrom("'self'", 'https://images.example.com');
     * ```
     */
    public function allowImageFrom(string ...$sources): self
    {
        return $this->addCspSources('img-src', $sources);
    }

    /**
     * Allows fonts from specified sources.
     *
     * @param string ...$sources One or more font sources to allow
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->allowFontFrom("'self'", 'https://fonts.gstatic.com');
     * ```
     */
    public function allowFontFrom(string ...$sources): self
    {
        return $this->addCspSources('font-src', $sources);
    }

    /**
     * Allows connection sources (AJAX, WebSocket, EventSource).
     *
     * @param string ...$sources One or more connection sources to allow
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->allowConnectTo("'self'", 'https://api.example.com');
     * ```
     */
    public function allowConnectTo(string ...$sources): self
    {
        return $this->addCspSources('connect-src', $sources);
    }

    /**
     * Allows media (audio/video) from specified sources.
     *
     * @param string ...$sources One or more media sources to allow
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->allowMediaFrom("'self'", 'https://videos.example.com');
     * ```
     */
    public function allowMediaFrom(string ...$sources): self
    {
        return $this->addCspSources('media-src', $sources);
    }

    /**
     * Allows frame/iframe sources.
     *
     * @param string ...$sources One or more frame sources to allow
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->allowFrameFrom('https://www.youtube.com');
     * ```
     */
    public function allowFrameFrom(string ...$sources): self
    {
        return $this->addCspSources('frame-src', $sources);
    }

    /**
     * Allows inline styles.
     *
     * Use with caution as inline styles can be a security risk.
     * Consider using nonces or hashes for better security.
     *
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->allowInlineStyles();
     * ```
     */
    public function allowInlineStyles(): self
    {
        return $this->addCspSources('style-src', ["'unsafe-inline'"]);
    }

    /**
     * Allows inline scripts.
     *
     * WARNING: This significantly weakens your CSP protection.
     * Prefer using nonces or hashes for inline scripts instead.
     *
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->allowInlineScripts(); // Use cautiously!
     * ```
     */
    public function allowInlineScripts(): self
    {
        return $this->addCspSources('script-src', ["'unsafe-inline'"]);
    }

    /**
     * Blocks all mixed content (HTTP resources on HTTPS pages).
     *
     * Forces all resources to be loaded over HTTPS, preventing
     * mixed content warnings and potential security issues.
     *
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->blockAllMixed();
     * ```
     */
    public function blockAllMixed(): self
    {
        $this->csp['block-all-mixed-content'] = '';

        return $this;
    }

    /**
     * Requires Subresource Integrity for scripts and styles.
     *
     * Ensures that resources fetched from CDNs haven't been tampered with.
     *
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->requireSri();
     * ```
     */
    public function requireSri(): self
    {
        $this->csp['require-sri-for'] = ['script', 'style'];

        return $this;
    }

    /**
     * Sets a strict CSP that only allows resources from your domain.
     *
     * This is a secure default policy that blocks inline scripts/styles
     * and only allows resources from the same origin.
     *
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->enableCsp()
     *      ->useStrictPolicy()
     *      ->allowScriptFrom('https://cdn.example.com'); // Add exceptions
     * ```
     */
    public function useStrictPolicy(): self
    {
        return $this->setCsp('default-src', ["'self'"]);
    }

    /**
     * Adds sources to a CSP directive (internal helper).
     *
     * @param string $directive The CSP directive to modify
     * @param array<string> $sources Sources to add
     * @return self Returns this page for method chaining
     */
    private function addCspSources(string $directive, array $sources): self
    {
        $current = $this->csp[$directive] ?? [];
        
        // Ensure $current is always an array
        if (is_string($current)) {
            $current = [$current];
        }

        // Merge and remove duplicates
        $this->csp[$directive] = array_unique(array_merge($current, $sources));

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Behavioral Flags (Indexing / Caching / Security)
     | -----------------------------------------------------------------
     */

    /**
     * Prevents search engines from indexing this page.
     *
     * Useful for staging environments, admin panels, or pages under development.
     *
     * @param bool $enabled Whether to enable noindex (default: true)
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * // Staging environment
     * if (env('APP_ENV') === 'staging') {
     *     $page->noIndex();
     * }
     * ```
     */
    public function noIndex(bool $enabled = true): self
    {
        return $this->setFlag(self::NO_INDEX, $enabled);
    }

    /**
     * Prevents search engines from following links on this page.
     *
     * @param bool $enabled Whether to enable nofollow (default: true)
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * $page->noFollow();
     * ```
     */
    public function noFollow(bool $enabled = true): self
    {
        return $this->setFlag(self::NO_FOLLOW, $enabled);
    }

    /**
     * Disables browser and proxy caching for this page.
     *
     * Use for pages with frequently changing content or sensitive information.
     *
     * @param bool $enabled Whether to enable no-cache (default: true)
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * // Disable caching for dynamic dashboard
     * $page->noCache();
     * ```
     */
    public function noCache(bool $enabled = true): self
    {
        return $this->setFlag(self::NO_CACHE, $enabled);
    }

    /**
     * Forces HTTPS redirects for this page.
     *
     * Ensures the page is always served over a secure connection.
     *
     * @param bool $enabled Whether to force HTTPS (default: true)
     * @return self Returns this page for method chaining
     *
     * @example
     * ```php
     * // Force HTTPS for checkout pages
     * $page->forceHttps();
     * ```
     */
    public function forceHttps(bool $enabled = true): self
    {
        return $this->setFlag(self::FORCE_HTTPS, $enabled);
    }

    /* -----------------------------------------------------------------
     |  Flag Management (Internal)
     | -----------------------------------------------------------------
     */

    /**
     * Checks if a specific flag is set.
     *
     * @param int $flag The flag constant to check
     * @return bool True if the flag is set
     */
    private function hasFlag(int $flag): bool
    {
        return ($this->flags & $flag) === $flag;
    }

    /**
     * Sets or clears a specific flag.
     *
     * @param int $flag The flag constant to modify
     * @param bool $enabled Whether to set (true) or clear (false) the flag
     * @return self Returns this page for method chaining
     */
    private function setFlag(int $flag, bool $enabled): self
    {
        if ($enabled) {
            $this->flags |= $flag;
        } else {
            $this->flags &= ~$flag;
        }

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Serialization
     | -----------------------------------------------------------------
     */

    /**
     * Converts the page to a transport-safe array.
     *
     * This method serializes the entire page configuration into a nested
     * associative array suitable for JSON encoding, caching, or transmission
     * to a client-side framework.
     *
     * The returned structure includes all page metadata, flags, and the
     * complete Canvas tree.
     *
     * @return array<string, mixed> Complete page state as an array
     *
     * @example
     * ```php
     * // Serialize to JSON for API response
     * $json = json_encode($page->toArray());
     * 
     * // Cache the page state
     * cache()->put("page:{$id}", $page->toArray(), 3600);
     * 
     * // Send to client for hydration
     * return response()->json([
     *     'page' => $page->toArray(),
     *     'timestamp' => time()
     * ]);
     * ```
     */
    public function toArray(): array
    {
        return [
            'title'    => $this->title,
            'locale'   => $this->locale,
            'charset'  => $this->charset,
            'flags'    => $this->flags,
            'meta'     => $this->metaTags,
            'seo'      => $this->seoTags,
            'csp'      => $this->csp,
            'canvas'   => $this->canvas->toArray(),
        ];
    }
}