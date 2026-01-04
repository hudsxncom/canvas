<?php

namespace Hudsxn\Canvas\Contracts;

use Hudsxn\Canvas\Objects\Page;

/**
 * Contract for rendering Page objects into HTML output.
 *
 * This interface defines the contract for page rendering implementations,
 * allowing different rendering strategies to be used interchangeably based
 * on developer preference and application requirements.
 *
 * ## Purpose
 * The CanvasPageRenderer provides flexibility in how Pages are rendered:
 * - Developers can choose their preferred rendering approach
 * - Different projects can use different rendering strategies
 * - Rendering logic is completely decoupled from the Canvas structure
 * - Easy to test and swap implementations via dependency injection
 *
 * ## Rendering Approaches
 *
 * This interface supports any rendering strategy, including:
 * - **Traditional server-side**: PHP templates (Blade, Twig, plain PHP)
 * - **Single Page Applications**: Generate JSON for React, Vue, Angular
 * - **Hybrid approaches**: SSR with client-side hydration
 * - **Static generation**: Pre-render to HTML files
 * - **Component-based**: Custom component rendering systems
 *
 * ## How It Works
 *
 * The renderer receives:
 * 1. **$page** - The Page object containing the Canvas tree and metadata
 * 2. **&$sourceCode** - A reference to a numerically-indexed array for output
 *
 * The `$sourceCode` array is a numerically-indexed array (not associative),
 * passed by reference for performance. Using numeric indexes with array push
 * operations is significantly faster than repeated string concatenation, as it
 * avoids constant buffer reallocation in memory.
 *
 * ```php
 * // Fast: append to numeric array
 * $sourceCode[] = '<div>';
 * $sourceCode[] = $content;
 * $sourceCode[] = '</div>';
 * 
 * // Final output: implode('', $sourceCode)
 * ```
 *
 * ## Implementation Examples
 *
 * ### Traditional PHP Template Renderer
 * Renders the page using server-side PHP templates:
 * ```php
 * class BladeRenderer implements CanvasPageRenderer
 * {
 *     public function generateHtml(Page $page, array &$sourceCode): void
 *     {
 *         $canvas = $page->getCanvas();
 *         $template = $canvas->getTemplate();
 *         
 *         $html = view($template, [
 *             'page' => $page,
 *             'canvas' => $canvas,
 *             'title' => $page->getTitle(),
 *             'nodes' => $canvas->getChildren()
 *         ])->render();
 *         
 *         $sourceCode[] = $html;
 *     }
 * }
 * ```
 *
 * ### SPA JSON Renderer
 * Generates JSON for client-side frameworks like React or Vue:
 * ```php
 * class SpaRenderer implements CanvasPageRenderer
 * {
 *     public function generateHtml(Page $page, array &$sourceCode): void
 *     {
 *         $sourceCode[] = view('spa-shell', [
 *             'title' => $page->getTitle()
 *         ])->render();
 *         
 *         $sourceCode[] = '<script>window.__INITIAL_STATE__=';
 *         $sourceCode[] = json_encode([
 *             'canvas' => $page->getCanvas()->toArray(),
 *             'meta' => $page->getMeta(),
 *             'route' => $page->getRoute()
 *         ]);
 *         $sourceCode[] = ';</script>';
 *     }
 * }
 * ```
 *
 * ### Component-Based Renderer
 * Recursively renders custom components:
 * ```php
 * class ComponentRenderer implements CanvasPageRenderer
 * {
 *     public function __construct(private ComponentRegistry $registry) {}
 *     
 *     public function generateHtml(Page $page, array &$sourceCode): void
 *     {
 *         $canvas = $page->getCanvas();
 *         $this->renderNode($canvas, $sourceCode);
 *     }
 *     
 *     private function renderNode(Node $node, array &$sourceCode): void
 *     {
 *         $component = $this->registry->get($node->getName());
 *         $sourceCode[] = $component->render(
 *             $node->getProps(), 
 *             $node->getChildren()
 *         );
 *     }
 * }
 * ```
 *
 * ### Static HTML Renderer
 * Generates static HTML files with optimal performance:
 * ```php
 * class StaticRenderer implements CanvasPageRenderer
 * {
 *     public function generateHtml(Page $page, array &$sourceCode): void
 *     {
 *         $sourceCode[] = '<!DOCTYPE html><html><head>';
 *         $this->renderHead($page, $sourceCode);
 *         $sourceCode[] = '</head><body>';
 *         $this->renderBody($page->getCanvas(), $sourceCode);
 *         $this->renderScripts($page, $sourceCode);
 *         $sourceCode[] = '</body></html>';
 *     }
 *     
 *     private function renderHead(Page $page, array &$sourceCode): void
 *     {
 *         $sourceCode[] = '<title>';
 *         $sourceCode[] = htmlspecialchars($page->getTitle());
 *         $sourceCode[] = '</title>';
 *         // Additional head elements...
 *     }
 * }
 * ```
 *
 * ## Using the Renderer
 *
 * Renderers are typically injected and used by a page rendering service:
 *
 * ```php
 * class PageRenderService
 * {
 *     public function __construct(private CanvasPageRenderer $renderer) {}
 *     
 *     public function render(Page $page): string
 *     {
 *         $sourceCode = [];
 *         $this->renderer->generateHtml($page, $sourceCode);
 *         
 *         // Implode the array to get final HTML
 *         return implode('', $sourceCode);
 *     }
 * }
 * ```
 *
 * ## Configuration Example
 *
 * Switch renderers via dependency injection:
 *
 * ```php
 * // Traditional PHP app
 * $container->bind(CanvasPageRenderer::class, BladeRenderer::class);
 *
 * // SPA application
 * $container->bind(CanvasPageRenderer::class, SpaRenderer::class);
 *
 * // Component-based system
 * $container->bind(CanvasPageRenderer::class, ComponentRenderer::class);
 * ```
 *
 * ## Design Benefits
 *
 * - **Flexibility**: Choose the rendering approach that fits your needs
 * - **Testability**: Easy to mock or swap for testing
 * - **Separation of Concerns**: Canvas structure is independent of rendering
 * - **Framework Agnostic**: Works with any PHP framework or standalone
 * - **Future Proof**: New rendering strategies can be added without changes
 *
 * @package Hudsxn\Canvas\Contracts
 */
interface CanvasPageRenderer
{
    /**
     * Generates HTML (or other output) from a Page object.
     *
     * This method is responsible for transforming the Page and its Canvas tree
     * into renderable output. The implementation determines how the rendering
     * happens and what format the output takes.
     *
     * ## Performance Optimization
     *
     * The `$sourceCode` array is a numerically-indexed array passed by reference.
     * This design choice significantly improves performance by avoiding repeated
     * string concatenation and memory reallocation:
     *
     * ```php
     * // Efficient: append to numeric array (O(1) operation)
     * $sourceCode[] = '<div class="header">';
     * $sourceCode[] = $this->renderContent($node);
     * $sourceCode[] = '</div>';
     * 
     * // Final step: implode('', $sourceCode)
     * ```
     *
     * Instead of building strings with `.=` or concatenation (which creates new
     * string buffers each time), appending to a numeric array is much faster for
     * large HTML outputs. The array elements are joined once at the end.
     *
     * @param Page $page The page object to render, containing the Canvas tree
     * @param array<int, string> &$sourceCode Numeric array for appending output fragments
     * @return void The method modifies $sourceCode by reference
     *
     * @example
     * ```php
     * $renderer = new BladeRenderer();
     * $sourceCode = [];
     * $renderer->generateHtml($page, $sourceCode);
     * $html = implode('', $sourceCode);
     * echo $html;
     * ```
     */
    public function generateHtml(Page $page, array &$sourceCode): void;
}