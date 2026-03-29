<?php

namespace justinholt\freenav\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\SetStatus;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use justinholt\freenav\elements\db\NodeQuery;
use justinholt\freenav\enums\NodeType as NodeTypeEnum;
use justinholt\freenav\events\NodeActiveEvent;
use justinholt\freenav\FreeNav;
use justinholt\freenav\gql\interfaces\NodeInterface;
use justinholt\freenav\models\Menu;
use justinholt\freenav\models\VisibilityRule;
use justinholt\freenav\records\NodeRecord;
use Twig\Markup;
use yii\base\InvalidConfigException;

class Node extends Element
{
    public const EVENT_NODE_ACTIVE = 'nodeActive';

    public ?int $menuId = null;
    public ?int $parentId = null;
    public ?int $linkedElementId = null;
    public string $nodeType = 'custom';
    public ?string $url = null;
    public ?string $classes = null;
    public ?string $urlSuffix = null;
    public array|string|null $customAttributes = null;
    public array|string|null $data = null;
    public bool $newWindow = false;
    public ?string $icon = null;
    public ?string $badge = null;
    public array|string|null $visibilityRules = null;
    public ?bool $deletedWithMenu = null;

    private ?Element $_linkedElement = null;
    private ?bool $_linkedElementLoaded = false;
    private ?Menu $_menu = null;

    public static function displayName(): string
    {
        return Craft::t('free-nav', 'Navigation Node');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('free-nav', 'Navigation Nodes');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('free-nav', 'navigation node');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('free-nav', 'navigation nodes');
    }

    public static function refHandle(): ?string
    {
        return 'freenavnode';
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public static function trackChanges(): bool
    {
        return true;
    }

    public function getGqlTypeName(): string
    {
        return $this->getMenu()->handle . '_FreeNavNode';
    }

    public static function gqlTypeNameByContext(mixed $context): string
    {
        /** @var Menu $context */
        return $context->handle . '_FreeNavNode';
    }

    public static function gqlScopesByContext(mixed $context): array
    {
        /** @var Menu $context */
        return ["freeNavMenus.{$context->uid}:read"];
    }

    public static function gqlInterfaceType(): string
    {
        return NodeInterface::class;
    }

    public static function find(): NodeQuery
    {
        return new NodeQuery(static::class);
    }

    public static function defineSources(string $context = null): array
    {
        $sources = [];
        $menus = FreeNav::getInstance()->getMenus()->getAllMenus();

        foreach ($menus as $menu) {
            $sources[] = [
                'key' => 'menu:' . $menu->uid,
                'label' => $menu->name,
                'criteria' => ['menuId' => $menu->id],
                'structureId' => $menu->structureId,
                'structureEditable' => true,
                'defaultSort' => ['structure', 'asc'],
            ];
        }

        return $sources;
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'nodeType' => Craft::t('free-nav', 'Node Type'),
            'url' => Craft::t('app', 'URL'),
            'classes' => Craft::t('free-nav', 'Classes'),
            'newWindow' => Craft::t('free-nav', 'New Window'),
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'nodeType', 'url'];
    }

    protected static function defineActions(string $source = null): array
    {
        return [
            Delete::class,
            SetStatus::class,
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['title', 'url', 'classes', 'nodeType'];
    }

    public function getUrl(): ?string
    {
        $nodeTypeEnum = $this->getNodeType();

        if (!$nodeTypeEnum->hasUrl()) {
            return null;
        }

        if ($nodeTypeEnum->isElement()) {
            $element = $this->getLinkedElement();
            $baseUrl = $element?->getUrl();
        } else {
            $baseUrl = $this->_parseUrl($this->url);
        }

        if ($baseUrl === null) {
            return null;
        }

        if ($this->urlSuffix) {
            $baseUrl .= $this->urlSuffix;
        }

        return $baseUrl;
    }

    public function getLink(): Markup
    {
        $url = $this->getUrl();
        $attrs = $this->getLinkAttributes();

        if ($url === null) {
            return Template::raw(Html::tag('span', Html::encode($this->title), $attrs));
        }

        $attrs['href'] = $url;

        return Template::raw(Html::tag('a', Html::encode($this->title), $attrs));
    }

    public function getTarget(): string
    {
        return $this->newWindow ? '_blank' : '';
    }

    public function getLinkedElement(): ?Element
    {
        if ($this->_linkedElementLoaded) {
            return $this->_linkedElement;
        }

        $this->_linkedElementLoaded = true;

        if (!$this->linkedElementId) {
            return null;
        }

        $nodeTypeEnum = $this->getNodeType();
        $elementType = $nodeTypeEnum->elementType();

        if (!$elementType) {
            return null;
        }

        $this->_linkedElement = Craft::$app->getElements()->getElementById(
            $this->linkedElementId,
            $elementType,
            $this->siteId
        );

        return $this->_linkedElement;
    }

    public function isActive(): bool
    {
        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return false;
        }

        // Allow event override
        $event = new NodeActiveEvent(['node' => $this]);
        $this->trigger(self::EVENT_NODE_ACTIVE, $event);

        if ($event->isActive !== null) {
            return $event->isActive;
        }

        return $this->isCurrent() || $this->hasActiveDescendant();
    }

    public function isCurrent(): bool
    {
        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return false;
        }

        $nodeUrl = $this->getUrl();
        if ($nodeUrl === null) {
            return false;
        }

        $currentUrl = $request->getAbsoluteUrl();
        $nodeUrl = UrlHelper::siteUrl($nodeUrl);

        return rtrim($currentUrl, '/') === rtrim($nodeUrl, '/');
    }

    public function hasActiveDescendant(): bool
    {
        $children = $this->getChildren()->all();

        foreach ($children as $child) {
            if ($child->isCurrent() || $child->hasActiveDescendant()) {
                return true;
            }
        }

        return false;
    }

    public function getMenu(): Menu
    {
        if ($this->_menu !== null) {
            return $this->_menu;
        }

        if (!$this->menuId) {
            throw new InvalidConfigException('Node is missing its menu ID');
        }

        $this->_menu = FreeNav::getInstance()->getMenus()->getMenuById($this->menuId);

        if ($this->_menu === null) {
            throw new InvalidConfigException('Invalid menu ID: ' . $this->menuId);
        }

        return $this->_menu;
    }

    public function getNodeType(): NodeTypeEnum
    {
        return NodeTypeEnum::from($this->nodeType);
    }

    public function isElement(): bool
    {
        return $this->getNodeType()->isElement();
    }

    public function isCustom(): bool
    {
        return $this->getNodeType() === NodeTypeEnum::Custom;
    }

    public function isPassive(): bool
    {
        return $this->getNodeType() === NodeTypeEnum::Passive;
    }

    public function isSite(): bool
    {
        return $this->getNodeType() === NodeTypeEnum::Site;
    }

    public function hasOverriddenTitle(): bool
    {
        if (!$this->isElement()) {
            return false;
        }

        $element = $this->getLinkedElement();
        if (!$element) {
            return false;
        }

        return $this->title !== $element->title;
    }

    public function getLinkAttributes(array $extra = []): array
    {
        $attrs = [];

        if ($this->classes) {
            $attrs['class'] = $this->classes;
        }

        if ($this->newWindow) {
            $attrs['target'] = '_blank';
            $attrs['rel'] = 'noopener noreferrer';
        }

        // Custom attributes
        $customAttrs = $this->getCustomAttributesArray();
        foreach ($customAttrs as $attr) {
            if (!empty($attr['key'])) {
                $attrs[$attr['key']] = $attr['value'] ?? '';
            }
        }

        // ARIA attributes
        $ariaAttrs = $this->getAriaAttributes();
        $attrs = array_merge($attrs, $ariaAttrs);

        return array_merge($attrs, $extra);
    }

    public function getAriaAttributes(): array
    {
        $attrs = [];

        if ($this->isCurrent()) {
            $attrs['aria-current'] = 'page';
        }

        $children = $this->getChildren();
        if ($children->count()) {
            $attrs['aria-expanded'] = 'false';
            $attrs['aria-haspopup'] = 'true';
        }

        return $attrs;
    }

    public function isVisible(): bool
    {
        $rules = $this->getVisibilityRulesArray();

        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $ruleData) {
            $rule = new VisibilityRule($ruleData);
            if (!$rule->evaluate()) {
                return false;
            }
        }

        return true;
    }

    public function getIconHtml(): ?Markup
    {
        if (empty($this->icon)) {
            return null;
        }

        return Template::raw(Html::tag('i', '', ['class' => $this->icon, 'aria-hidden' => 'true']));
    }

    public function getBadgeHtml(): ?Markup
    {
        if (empty($this->badge)) {
            return null;
        }

        return Template::raw(Html::tag('span', Html::encode($this->badge), ['class' => 'freenav-badge']));
    }

    public function getCustomAttributesArray(): array
    {
        if (is_string($this->customAttributes)) {
            return Json::decodeIfJson($this->customAttributes) ?: [];
        }

        return $this->customAttributes ?? [];
    }

    public function getVisibilityRulesArray(): array
    {
        if (is_string($this->visibilityRules)) {
            return Json::decodeIfJson($this->visibilityRules) ?: [];
        }

        return $this->visibilityRules ?? [];
    }

    public function getDataArray(): array
    {
        if (is_string($this->data)) {
            return Json::decodeIfJson($this->data) ?: [];
        }

        return $this->data ?? [];
    }

    public function fields(): array
    {
        $fields = parent::fields();

        // Decode JSON columns as arrays instead of raw strings
        $fields['customAttributes'] = fn() => $this->getCustomAttributesArray();
        $fields['data'] = fn() => $this->getDataArray();
        $fields['visibilityRules'] = fn() => $this->getVisibilityRulesArray();

        return $fields;
    }

    public function extraFields(): array
    {
        $fields = parent::extraFields();

        return array_merge($fields, [
            'active',
            'target',
            'menuHandle',
            'menuName',
            'nodeTypeName',
        ]);
    }

    public function getActive(): bool
    {
        return $this->isActive();
    }

    public function getMenuHandle(): string
    {
        return $this->getMenu()->handle;
    }

    public function getMenuName(): string
    {
        return $this->getMenu()->name;
    }

    public function getNodeTypeName(): string
    {
        return $this->getNodeType()->label();
    }

    public function getSupportedSites(): array
    {
        $menu = $this->getMenu();
        $siteSettings = $menu->getSiteSettings();

        $sites = [];
        foreach ($siteSettings as $siteSetting) {
            if ($siteSetting->enabled) {
                $sites[] = [
                    'siteId' => $siteSetting->siteId,
                    'propagate' => true,
                    'enabledByDefault' => true,
                ];
            }
        }

        if (empty($sites)) {
            $sites[] = [
                'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
                'propagate' => true,
                'enabledByDefault' => true,
            ];
        }

        return $sites;
    }

    public function getFieldLayout(): ?\craft\models\FieldLayout
    {
        try {
            return $this->getMenu()->getFieldLayout();
        } catch (InvalidConfigException) {
            return null;
        }
    }

    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = NodeRecord::findOne($this->id);

            if (!$record) {
                throw new InvalidConfigException('Invalid node ID: ' . $this->id);
            }
        } else {
            $record = new NodeRecord();
            $record->id = $this->id;
        }

        $record->menuId = $this->menuId;
        $record->parentId = $this->parentId;
        $record->linkedElementId = $this->linkedElementId;
        $record->nodeType = $this->nodeType;
        $record->url = $this->url;
        $record->classes = $this->classes;
        $record->urlSuffix = $this->urlSuffix;
        $record->customAttributes = is_array($this->customAttributes) ? Json::encode($this->customAttributes) : $this->customAttributes;
        $record->data = is_array($this->data) ? Json::encode($this->data) : $this->data;
        $record->newWindow = $this->newWindow;
        $record->icon = $this->icon;
        $record->badge = $this->badge;
        $record->visibilityRules = is_array($this->visibilityRules) ? Json::encode($this->visibilityRules) : $this->visibilityRules;
        $record->deletedWithMenu = $this->deletedWithMenu;

        $record->save(false);

        // Invalidate menu cache
        if ($this->menuId) {
            try {
                $menu = $this->getMenu();
                FreeNav::getInstance()->getMenuCache()->invalidate($menu->handle);
            } catch (InvalidConfigException) {
            }
        }

        parent::afterSave($isNew);
    }

    public function afterDelete(): void
    {
        // Invalidate menu cache
        if ($this->menuId) {
            try {
                $menu = $this->getMenu();
                FreeNav::getInstance()->getMenuCache()->invalidate($menu->handle);
            } catch (InvalidConfigException) {
            }
        }

        parent::afterDelete();
    }

    public function canView(\craft\elements\User $user): bool
    {
        return true;
    }

    public function canSave(\craft\elements\User $user): bool
    {
        if ($this->menuId) {
            try {
                $menu = $this->getMenu();
                return $user->can('freeNav-editNodes:' . $menu->uid)
                    || $user->can('freeNav-editNodes');
            } catch (InvalidConfigException) {
            }
        }

        return $user->can('freeNav-editNodes');
    }

    public function canDelete(\craft\elements\User $user): bool
    {
        if ($this->menuId) {
            try {
                $menu = $this->getMenu();
                return $user->can('freeNav-deleteNodes:' . $menu->uid)
                    || $user->can('freeNav-deleteNodes');
            } catch (InvalidConfigException) {
            }
        }

        return $user->can('freeNav-deleteNodes');
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'nodeType' => Html::tag('span', $this->getNodeType()->label(), [
                'style' => 'color: ' . $this->getNodeType()->color(),
            ]),
            'newWindow' => $this->newWindow ? '<span data-icon="check"></span>' : '',
            default => parent::tableAttributeHtml($attribute),
        };
    }

    private function _parseUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        // Handle environment variables
        if (str_starts_with($url, '$')) {
            $url = Craft::parseEnv($url);
        }

        // Handle aliases
        if (str_starts_with($url, '@')) {
            $url = Craft::getAlias($url);
        }

        return $url;
    }
}
