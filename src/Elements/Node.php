<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Elements;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Element\Actions\Delete;
use CraftCms\Cms\Element\Actions\SetStatus;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\HtmlString;
use JustinHoltWeb\FreeNav\Elements\Conditions\NodeCondition;
use JustinHoltWeb\FreeNav\Elements\Queries\NodeQuery;
use JustinHoltWeb\FreeNav\Elements\Validation\NodeRules;
use JustinHoltWeb\FreeNav\Enums\NodeType as NodeTypeEnum;
use JustinHoltWeb\FreeNav\Enums\Propagation;
use JustinHoltWeb\FreeNav\Events\ResolvingNodeActive;
use JustinHoltWeb\FreeNav\Models\Menu;
use JustinHoltWeb\FreeNav\Models\VisibilityRule;
use JustinHoltWeb\FreeNav\Services\Menus;
use JustinHoltWeb\FreeNav\Services\Nodes;
use Override;
use RuntimeException;
use Stringable;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * A menu node.
 *
 * Nodes are elements: they live in `elements`, with their own columns in `freenav_nodes` (same ID),
 * and in their menu's structure, which is the only place the hierarchy is kept.
 *
 * @property NodeRules $ruleset
 */
#[Ruleset(NodeRules::class)] // @phpstan-ignore argument.type (Ruleset's template isn't covariant; core's own elements do this)
class Node extends Element
{
    /**
     * The node attributes the control panel's node editor may set. Everything else on a node — its
     * menu, type and linked element — is decided when it's created, so a crafted save request can't
     * move a node into a menu its author can't edit.
     */
    public const array EDITABLE_ATTRIBUTES = [
        'title', 'customUrl', 'classes', 'urlSuffix', 'customAttributes', 'newWindow', 'icon', 'badge',
        'visibilityRules',
    ];

    public ?int $menuId = null;

    public ?int $linkedElementId = null;

    public string $nodeType = 'custom';

    public ?string $customUrl = null;

    public ?string $classes = null;

    public ?string $urlSuffix = null;

    /** @var array<mixed>|string|null */
    public array|string|null $customAttributes = null;

    /** @var array<mixed>|string|null */
    public array|string|null $data = null;

    public bool $newWindow = false;

    public ?string $icon = null;

    public ?string $badge = null;

    /** @var array<mixed>|string|null */
    public array|string|null $visibilityRules = null;

    public ?bool $deletedWithMenu = null;

    private ?ElementInterface $linkedElement = null;

    private bool $linkedElementLoaded = false;

    private ?Menu $menu = null;

    #[Override]
    public static function displayName(): string
    {
        return t('Navigation Node', category: 'free-nav');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Navigation Nodes', category: 'free-nav');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('navigation node', category: 'free-nav');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('navigation nodes', category: 'free-nav');
    }

    #[Override]
    public static function refHandle(): ?string
    {
        return 'freenavnode';
    }

    #[Override]
    public static function hasTitles(): bool
    {
        return true;
    }

    #[Override]
    public static function hasStatuses(): bool
    {
        return true;
    }

    #[Override]
    public static function isLocalized(): bool
    {
        return true;
    }

    #[Override]
    public static function trackChanges(): bool
    {
        return true;
    }

    #[Override]
    public static function hasStructures(): bool
    {
        return true;
    }

    #[Override]
    public static function find(): NodeQuery
    {
        return new NodeQuery;
    }

    #[Override]
    public static function createCondition(): ElementConditionInterface
    {
        return new NodeCondition(self::class);
    }

    #[Override]
    public function getGqlTypeName(): string
    {
        return self::gqlTypeName($this->getMenu());
    }

    public static function gqlTypeName(Menu $menu): string
    {
        return $menu->handle.'_FreeNavNode';
    }

    #[Override]
    public static function gqlScopesByContext(mixed $context): array
    {
        /** @var Menu $context */
        return ["freeNavMenus.{$context->uid}:read"];
    }

    /**
     * One source per menu, each in structure mode. Only the menus the user can work on are listed,
     * and the tree is only draggable for those whose nodes they may edit — the core index uses
     * `structureEditable` as the only gate on moving.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    protected static function defineSources(string $context): array
    {
        $menus = app(Menus::class);
        $user = currentUserElement();
        $sources = [];

        foreach ($menus->getAllMenus() as $menu) {
            $canEdit = $user ? $menus->canEditNodes($user, $menu) : true;

            if ($user && ! $canEdit && ! $menus->canManageMenu($user, $menu)) {
                continue;
            }

            $sources[] = [
                'key' => 'menu:'.$menu->uid,
                'label' => $menu->name,
                'data' => ['handle' => $menu->handle, 'menu-id' => $menu->id],
                'criteria' => ['menuId' => $menu->id],
                'structureId' => $menu->structureId,
                'structureEditable' => $canEdit,
                'defaultSort' => ['structure', 'asc'],
                'defaultViewMode' => 'structure',
            ];
        }

        return $sources;
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return array_merge(parent::defineTableAttributes(), [
            'nodeType' => ['label' => t('Node Type', category: 'free-nav')],
            'url' => ['label' => t('URL', category: 'free-nav')],
            'classes' => ['label' => t('Classes', category: 'free-nav')],
            'newWindow' => ['label' => t('New Window', category: 'free-nav')],
        ]);
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['status', 'nodeType', 'url'];
    }

    #[Override]
    protected static function defineActions(string $source): array
    {
        return [
            SetStatus::class,
            Delete::class,
        ];
    }

    #[Override]
    protected static function defineSearchableAttributes(): array
    {
        return ['title', 'customUrl', 'classes', 'nodeType'];
    }

    #[Override]
    protected function attributeHtml(string $attribute): string|Stringable
    {
        return match ($attribute) {
            'nodeType' => Html::tag('span', Html::encode($this->getNodeType()->label()), [
                'style' => 'color: '.$this->getNodeType()->color(),
            ]),
            'url' => ($url = $this->getUrl()) !== null ? Html::tag('code', Html::encode($url)) : '',
            'newWindow' => $this->newWindow ? Html::encode(t('Yes', category: 'free-nav')) : '',
            default => parent::attributeHtml($attribute),
        };
    }

    // URLs and links
    // -------------------------------------------------------------------------

    /**
     * The URL a visitor should follow. `customUrl` is only the stored value for custom nodes; this
     * resolves linked elements and the suffix, and is the only accessor for the URL to print.
     */
    #[Override]
    public function getUrl(): ?string
    {
        $nodeType = $this->getNodeType();

        if (! $nodeType->hasUrl()) {
            return null;
        }

        if ($nodeType->isElement()) {
            $baseUrl = $this->getLinkedElement()?->getUrl();
        } else {
            $baseUrl = self::resolveCustomUrl($this->customUrl);
        }

        if ($baseUrl === null) {
            return null;
        }

        if ($this->urlSuffix) {
            $baseUrl .= $this->urlSuffix;

            // Checked again with the suffix on: a relative custom URL such as `java` plus a suffix
            // of `script:…` would otherwise make a `javascript:` link. This also covers suffixes
            // saved before the suffix rule existed.
            if (! self::hasSafeScheme($baseUrl)) {
                return null;
            }
        }

        return $baseUrl;
    }

    #[Override]
    public function getLink(): ?HtmlString
    {
        $url = $this->getUrl();
        $attrs = $this->getLinkAttributes();

        if ($url === null) {
            return new HtmlString(Html::tag('span', Html::encode((string) $this->title), $attrs));
        }

        $attrs['href'] = $url;

        return new HtmlString(Html::tag('a', Html::encode((string) $this->title), $attrs));
    }

    public function getTarget(): string
    {
        return $this->newWindow ? '_blank' : '';
    }

    public function getLinkedElement(): ?ElementInterface
    {
        if ($this->linkedElementLoaded) {
            return $this->linkedElement;
        }

        $this->linkedElementLoaded = true;
        $elementType = $this->getNodeType()->elementType();

        if (! $this->linkedElementId || ! $elementType) {
            return null;
        }

        return $this->linkedElement = Elements::getElementById($this->linkedElementId, $elementType, $this->siteId);
    }

    public function isActive(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        if (Event::hasListeners(ResolvingNodeActive::class)) {
            event($event = new ResolvingNodeActive($this));

            if ($event->isActive !== null) {
                return $event->isActive;
            }
        }

        return $this->isCurrent() || $this->hasActiveDescendant();
    }

    public function isCurrent(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        $nodeUrl = $this->getUrl();

        if ($nodeUrl === null) {
            return false;
        }

        $currentUrl = request()->fullUrl();
        $nodeUrl = Url::siteUrl($nodeUrl);

        return rtrim($currentUrl, '/') === rtrim($nodeUrl, '/');
    }

    public function hasActiveDescendant(): bool
    {
        $children = $this->getChildren();

        // Iterating an element query directly doesn't run it in Craft 6, so ask for the results.
        foreach ($children instanceof ElementQueryInterface ? $children->all() : $children as $child) {
            if ($child instanceof self && ($child->isCurrent() || $child->hasActiveDescendant())) {
                return true;
            }
        }

        return false;
    }

    public function getMenu(): Menu
    {
        if ($this->menu !== null) {
            return $this->menu;
        }

        if (! $this->menuId) {
            throw new RuntimeException('Node is missing its menu ID');
        }

        return $this->menu = app(Menus::class)->getMenuById($this->menuId)
            ?? throw new RuntimeException('Invalid menu ID: '.$this->menuId);
    }

    public function getNodeType(): NodeTypeEnum
    {
        return NodeTypeEnum::tryFrom($this->nodeType) ?? NodeTypeEnum::Custom;
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
        if (! $this->isElement()) {
            return false;
        }

        $element = $this->getLinkedElement();

        if (! $element) {
            return false;
        }

        return $this->title !== $element->title;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
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
        foreach ($this->getCustomAttributesArray() as $attr) {
            if (! is_array($attr) || empty($attr['key']) || ! self::isSafeAttributeName((string) $attr['key'])) {
                continue;
            }

            $value = self::customAttributeValue($attr['value'] ?? null);

            if ($value !== null) {
                $attrs[$attr['key']] = $value;
            }
        }

        $attrs = array_merge($attrs, $this->getAriaAttributes());

        return array_merge($attrs, $extra);
    }

    /**
     * @return array<string, string>
     */
    public function getAriaAttributes(): array
    {
        $attrs = [];

        if ($this->isCurrent()) {
            $attrs['aria-current'] = 'page';
        }

        if ($this->getChildren()->count()) {
            $attrs['aria-expanded'] = 'false';
            $attrs['aria-haspopup'] = 'true';
        }

        return $attrs;
    }

    public function isVisible(): bool
    {
        foreach ($this->getVisibilityRulesArray() as $ruleData) {
            if (is_array($ruleData) && ! VisibilityRule::fromArray($ruleData)->evaluate()) {
                return false;
            }
        }

        return true;
    }

    public function getIconHtml(): ?HtmlString
    {
        if (empty($this->icon)) {
            return null;
        }

        return new HtmlString(Html::tag('i', '', ['class' => $this->icon, 'aria-hidden' => 'true']));
    }

    public function getBadgeHtml(): ?HtmlString
    {
        if (empty($this->badge)) {
            return null;
        }

        return new HtmlString(Html::tag('span', Html::encode($this->badge), ['class' => 'freenav-badge']));
    }

    /**
     * @return array<mixed>
     */
    public function getCustomAttributesArray(): array
    {
        return self::decodeJsonColumn($this->customAttributes);
    }

    /**
     * @return array<mixed>
     */
    public function getVisibilityRulesArray(): array
    {
        return self::decodeJsonColumn($this->visibilityRules);
    }

    /**
     * @return array<mixed>
     */
    public function getDataArray(): array
    {
        return self::decodeJsonColumn($this->data);
    }

    /**
     * @param  array<mixed>|string|null  $value
     * @return array<mixed>
     */
    private static function decodeJsonColumn(array|string|null $value): array
    {
        if (is_string($value)) {
            $decoded = Json::decodeIfJson($value);

            return is_array($decoded) ? $decoded : [];
        }

        return $value ?? [];
    }

    #[Override]
    public function fields(): array
    {
        $fields = parent::fields();

        // Decode JSON columns as arrays instead of raw strings
        $fields['customAttributes'] = fn () => $this->getCustomAttributesArray();
        $fields['data'] = fn () => $this->getDataArray();
        $fields['visibilityRules'] = fn () => $this->getVisibilityRulesArray();

        return $fields;
    }

    #[Override]
    public function extraFields(): array
    {
        return array_merge(parent::extraFields(), [
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
        return (string) $this->getMenu()->handle;
    }

    public function getMenuName(): string
    {
        return (string) $this->getMenu()->name;
    }

    public function getNodeTypeName(): string
    {
        return $this->getNodeType()->label();
    }

    // Sites, layout, saving
    // -------------------------------------------------------------------------

    #[Override]
    public function getSupportedSites(): array
    {
        $menu = $this->getMenu();
        $enabledSiteIds = $menu->getEnabledSiteIds();
        $siteId = $this->siteId ?? Sites::getCurrentSite()->id;
        $site = Sites::getSiteById((int) $siteId);
        $sites = [];

        foreach ($enabledSiteIds as $enabledSiteId) {
            $enabledSite = Sites::getSiteById($enabledSiteId);

            if (! $enabledSite) {
                continue;
            }

            $include = match ($menu->getPropagationMethod()) {
                Propagation::None => $enabledSiteId === $siteId,
                Propagation::SiteGroup => $enabledSite->groupId === $site?->groupId,
                Propagation::Language => $enabledSite->language === $site?->language,
                Propagation::All => true,
            };

            if ($include) {
                $sites[] = ['siteId' => $enabledSiteId, 'propagate' => true, 'enabledByDefault' => true];
            }
        }

        // Nothing matched, which means the node's own site isn't one the menu is enabled for. Report
        // the menu's sites so Craft raises its "unsupported site" error against the real list rather
        // than silently saving somewhere unexpected.
        if ($sites === []) {
            foreach ($enabledSiteIds as $enabledSiteId) {
                $sites[] = ['siteId' => $enabledSiteId, 'propagate' => true, 'enabledByDefault' => true];
            }
        }

        return $sites;
    }

    #[Override]
    public function getFieldLayout(): ?FieldLayout
    {
        try {
            return $this->getMenu()->getFieldLayout();
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Writes the node's own row. Runs inside the save transaction, once per site.
     */
    #[Override]
    public function afterSave(bool $isNew): void
    {
        $now = now();
        $row = [
            'id' => $this->id,
            'menuId' => $this->menuId,
            'linkedElementId' => $this->linkedElementId,
            'nodeType' => $this->nodeType,
            'customUrl' => $this->customUrl,
            'classes' => $this->classes,
            'urlSuffix' => $this->urlSuffix,
            'customAttributes' => self::encodeJsonColumn($this->customAttributes),
            'data' => self::encodeJsonColumn($this->data),
            'newWindow' => $this->newWindow,
            'icon' => $this->icon,
            'badge' => $this->badge,
            'visibilityRules' => self::encodeJsonColumn($this->visibilityRules),
            'deletedWithMenu' => $this->deletedWithMenu,
            'dateUpdated' => $now,
        ];

        $columns = array_keys($row);

        DB::table('freenav_nodes')->upsert(
            [$row + ['dateCreated' => $now, 'uid' => $this->uid]],
            uniqueBy: ['id'],
            update: $columns,
        );

        if ($this->linkedElementId) {
            app(Nodes::class)->forgetLinkedElementIds();
        }

        parent::afterSave($isNew);
    }

    /**
     * @param  array<mixed>|string|null  $value
     */
    private static function encodeJsonColumn(array|string|null $value): ?string
    {
        if (is_array($value)) {
            return $value === [] ? null : (string) Json::encode($value);
        }

        return $value === '' ? null : $value;
    }

    /**
     * The menu's rendered HTML depends on the node, and the cache entries carry this tag (as Craft
     * namespaces it: `element::<Node class>::menu:<handle>`), so saving or deleting a node clears them.
     */
    #[Override]
    protected function cacheTags(): array
    {
        try {
            return $this->menuId ? ['menu:'.$this->getMenu()->handle] : [];
        } catch (RuntimeException) {
            return [];
        }
    }

    /**
     * The node editor posts every field in the menu's layout. Only node settings are taken from it,
     * and the JSON fields arrive as text.
     *
     * @param  array<string, mixed>  $values
     */
    #[Override]
    public function setAttributesFromRequest(array $values): void
    {
        $values = array_intersect_key($values, array_flip(self::EDITABLE_ATTRIBUTES));

        foreach (['customAttributes', 'visibilityRules'] as $json) {
            if (array_key_exists($json, $values) && is_string($values[$json])) {
                $text = trim($values[$json]);
                $decoded = $text === '' ? null : Json::decodeIfJson($text);
                // Text that isn't JSON is kept as typed, so validation can say so instead of
                // silently emptying the field.
                $values[$json] = is_array($decoded) || $decoded === null ? $decoded : $text;
            }
        }

        parent::setAttributesFromRequest($values);
    }

    // Permissions
    // -------------------------------------------------------------------------

    #[Override]
    public function canView(User $user): bool
    {
        try {
            $menu = $this->getMenu();
        } catch (RuntimeException) {
            return false;
        }

        $menus = app(Menus::class);

        return $menus->canEditNodes($user, $menu) || $menus->canManageMenu($user, $menu);
    }

    #[Override]
    public function canSave(User $user): bool
    {
        try {
            return app(Menus::class)->canEditNodes($user, $this->getMenu());
        } catch (RuntimeException) {
            return false;
        }
    }

    #[Override]
    public function canDelete(User $user): bool
    {
        try {
            return app(Menus::class)->canDeleteNodes($user, $this->getMenu());
        } catch (RuntimeException) {
            return false;
        }
    }

    // Control panel
    // -------------------------------------------------------------------------

    #[Override]
    protected function cpEditUrl(): ?string
    {
        return ElementHelper::elementEditorUrl($this, false);
    }

    #[Override]
    public function getPostEditUrl(): ?string
    {
        return $this->menuId ? cp_url("free-nav/menus/{$this->menuId}/build") : cp_url('free-nav/menus');
    }

    #[Override]
    protected function crumbs(): array
    {
        $crumbs = [
            new ActionItem()->label(t('Menus', category: 'free-nav'))->href(cp_url('free-nav/menus')),
        ];

        try {
            $menu = $this->getMenu();
            $crumbs[] = new ActionItem()->label((string) $menu->name)->href(cp_url("free-nav/menus/{$menu->id}/build"));
        } catch (RuntimeException) {
        }

        return $crumbs;
    }

    #[Override]
    protected function metadata(): array
    {
        $linked = $this->isElement() ? $this->getLinkedElement() : null;

        return [
            t('Node Type', category: 'free-nav') => Html::encode($this->getNodeType()->label()),
            t('Linked element', category: 'free-nav') => $linked
                ? (($cpUrl = $linked->getCpEditUrl()) ? Html::a(Html::encode((string) $linked->title), $cpUrl) : Html::encode((string) $linked->title))
                : false,
            t('URL', category: 'free-nav') => ($url = $this->getUrl()) !== null ? Html::tag('code', Html::encode($url)) : false,
        ];
    }

    // Custom URLs and custom attributes: the two things editors write that end up in every page.
    // -------------------------------------------------------------------------

    /**
     * The custom URL a visitor may follow, or null.
     *
     * Anyone with "edit nodes" writes this, and it ends up in an `href` on every page and in the
     * REST API. So until 5.1.5 two things were possible that should not be: a `javascript:` link,
     * and `$CRAFT_SECURITY_KEY` (or any other secret) resolved and printed for the world.
     * Environment variables and aliases now have to come out as an http(s) address, and only link
     * schemes that cannot run anything are allowed through.
     */
    public static function resolveCustomUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '$') || str_starts_with($url, '@')) {
            // One variable (`$PRIMARY_SITE_URL/about`) or one alias (`@web/about`), nothing nested:
            // Craft 6 also expands `${VAR}` anywhere in a string, which would let a URL carry a
            // secret in its query string.
            if (! preg_match('~^(\$[A-Za-z_][A-Za-z0-9_]*|@[A-Za-z0-9_-]+)(/[^$]*)?$~', $url)) {
                return null;
            }

            $resolved = Env::parse($url);

            if (! is_string($resolved) || ! preg_match('~^https?://[^/?#@\s]+(?:[/?#]\S*)?$~i', $resolved)) {
                return null;
            }

            return $resolved;
        }

        return self::hasSafeScheme($url) ? $url : null;
    }

    /**
     * Whether a link's scheme, if it has one, is one that can't run anything: http(s), mailto,
     * tel or sms. A relative link has none and passes.
     */
    public static function hasSafeScheme(string $url): bool
    {
        // Browsers ignore whitespace and control characters inside a scheme, so `java\tscript:`
        // is still JavaScript.
        $compact = preg_replace('/[\x00-\x20]+/', '', $url) ?? '';

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $compact, $m)) {
            return in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel', 'sms'], true);
        }

        return true;
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

        if (! preg_match('/^data-[a-z0-9][a-z0-9_-]*$/', $lower)) {
            return false;
        }

        // data-* attributes that front-end frameworks evaluate as code: htmx and Angular's data-
        // forms, and Datastar's attribute plugins. A site running some other framework that
        // executes data-* attributes should treat menu editors as trusted.
        return ! preg_match('/^data-(hx-|ng-|x-|v-|on|bind|attr|signals|json-signals|effect|computed|init|ref|indicator|persist|text|show|class|style|model|ignore|scroll-into-view|view-transition|replace-url|query-string|custom-validity|preserve-attr|star-|animate|match-media|store|intersects|teleport)/', $lower);
    }

    /**
     * A custom attribute's value as text, or null when it isn't text.
     *
     * Only scalars: Craft's HTML helper expands an array value for `data`/`aria`-style names into
     * further attributes whose names nobody checked.
     */
    public static function customAttributeValue(mixed $value): ?string
    {
        if ($value === null) {
            return '';
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Drops custom attributes with unsafe names or non-text values. For imports, where one bad
     * attribute shouldn't stop the whole node, or menu, from coming across.
     *
     * @param  array<mixed>|null  $attributes
     * @return array<mixed>|null
     */
    public static function safeCustomAttributes(?array $attributes): ?array
    {
        if ($attributes === null) {
            return null;
        }

        return array_values(array_filter($attributes, fn ($attr) => is_array($attr)
            && self::isSafeAttributeName(trim((string) ($attr['key'] ?? '')))
            && self::customAttributeValue($attr['value'] ?? null) !== null));
    }
}
