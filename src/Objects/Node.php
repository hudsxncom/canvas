<?php

declare(strict_types=1);

namespace Hudsxn\Canvas\Objects;

/**
 * Represents a single node in the Canvas tree structure.
 *
 * Node is the fundamental building block of the Canvas system, providing a flexible
 * tree structure for representing UI components or document elements. Each node can
 * contain a type identifier, arbitrary properties, and child nodes.
 *
 * ## Key Features
 * - **Hierarchical structure**: Parent-child relationships with tree traversal
 * - **Type identification**: Named nodes for component/element typing
 * - **Property storage**: Arbitrary key-value props serializable to JSON
 * - **Dual rendering**: Supports both client-side (JSON) and server-side rendering
 *
 * ## Usage Examples
 *
 * ### Creating a simple tree
 * ```php
 * $root = new Node('div', ['class' => 'container']);
 * $heading = new Node('h1', ['text' => 'Welcome']);
 * $paragraph = new Node('p', ['text' => 'Hello world']);
 *
 * $root->addChild($heading)->addChild($paragraph);
 * ```
 *
 * ### Searching the tree
 * ```php
 * $button = $root->getChildByName('Button');
 * $primary = $root->getChildWhereProp('variant', 'primary');
 * ```
 *
 * ### Serializing to JSON
 * ```php
 * $json = json_encode($root->toArray());
 * ```
 *
 * @package Hudsxn\Canvas\Objects
 */
class Node
{
    /**
     * Parent node reference.
     *
     * Null indicates this is a root node with no parent.
     *
     * @var Node|null
     */
    private ?Node $parent = null;

    /**
     * Array of child nodes.
     *
     * Children are stored in order and maintain their parent reference.
     * The array is automatically reindexed when children are removed.
     *
     * @var Node[]
     */
    private array $children = [];

    /**
     * Node properties (arbitrary key-value pairs).
     *
     * Properties should be JSON-serializable values such as strings, numbers,
     * booleans, arrays, or nested associative arrays. Avoid storing objects
     * or resources that cannot be serialized.
     *
     * @var array<string, mixed>
     */
    private array $props = [];

    /**
     * Node type identifier.
     *
     * Typically represents a component name (e.g., "Button", "Layout")
     * or HTML element (e.g., "div", "span"). Used for identifying and
     * searching nodes within the tree.
     *
     * @var string
     */
    private string $name;

    /**
     * Creates a new Node instance.
     *
     * @param string $name   Node type identifier (e.g., "div", "Button", "Layout")
     * @param array<string, mixed> $props Initial properties (must be JSON-serializable)
     *
     * @example
     * ```php
     * $node = new Node('Button', ['text' => 'Click me', 'variant' => 'primary']);
     * ```
     */
    public function __construct(string $name, array $props = [])
    {
        $this->name  = $name;
        $this->props = $props;
    }

    /* -----------------------------------------------------------------
     |  Identity Methods
     | -----------------------------------------------------------------
     */

    /**
     * Gets the node's type identifier.
     *
     * @return string The node name (e.g., "div", "Button")
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the node's type identifier.
     *
     * @param string $name New node name
     * @return self Returns the node instance for method chaining
     *
     * @example
     * ```php
     * $node->setName('span');
     * ```
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Parent Relationship Methods
     | -----------------------------------------------------------------
     */

    /**
     * Gets the parent node.
     *
     * @return Node|null The parent node, or null if this is a root node
     */
    public function getParent(): ?Node
    {
        return $this->parent;
    }

    /**
     * Sets the parent node reference (internal use).
     *
     * This method is private because parent relationships should be managed
     * through addChild() and removeChild() to maintain tree integrity.
     *
     * @param Node|null $parent The parent node to set
     * @return void
     */
    private function setParent(?Node $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * Checks if this node is a root node (has no parent).
     *
     * @return bool True if the node has no parent
     */
    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    /**
     * Recursively searches for a descendant node by name.
     *
     * Performs a depth-first search starting from this node, checking the current
     * node first, then recursively searching all children.
     *
     * @param string $componentName The node name to search for
     * @return Node|null The first matching node found, or null if not found
     *
     * @example
     * ```php
     * $button = $root->getChildByName('Button');
     * if ($button !== null) {
     *     $button->setProp('disabled', true);
     * }
     * ```
     */
    public function getChildByName(string $componentName): ?Node
    {
        if ($this->name === $componentName)
        {
            return $this;
        }

        foreach($this->children as $child)
        {
            $result = $child->getChildByName($componentName);

            if ($result != null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Recursively searches for a descendant node by property value.
     *
     * Performs a depth-first search for the first node where the specified
     * property key matches the given value exactly (using strict comparison).
     *
     * @param string $propKey The property key to search for
     * @param string $value   The property value to match
     * @return Node|null The first matching node found, or null if not found
     *
     * @example
     * ```php
     * $primaryButton = $root->getChildWhereProp('variant', 'primary');
     * $namedSection = $root->getChildWhereProp('id', 'hero-section');
     * ```
     */
    public function getChildWhereProp(string $propKey, string $value): ?Node
    {
        if (isset($this->props[$propKey]) && $this->props[$propKey] === $value)
        {
            return $this;
        }
        foreach($this->children as $child)
        {
            $result = $child->getChildWhereProp($propKey, $value);

            if ($result != null) {
                return $result;
            }
        }
        return null;
    }

    /* -----------------------------------------------------------------
     |  Children Management Methods
     | -----------------------------------------------------------------
     */

    /**
     * Gets all direct children of this node.
     *
     * Returns a copy of the children array to prevent external mutation
     * of the internal tree structure.
     *
     * @return Node[] Array of child nodes (reindexed, starting from 0)
     *
     * @example
     * ```php
     * foreach ($parent->getChildren() as $child) {
     *     echo $child->getName();
     * }
     * ```
     */
    public function getChildren(): array
    {
        return array_values($this->children);
    }

    /**
     * Adds a child node to this node.
     *
     * If the child already has a parent, it will be automatically removed
     * from its previous parent before being added to this node. The child's
     * parent reference is updated accordingly.
     *
     * @param Node $child The node to add as a child
     * @return self Returns this node for method chaining
     *
     * @example
     * ```php
     * $parent->addChild(new Node('div'))
     *        ->addChild(new Node('span'))
     *        ->addChild(new Node('p'));
     * ```
     */
    public function addChild(Node $child): self
    {
        // Detach from previous parent if necessary
        if ($child->parent !== null) {
            $child->parent->removeChild($child);
        }

        $child->setParent($this);
        $this->children[] = $child;

        return $this;
    }

    /**
     * Removes a child node from this node.
     *
     * Searches for the exact child instance (by reference) and removes it.
     * The child's parent reference is set to null. The children array is
     * automatically reindexed after removal.
     *
     * @param Node $child The child node to remove
     * @return self Returns this node for method chaining
     *
     * @example
     * ```php
     * $parent->removeChild($unwantedChild);
     * ```
     */
    public function removeChild(Node $child): self
    {
        foreach ($this->children as $index => $existing) {
            if ($existing === $child) {
                unset($this->children[$index]);
                $child->setParent(null);
                break;
            }
        }

        // Reindex for predictable ordering
        $this->children = array_values($this->children);

        return $this;
    }

    /**
     * Checks if this node has any children.
     *
     * @return bool True if the node has one or more children
     */
    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    /* -----------------------------------------------------------------
     |  Properties Management Methods
     | -----------------------------------------------------------------
     */

    /**
     * Gets all properties of this node.
     *
     * Returns a copy of the props array to prevent external mutation.
     * To modify properties, use setProp() or setProps().
     *
     * @return array<string, mixed> Associative array of all properties
     *
     * @example
     * ```php
     * $props = $node->getProps();
     * echo $props['text'] ?? 'No text';
     * ```
     */
    public function getProps(): array
    {
        return $this->props;
    }

    /**
     * Gets a single property value.
     *
     * @param string $key     The property key to retrieve
     * @param mixed  $default Default value if the key doesn't exist
     * @return mixed The property value or the default
     *
     * @example
     * ```php
     * $text = $node->getProp('text', 'Default text');
     * $count = $node->getProp('count', 0);
     * ```
     */
    public function getProp(string $key, mixed $default = null): mixed
    {
        return $this->props[$key] ?? $default;
    }

    /**
     * Sets a single property value.
     *
     * If the property already exists, it will be overwritten.
     * Properties should be JSON-serializable values.
     *
     * @param string $key   The property key
     * @param mixed  $value The property value (should be JSON-serializable)
     * @return self Returns the node for method chaining
     *
     * @example
     * ```php
     * $node->setProp('text', 'Hello')
     *      ->setProp('visible', true)
     *      ->setProp('count', 42);
     * ```
     */
    public function setProp(string $key, mixed $value): self
    {
        $this->props[$key] = $value;

        return $this;
    }

    /**
     * Replaces all properties with a new set.
     *
     * This completely overwrites the existing properties.
     * To merge properties, retrieve with getProps(), modify, then call setProps().
     *
     * @param array<string, mixed> $props New properties (should be JSON-serializable)
     * @return self Returns the node for method chaining
     *
     * @example
     * ```php
     * $node->setProps(['text' => 'New text', 'visible' => true]);
     * ```
     */
    public function setProps(array $props): self
    {
        $this->props = $props;

        return $this;
    }

    /**
     * Removes a property from the node.
     *
     * If the property doesn't exist, this method has no effect.
     *
     * @param string $key The property key to remove
     * @return self Returns the node for method chaining
     *
     * @example
     * ```php
     * $node->removeProp('temporaryData');
     * ```
     */
    public function removeProp(string $key): self
    {
        unset($this->props[$key]);

        return $this;
    }

    /**
     * Checks if a property exists on the node.
     *
     * Returns true even if the property value is null. Use getProp()
     * to check for null values.
     *
     * @param string $key The property key to check
     * @return bool True if the property exists (regardless of value)
     *
     * @example
     * ```php
     * if ($node->hasProp('onClick')) {
     *     // Property exists, handle the click event
     * }
     * ```
     */
    public function hasProp(string $key): bool
    {
        return array_key_exists($key, $this->props);
    }

    /* -----------------------------------------------------------------
     |  Serialization Methods
     | -----------------------------------------------------------------
     */

    /**
     * Converts the node and its entire subtree to a serializable array.
     *
     * This method recursively converts the node and all its descendants into
     * a nested associative array structure suitable for JSON encoding. The
     * resulting structure can be used for:
     * - Client-side rendering (hydration from JSON state)
     * - API responses
     * - State persistence
     * - Data transfer between systems
     *
     * The returned array structure:
     * ```php
     * [
     *     'name' => 'div',
     *     'props' => ['class' => 'container'],
     *     'children' => [
     *         ['name' => 'h1', 'props' => [...], 'children' => []],
     *         ['name' => 'p', 'props' => [...], 'children' => []]
     *     ]
     * ]
     * ```
     *
     * @return array<string, mixed> Nested array representation of the node tree
     *
     * @example
     * ```php
     * $array = $root->toArray();
     * $json = json_encode($array, JSON_PRETTY_PRINT);
     * file_put_contents('tree.json', $json);
     * ```
     */
    public function toArray(): array
    {
        return [
            'name'     => $this->name,
            'props'    => $this->props,
            'children' => array_map(
                static fn (Node $child) => $child->toArray(),
                $this->children
            ),
        ];
    }
}