<?php

declare(strict_types=1);

namespace Hudsxn\Canvas\Objects;

/**
 * Represents the root node of a renderable tree structure.
 *
 * Canvas extends Node to provide a specialized root container that includes
 * template/layout information for rendering. It serves as the entry point for
 * both server-side rendering and client-side hydration from JSON.
 *
 * ## Purpose
 * While Node represents individual components or elements in a tree, Canvas
 * represents the entire document or page structure. It combines the tree
 * capabilities of Node with rendering metadata (the template identifier).
 *
 * ## Key Differences from Node
 * - Always acts as the root (top-level) node
 * - Has a fixed name of 'canvas'
 * - Includes template information for the rendering engine
 * - Serializes with additional template metadata
 *
 * ## Usage Examples
 *
 * ### Creating a basic canvas
 * ```php
 * $canvas = new Canvas('layouts/app', [
 *     'title' => 'My Page',
 *     'meta' => ['description' => 'A great page']
 * ]);
 * ```
 *
 * ### Building a complete tree
 * ```php
 * $canvas = new Canvas('default');
 * $canvas->addChild(new Node('Header', ['logo' => 'logo.png']))
 *        ->addChild(new Node('Main', ['content' => '...']))
 *        ->addChild(new Node('Footer'));
 * ```
 *
 * ### Changing templates dynamically
 * ```php
 * if ($isMobile) {
 *     $canvas->setTemplate('layouts/mobile');
 * }
 * ```
 *
 * ### Serializing for client-side hydration
 * ```php
 * $json = json_encode($canvas->toArray());
 * // Send to client for React/Vue/etc hydration
 * ```
 *
 * ## Template Naming Conventions
 * Templates can follow various naming patterns:
 * - Simple names: "default", "minimal", "admin"
 * - Path-based: "layouts/app", "layouts/dashboard"
 * - Namespaced: "@canvas/base", "@theme/modern"
 * - File-based: "views/layout.blade.php"
 *
 * The interpretation of template names is determined by your rendering engine.
 *
 * @package Hudsxn\Canvas\Objects
 * @extends Node
 */
class Canvas extends Node
{
    /**
     * Template identifier or path used for rendering.
     *
     * This identifier tells the rendering engine which layout or template
     * to use when rendering the canvas tree. The format and interpretation
     * depends on your rendering system.
     *
     * Common patterns:
     * - "default" - A simple identifier
     * - "layouts/app" - Path-style identifier
     * - "@canvas/base" - Namespaced identifier
     * - "views/page.blade.php" - File path for template engines
     *
     * @var string
     */
    private string $template;

    /**
     * Creates a new Canvas instance.
     *
     * The canvas is automatically assigned the name 'canvas' and serves as
     * the root node of your rendering tree. The template parameter determines
     * which layout will be used when rendering.
     *
     * @param string $template Template identifier (e.g., "default", "layouts/app")
     * @param array<string, mixed> $props Initial canvas-level properties (must be JSON-serializable)
     *
     * @example
     * ```php
     * // Simple canvas
     * $canvas = new Canvas('default');
     *
     * // Canvas with metadata
     * $canvas = new Canvas('layouts/admin', [
     *     'title' => 'Admin Dashboard',
     *     'lang' => 'en',
     *     'theme' => 'dark'
     * ]);
     * ```
     */
    public function __construct(string $template, array $props = [])
    {
        parent::__construct('canvas', $props);
        $this->template = $template;
    }

    /**
     * Gets the template identifier for this canvas.
     *
     * @return string The current template identifier
     *
     * @example
     * ```php
     * $template = $canvas->getTemplate(); // "layouts/app"
     * ```
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Sets or changes the canvas template.
     *
     * Useful for dynamically switching layouts based on context, such as
     * user preferences, device type, or application state.
     *
     * @param string $template New template identifier
     * @return static Returns the canvas instance for method chaining
     *
     * @example
     * ```php
     * // Switch to mobile layout
     * $canvas->setTemplate('layouts/mobile');
     *
     * // Conditional template selection
     * $canvas->setTemplate($user->isAdmin() ? 'admin' : 'default');
     *
     * // Method chaining
     * $canvas->setTemplate('layouts/app')
     *        ->setProp('title', 'New Title')
     *        ->addChild($header);
     * ```
     */
    public function setTemplate(string $template): static
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Converts the canvas and its entire tree to a serializable array.
     *
     * Extends the base Node::toArray() method to include template metadata.
     * The resulting structure includes everything from the parent Node plus
     * the template identifier, making it suitable for:
     *
     * - JSON API responses
     * - Client-side framework hydration (React, Vue, etc.)
     * - State persistence and caching
     * - Inter-service communication
     *
     * The returned array structure:
     * ```php
     * [
     *     'name' => 'canvas',
     *     'props' => ['title' => 'My Page', ...],
     *     'children' => [...],
     *     'template' => 'layouts/app'
     * ]
     * ```
     *
     * @return array<string, mixed> Nested array representation including template info
     *
     * @example
     * ```php
     * // Serialize to JSON for API response
     * $data = $canvas->toArray();
     * return response()->json($data);
     *
     * // Save state to cache
     * $serialized = json_encode($canvas->toArray());
     * cache()->put('page_state', $serialized);
     *
     * // Send to client for hydration
     * echo '<script>window.__INITIAL_STATE__ = ' . 
     *      json_encode($canvas->toArray()) . 
     *      ';</script>';
     * ```
     */
    public function toArray(): array
    {
        return array_merge(
            parent::toArray(),
            [
                'template' => $this->template,
            ]
        );
    }
}