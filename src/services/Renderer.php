<?php

namespace justinholt\freenav\services;

use Craft;
use craft\helpers\Template;
use justinholt\freenav\elements\Node;
use justinholt\freenav\enums\Preset;
use justinholt\freenav\FreeNav;
use justinholt\freenav\helpers\NodeHelper;
use Twig\Markup;
use yii\base\Component;

class Renderer extends Component
{
    public function render(string $handle, array $options = []): Markup
    {
        $settings = FreeNav::getInstance()->getSettings();

        // Merge defaults
        $options = array_merge([
            'preset' => $settings->defaultPreset,
            'id' => null,
            'class' => null,
            'ulClass' => null,
            'liClass' => null,
            'aClass' => null,
            'activeClass' => $settings->activeClass,
            'hasChildrenClass' => $settings->hasChildrenClass,
            'maxLevel' => null,
            'overrideTemplate' => null,
            'cache' => $settings->cacheEnabled,
            'cacheDuration' => $settings->cacheDuration,
            'aria' => $settings->ariaEnabled,
            'visibilityCheck' => true,
        ], $options);

        // Check cache
        if ($options['cache']) {
            $siteId = (string)Craft::$app->getSites()->getCurrentSite()->id;
            $cacheKey = md5(json_encode($options));
            $cached = FreeNav::getInstance()->getMenuCache()->get($handle, $siteId, $cacheKey);

            if ($cached !== null) {
                return Template::raw($cached);
            }
        }

        // Get preset
        $preset = Preset::tryFrom($options['preset'] ?? 'default') ?? Preset::Default;

        // Build the HTML
        $html = $this->_renderMenu($handle, $preset, $options);

        // Store in cache
        if ($options['cache']) {
            $siteId = (string)Craft::$app->getSites()->getCurrentSite()->id;
            $cacheKey = md5(json_encode($options));
            FreeNav::getInstance()->getMenuCache()->set(
                $handle,
                $siteId,
                $cacheKey,
                $html,
                $options['cacheDuration'],
            );
        }

        return Template::raw($html);
    }

    public function renderPreset(string $handle, Preset $preset, array $options = []): Markup
    {
        $options['preset'] = $preset->value;
        return $this->render($handle, $options);
    }

    public function tree(string $handle, array $criteria = []): array
    {
        $query = Node::find()
            ->menuHandle($handle)
            ->status('enabled');

        foreach ($criteria as $key => $value) {
            if (method_exists($query, $key)) {
                $query->$key($value);
            } else {
                $query->$key = $value;
            }
        }

        $nodes = $query->all();

        return NodeHelper::buildTree($nodes);
    }

    public function getActiveNode(string $handle): ?Node
    {
        $nodes = Node::find()
            ->menuHandle($handle)
            ->status('enabled')
            ->all();

        foreach ($nodes as $node) {
            if ($node->isCurrent()) {
                return $node;
            }
        }

        return null;
    }

    private function _renderMenu(string $handle, Preset $preset, array $options): string
    {
        // Custom template override
        if (!empty($options['overrideTemplate'])) {
            $templatePath = $options['overrideTemplate'];
        } else {
            $templatePath = 'free-nav/_presets/' . $preset->templateName();
        }

        // Get nodes
        $query = Node::find()
            ->menuHandle($handle)
            ->status('enabled');

        if ($options['maxLevel'] ?? null) {
            $query->level('<= ' . $options['maxLevel']);
        }

        $nodes = $query->all();

        // Filter by visibility
        if ($options['visibilityCheck']) {
            $nodes = array_filter($nodes, fn(Node $node) => $node->isVisible());

            // Presets nest by level, so drop the descendants of any node that was
            // filtered out instead of letting them surface as their own top-level items
            $nodes = NodeHelper::flattenTree(NodeHelper::buildTree(array_values($nodes)));
        }

        if (empty($nodes)) {
            return '';
        }

        $menu = FreeNav::getInstance()->getMenus()->getMenuByHandle($handle);

        // Render template
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        // If using built-in preset, use CP template mode to find it in plugin templates
        if (empty($options['overrideTemplate'])) {
            $view->setTemplateMode($view::TEMPLATE_MODE_CP);
        }

        try {
            $html = $view->renderTemplate($templatePath, [
                'nodes' => $nodes,
                'menu' => $menu,
                'options' => $options,
            ]);
        } finally {
            $view->setTemplateMode($oldTemplateMode);
        }

        return $html;
    }
}
