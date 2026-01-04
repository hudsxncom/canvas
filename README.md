# Canvas Framework

A lightweight, performant PHP framework for building server-rendered and hybrid web applications with a focus on flexibility, security, and developer experience.

## Overview

Canvas provides a structured approach to building web pages through a tree-based component system, decoupled rendering architecture, and comprehensive response handling. It solves common problems in modern PHP web development while maintaining simplicity and performance.

## Core Problems Solved

### 1. Rendering Flexibility

Traditional PHP frameworks often tightly couple your application logic to a specific templating engine. Canvas solves this by providing a renderer contract that allows you to choose your preferred rendering approach:

- **Server-side templating**: Use Blade, Twig, or plain PHP
- **Single Page Applications**: Generate JSON for React, Vue, or Angular
- **Hybrid approaches**: Server-side rendering with client-side hydration
- **Static site generation**: Pre-render to HTML files

Switch between rendering strategies without changing your application code. Simply swap the renderer implementation.

### 2. Performance Optimization

Canvas includes several performance-critical features:

**Array-Based String Building**
Instead of using string concatenation (which creates new string buffers on each operation), Canvas uses numerically-indexed arrays for HTML generation. This approach is significantly faster for large HTML outputs:

```php
// Slow: Creates new string buffer each time
$html .= '<div>';
$html .= $content;
$html .= '</div>';

// Fast: O(1) array append operations
$sourceCode[] = '<div>';
$sourceCode[] = $content;
$sourceCode[] = '</div>';
// Join once at the end: implode('', $sourceCode)
```

**Automatic Gzip Compression**
Built-in content negotiation and gzip compression can reduce HTML payload sizes by 70-90%, significantly improving page load times. Compression level is configurable from 1 (fastest) to 9 (maximum compression).

**Bitwise Flags**
Page configuration flags (caching, indexing, security) use bitwise operations instead of individual boolean properties, reducing memory usage and providing faster flag checks.

### 3. Security by Default

Canvas makes it easy to implement security best practices:

- **Content Security Policy**: Fluent API for configuring CSP directives
- **Automatic Security Headers**: X-Frame-Options, X-Content-Type-Options, HSTS
- **HTTPS Enforcement**: Built-in HTTPS redirect support
- **XSS Protection**: CSP helpers with sensible defaults

Configure comprehensive security policies in a few lines:

```php
$page->enableCsp()
     ->allowScriptFrom("'self'", 'https://cdn.example.com')
     ->allowStyleFrom("'self'")
     ->blockAllMixed()
     ->forceHttps();
```

### 4. SEO and Metadata Management

Centralized management of document metadata, Open Graph tags, Twitter Cards, and search engine directives:

```php
$page->setTitle('Page Title')
     ->setMeta('description', 'Page description')
     ->setSeo('og:image', 'https://example.com/image.jpg')
     ->setSeo('twitter:card', 'summary_large_image')
     ->noIndex(); // Staging environment
```

### 5. Separation of Concerns

Canvas enforces clean architecture through separation:

- **Node**: Structure and hierarchy (the "what")
- **Canvas**: Root container with template information (the "where")
- **Page**: Document metadata and policies (the "metadata")
- **Renderer**: Presentation logic (the "how")
- **Response**: HTTP delivery (the "transport")

This separation makes testing easier, code more maintainable, and allows different parts of your application to evolve independently.

## Architecture

### Node System

The Node class provides a flexible tree structure for representing UI components or document elements. Each node contains:

- Type identifier (component name)
- Arbitrary properties (JSON-serializable)
- Child nodes
- Parent reference

Nodes can be searched, traversed, and manipulated programmatically:

```php
$root = new Node('div', ['class' => 'container']);
$root->addChild(new Node('h1', ['text' => 'Welcome']))
     ->addChild(new Node('p', ['text' => 'Content']));

// Search the tree
$button = $root->getChildByName('Button');
$primary = $root->getChildWhereProp('variant', 'primary');
```

### Canvas (Root Container)

Canvas extends Node to represent the complete document, adding template information:

```php
$canvas = new Canvas('layouts/app', [
    'title' => 'My Page',
    'theme' => 'dark'
]);

// Change templates dynamically
if ($isMobile) {
    $canvas->setTemplate('layouts/mobile');
}
```

### Page (Document Configuration)

Page combines a Canvas with complete document metadata and behavioral rules:

```php
$page = new Page($canvas);
$page->setTitle('Welcome')
     ->setLocale('en-US')
     ->setCharset('UTF-8')
     ->enableCsp()
     ->forceHttps()
     ->noCache();
```

### Renderer (Pluggable Rendering)

Implement the `CanvasPageRenderer` interface to define how pages are rendered:

```php
class BladeRenderer implements CanvasPageRenderer
{
    public function generateHtml(Page $page, array &$sourceCode): void
    {
        $canvas = $page->getCanvas();
        $html = view($canvas->getTemplate(), [
            'page' => $page,
            'canvas' => $canvas
        ])->render();
        
        $sourceCode[] = $html;
    }
}
```

### Response (HTTP Delivery)

Response handles the complete HTTP response lifecycle:

```php
$response = new Response($renderer, $page);
$response->setCompressionLevel(6)
         ->setStatusCode(200)
         ->addHeader('X-Custom-Header', 'value')
         ->send();
```

## Performance Features

### Efficient String Building

Using array-based string building avoids the performance penalty of repeated string concatenation. For large HTML documents, this can provide significant performance improvements.

### Configurable Compression

Gzip compression with configurable levels (1-9) allows you to balance compression ratio against CPU usage based on your needs:

- Level 1-3: Fast compression for dynamic content
- Level 6: Balanced (default)
- Level 9: Maximum compression for static assets

### Bitwise Flag Operations

Page flags use bitwise operations for efficient storage and fast checks:

```php
// Set multiple flags efficiently
$page->noIndex()->noFollow()->noCache();

// Fast flag checking internally
if (($flags & Page::NO_CACHE) === Page::NO_CACHE) {
    // Apply no-cache headers
}
```

## Security Features

### Content Security Policy

Fluent API for building CSP directives:

```php
$page->enableCsp()
     ->allowScriptFrom("'self'", 'https://cdn.jsdelivr.net')
     ->allowStyleFrom("'self'", "'unsafe-inline'")
     ->allowImageFrom('https:', 'data:')
     ->allowConnectTo("'self'", 'https://api.example.com')
     ->blockAllMixed()
     ->requireSri();
```

### Automatic Security Headers

Response automatically generates security headers:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Strict-Transport-Security` (when HTTPS is enforced)

### HTTPS Enforcement

Built-in support for forcing HTTPS connections:

```php
$page->forceHttps();
// Generates: Strict-Transport-Security: max-age=31536000; includeSubDomains
```

## Use Cases

### Traditional Server-Rendered Applications

Use Canvas with your preferred template engine (Blade, Twig, plain PHP) for traditional server-side rendering with enhanced performance and security features.

### Single Page Applications

Canvas includes a built-in `SinglePageApplicationRenderer` for SPAs with full control over assets and state injection:

```php
use Hudsxn\Canvas\Renderers\SinglePageApplicationRenderer;

$renderer = new SinglePageApplicationRenderer();
$renderer->setJsUrl('/dist/app.js')
         ->setCssUrl('/dist/app.css')
         ->setMountElement('app')
         ->setStateVariable('__PAGE_STATE__');

$page = new Page($canvas);
$page->setTitle('My SPA')
     ->setMeta('description', 'A React application')
     ->enableCsp()
     ->allowScriptFrom("'self'");

$response = new Response($renderer, $page);
$response->send();
```

The SPA renderer generates a minimal HTML shell with:
- Automatic viewport configuration for mobile optimization
- CSP-compliant nonce-based inline script injection
- Configurable mount points and state variables
- Support for single or multiple JS/CSS bundles (vendor splitting)
- Complete page state serialized to JSON for client-side hydration

**Multiple Asset Support:**
```php
// Vendor + App splitting
$renderer->setJsUrls([
    '/dist/vendor.js',
    '/dist/app.js'
])->setCssUrls([
    'https://fonts.googleapis.com/css2?family=Inter',
    '/dist/app.css'
]);
```

### Hybrid Applications

Combine server-side rendering with client-side hydration for optimal performance and SEO:

1. Server renders initial HTML
2. Canvas state serialized to JSON
3. Client-side framework hydrates from JSON

### Static Site Generation

Pre-render pages to static HTML files:

```php
$html = $response->render();
file_put_contents("dist/page-{$id}.html", $html);
```

## Getting Started

### Installation

```bash
composer require hudsxn/canvas
```

### Basic Example

```php
use Hudsxn\Canvas\Objects\{Canvas, Page, Node};
use Hudsxn\Canvas\Response;

// Build the tree
$canvas = new Canvas('layouts/default');
$canvas->addChild(new Node('Header', ['logo' => 'logo.png']))
       ->addChild(new Node('Main', ['content' => 'Welcome!']))
       ->addChild(new Node('Footer'));

// Configure the page
$page = new Page($canvas);
$page->setTitle('Welcome to My Site')
     ->setMeta('description', 'A great website')
     ->enableCsp()
     ->allowScriptFrom("'self'")
     ->forceHttps();

// Render and send
$renderer = new MyRenderer();
$response = new Response($renderer, $page);
$response->send();
```

### Quick Start: Single Page Application

Canvas includes a production-ready SPA renderer:

```php
use Hudsxn\Canvas\Objects\{Canvas, Page};
use Hudsxn\Canvas\Renderers\SinglePageApplicationRenderer;
use Hudsxn\Canvas\Response;

// Create your page structure
$canvas = new Canvas('spa-app');
$canvas->addChild(new Node('Dashboard', ['userId' => 123]));

$page = new Page($canvas);
$page->setTitle('My React App')
     ->setMeta('viewport', 'width=device-width, initial-scale=1')
     ->enableCsp()
     ->allowScriptFrom("'self'", 'https://cdn.example.com')
     ->allowStyleFrom("'self'");

// Configure the SPA renderer
$renderer = new SinglePageApplicationRenderer();
$renderer->setJsUrl('/dist/app.js')
         ->setCssUrl('/dist/app.css');

// Send the response
$response = new Response($renderer, $page);
$response->send();
```

The client-side app can access the state:
```javascript
// Your React/Vue/Angular app
const state = window.__PAGE_STATE__;
console.log(state.canvas); // Full Canvas tree
console.log(state.title);  // "My React App"
```

### Implementing a Renderer

```php
use Hudsxn\Canvas\Contracts\CanvasPageRenderer;
use Hudsxn\Canvas\Objects\Page;

class MyRenderer implements CanvasPageRenderer
{
    public function generateHtml(Page $page, array &$sourceCode): void
    {
        $canvas = $page->getCanvas();
        
        $sourceCode[] = '<!DOCTYPE html>';
        $sourceCode[] = '<html lang="' . $page->getLocale() . '">';
        $sourceCode[] = '<head>';
        $sourceCode[] = '<meta charset="' . $page->getCharset() . '">';
        $sourceCode[] = '<title>' . htmlspecialchars($page->getTitle()) . '</title>';
        
        // Render meta tags
        foreach ($page->getMetaTags() as $name => $content) {
            $sourceCode[] = '<meta name="' . $name . '" content="' . htmlspecialchars($content) . '">';
        }
        
        $sourceCode[] = '</head>';
        $sourceCode[] = '<body>';
        
        // Render canvas children
        foreach ($canvas->getChildren() as $node) {
            $sourceCode[] = $this->renderNode($node);
        }
        
        $sourceCode[] = '</body>';
        $sourceCode[] = '</html>';
    }
    
    private function renderNode($node): string
    {
        // Your component rendering logic
        return '<div>' . $node->getName() . '</div>';
    }
}
```

## Design Philosophy

Canvas is built on several key principles:

**Flexibility Over Convention**: Choose your own rendering approach, template engine, and architecture. Canvas provides structure without imposing rigid conventions.

**Performance by Default**: Built-in optimizations like array-based string building and gzip compression mean you get good performance without extra effort.

**Security by Design**: Making security features easy to use encourages their adoption. CSP configuration should be as simple as method chaining.

**Separation of Concerns**: Clear boundaries between structure (Node), metadata (Page), rendering (Renderer), and transport (Response) make code more maintainable and testable.

**Progressive Enhancement**: Start simple and add features as needed. Use basic rendering or add compression, security headers, and advanced CSP as your application grows.

**Batteries Included**: While remaining flexible, Canvas includes a production-ready `SinglePageApplicationRenderer` so you can start building immediately without boilerplate.

## Bundled Renderer

### SinglePageApplicationRenderer

A production-ready renderer for React, Vue, Angular, and other client-side frameworks.

**Features:**
- CSP-compliant nonce-based script injection
- Automatic viewport configuration
- Support for single or multiple JS/CSS bundles
- Configurable mount points and state variables
- SEO-friendly meta tag generation
- Automatic state serialization

**Configuration Options:**

```php
$renderer = new SinglePageApplicationRenderer();

// Asset configuration
$renderer->setJsUrl('/dist/app.js');              // Single JS file
$renderer->setJsUrls([...]);                      // Multiple JS files
$renderer->setCssUrl('/dist/app.css');            // Single CSS file
$renderer->setCssUrls([...]);                     // Multiple CSS files

// Customization
$renderer->setMountElement('root');               // Change mount point
$renderer->setStateVariable('INITIAL_STATE');     // Change state variable
$renderer->includeViewport(false);                // Disable default viewport
```

**Generated HTML Structure:**

```html
<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My App</title>
    <link rel="stylesheet" href="/dist/app.css">
</head>
<body>
    <div id="app"></div>
    <script nonce="randomBase64Nonce">
        window.__PAGE_STATE__ = {
            "title": "My App",
            "canvas": {...},
            "meta": {...}
        };
    </script>
    <script src="/dist/app.js" nonce="randomBase64Nonce" defer></script>
</body>
</html>
```

**Advanced Usage:**

```php
// Production build with vendor splitting
$renderer = new SinglePageApplicationRenderer();
$renderer->setJsUrls([
    'https://cdn.example.com/vendor.js',
    'https://cdn.example.com/app.js'
])->setCssUrls([
    'https://fonts.googleapis.com/css2?family=Inter',
    'https://cdn.example.com/app.css'
])->setMountElement('react-root')
  ->setStateVariable('APP_DATA');

// Configure CSP for external resources
$page->enableCsp()
     ->allowScriptFrom("'self'", 'https://cdn.example.com')
     ->allowStyleFrom("'self'", 'https://fonts.googleapis.com')
     ->allowFontFrom('https://fonts.gstatic.com');
```

## License

MIT License - see LICENSE file for details.