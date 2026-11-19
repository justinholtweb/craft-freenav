<?php

namespace justinholt\freenav\elements;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\actions\Delete;
use craft\elements\actions\SetStatus;
use craft\helpers\App;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use justinholt\freenav\elements\db\NodeQuery;
use justinholt\freenav\enums\NodeType as NodeTypeEnum;
use justinholt\freenav\enums\Propagation;
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
    public ?int $linkedElementId = null;
    public string $nodeType = 'custom';
    public ?string $customUrl = null;
    public ?string $classes = null;
    public ?string $urlSuffix = null;
    public array|string|null $customAttributes = null;
    public array|string|null $data = null;
    public bool $newWindow = false;
    public ?string $icon = null;
    public ?string $badge = null;
    public array|string|null $visibilityRules = null;
    public ?bool $deletedWithMenu = null;
    public mixed $content = null;

    public function __construct($config = [])
    {
        // Filter out properties not defined on this class to avoid UnknownPropertyException
        if (is_array($config)) {
            $config = array_filter($config, function($key) {
                return property_exists($this, $key) || method_exists($this, 'set' . ucfirst($key));
            }, ARRAY_FILTER_USE_KEY);
        }

        parent::__construct($config);
    }

    private ?ElementInterface $_linkedElement = null;
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

    public static function defineSources(?string $context = null): array
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

    protected static function defineActions(?string $source = null): array
    {
        return [
            Delete::class,
            SetStatus::class,
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['title', 'customUrl', 'classes', 'nodeType'];
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
            $baseUrl = $this->_parseUrl($this->customUrl);
        }

        if ($baseUrl === null) {
            return null;
        }

        if ($this->urlSuffix) {
            $baseUrl .= $this->urlSuffix;

            // Checked again with the suffix on: a relative custom URL such as `java` plus a suffix
            // of `script:…` would otherwise make a `javascript:` link. This also covers suffixes
            // saved before the suffix rule existed.
            if (!self::_hasSafeScheme($baseUrl)) {
                return null;
            }
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

    public function getLinkedElement(): ?ElementInterface
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
            $this->siteId,
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
        /** @var Node[] $children */
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

        // Custom attributes. Unsafe names are skipped here as well as refused on save, so a menu
        // saved before 5.2.1 (or written straight to the database) can't put script on the page.
        $customAttrs = $this->getCustomAttributesArray();
        foreach ($customAttrs as $attr) {
            if (!is_array($attr) || empty($attr['key']) || !self::isSafeAttributeName((string)$attr['key'])) {
                continue;
            }

            $value = self::customAttributeValue($attr['value'] ?? null);

            if ($value !== null) {
                $attrs[$attr['key']] = $value;
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
        $sitesService = Craft::$app->getSites();
        $enabledSiteIds = $menu->getEnabledSiteIds();
        $siteId = $this->siteId ?? $sitesService->getCurrentSite()->id;
        $site = $sitesService->getSiteById($siteId);

        $sites = [];

        foreach ($enabledSiteIds as $enabledSiteId) {
            $enabledSite = $sitesService->getSiteById($enabledSiteId);

            if (!$enabledSite) {
                continue;
            }

            $include = match ($menu->getPropagationMethod()) {
                Propagation::None => $enabledSiteId === $siteId,
                Propagation::SiteGroup => $enabledSite->groupId === $site?->groupId,
                Propagation::Language => $enabledSite->language === $site?->language,
                Propagation::All => true,
            };

            if ($include) {
                $sites[] = [
                    'siteId' => $enabledSiteId,
                    'propagate' => true,
                    'enabledByDefault' => true,
                ];
            }
        }

        // Nothing matched, which means the node's own site isn't one the menu is enabled
        // for. Report the menu's sites so Craft raises its "unsupported site" error
        // against the real list rather than silently saving somewhere unexpected.
        if (empty($sites)) {
            foreach ($enabledSiteIds as $enabledSiteId) {
                $sites[] = [
                    'siteId' => $enabledSiteId,
                    'propagate' => true,
                    'enabledByDefault' => true,
                ];
            }
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
        $record->linkedElementId = $this->linkedElementId;
        $record->nodeType = $this->nodeType;
        $record->customUrl = $this->customUrl;
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

    public function afterMoveInStructure(int $structureId): void
    {
        // Reordering a node changes the rendered menu without saving the element,
        // so the cache has to be invalidated off the move itself
        if ($this->menuId) {
            try {
                FreeNav::getInstance()->getMenuCache()->invalidate($this->getMenu()->handle);
            } catch (InvalidConfigException) {
            }
        }

        parent::afterMoveInStructure($structureId);
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

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['customUrl'], function(string $attribute) {
            if ($this->customUrl && $this->getNodeType() === NodeTypeEnum::Custom && $this->_parseUrl($this->customUrl) === null) {
                $this->addError($attribute, Craft::t('free-nav', 'Use a web address, a path, an anchor, or a mailto:, tel: or sms: link. Environment variables and aliases must resolve to an http(s) address.'));
            }
        }, 'skipOnEmpty' => true];
        $rules[] = [['urlSuffix'], function(string $attribute) {
            if (!preg_match('/^[?#&\/]/', (string)$this->urlSuffix)) {
                $this->addError($attribute, Craft::t('free-nav', 'A URL suffix starts with #, ?, & or /.'));
            }
        }, 'skipOnEmpty' => true];
        $rules[] = [['customAttributes'], function(string $attribute) {
            foreach ($this->getCustomAttributesArray() as $attr) {
                $key = trim((string)(is_array($attr) ? ($attr['key'] ?? '') : ''));
                if ($key !== '' && !self::isSafeAttributeName($key)) {
                    $this->addError($attribute, Craft::t('free-nav', '“{key}” can’t be used as a custom attribute. Use data-*, aria-*, title, rel, id, lang, role or another plain attribute; event handlers, URL attributes, style and framework directives such as x-* or hx-* aren’t allowed.', ['key' => $key]));
                } elseif ($key !== '' && self::customAttributeValue($attr['value'] ?? null) === null) {
                    $this->addError($attribute, Craft::t('free-nav', 'The value of “{key}” must be text.', ['key' => $key]));
                }
            }
        }, 'skipOnEmpty' => true];

        return $rules;
    }

    /**
     * Attribute names a custom attribute may use on a front-end link.
     *
     * Custom attributes are written by anyone who can edit a menu, and land on every page that
     * renders it, so this is an allowlist: `aria-*`, `data-*`, and a handful of plain attributes
     * (`title`, `rel`, `id`, `lang`, `role`, `itemprop`…). Event handlers, URL attributes and
     * `style` are not on it, and neither are front-end framework directives that run code from
     * an attribute's value: Alpine (`x-*`), htmx (`hx-*`, `data-hx-*`), Vue (`v-*`), Angular
     * (`ng-*`, `data-ng-*`) and Datastar's script-running `data-*` attributes.
     */
    public static function isSafeAttributeName(string $name): bool
    {
        $lower = strtolower($name);

        if (in_array($lower, [
            'title', 'rel', 'id', 'lang', 'dir', 'role', 'tabindex', 'accesskey', 'translate',
            'hreflang', 'download', 'type', 'itemprop', 'itemscope', 'itemtype', 'itemid', 'referrerpolicy',
        ], true)) {
            return true;
        }

        if (preg_match('/^aria-[a-z]+$/', $lower)) {
            return true;
        }

        if (!preg_match('/^data-[a-z0-9][a-z0-9_-]*$/', $lower)) {
            return false;
        }

        // data-* attributes that front-end frameworks evaluate as code: htmx and Angular's data-
        // forms, and Datastar's attribute plugins. A site running some other framework that
        // executes data-* attributes should treat menu editors as trusted.
        return !preg_match('/^data-(hx-|ng-|x-|v-|on|bind|attr|signals|json-signals|effect|computed|init|ref|indicator|persist|text|show|class|style|model|ignore|scroll-into-view|view-transition|replace-url|query-string|custom-validity|preserve-attr|star-|animate|match-media|store|intersects|teleport)/', $lower);
    }

    /**
     * A custom attribute's value as a string, or null when it isn't a scalar. Craft's HTML helper
     * expands an array value into sub-attributes whose names are never checked.
     */
    public static function customAttributeValue(mixed $value): ?string
    {
        if ($value === null) {
            return '';
        }

        return is_scalar($value) ? (string)$value : null;
    }

    public static function safeCustomAttributes(?array $attributes): ?array
    {
        if ($attributes === null) {
            return null;
        }

        return array_values(array_filter($attributes, fn($attr) => is_array($attr)
            && self::isSafeAttributeName(trim((string)($attr['key'] ?? '')))
            && self::customAttributeValue($attr['value'] ?? null) !== null));
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

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'nodeType' => Html::tag('span', $this->getNodeType()->label(), [
                'style' => 'color: ' . $this->getNodeType()->color(),
            ]),
            'newWindow' => $this->newWindow ? '<span data-icon="check"></span>' : '',
            default => parent::attributeHtml($attribute),
        };
    }

    /**
     * The custom URL a visitor may follow, or null.
     *
     * Anyone with "edit nodes" writes this, and it ends up in an `href` on every page and in the
     * REST API. So until 5.1.5 two things were possible that should not be: a `javascript:` link,
     * and `$CRAFT_SECURITY_KEY` (or any other secret) resolved by `parseEnv()` and printed for
     * the world. Environment variables and aliases now have to come out as an http(s) address,
     * and only link schemes that cannot run anything are allowed through.
     */
    private function _parseUrl(?string $url): ?string
    {
        $url = trim((string)$url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '$') || str_starts_with($url, '@')) {
            $resolved = str_starts_with($url, '$') ? App::parseEnv($url) : Craft::getAlias($url, false);

            if (!is_string($resolved) || !preg_match('~^https?://[^/?#@\s]+(?:[/?#]\S*)?$~i', $resolved)) {
                return null;
            }

            return $resolved;
        }

        return self::_hasSafeScheme($url) ? $url : null;
    }

    /**
     * A URL with no scheme (relative), or an http(s), mailto, tel or sms one.
     */
    private static function _hasSafeScheme(string $url): bool
    {
        // Browsers ignore whitespace and control characters inside a scheme, so `java\tscript:`
        // is still JavaScript.
        $compact = preg_replace('/[\x00-\x20]+/', '', $url) ?? '';

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $compact, $m)) {
            return in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel', 'sms'], true);
        }

        return true;
    }
}
