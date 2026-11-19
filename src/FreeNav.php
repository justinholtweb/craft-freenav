<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav;

use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Element\Events\ElementDeleted;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\Element\Events\ElementSaving;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\LayoutElements\TitleField;
use CraftCms\Cms\GarbageCollection\Actions\DeletePartialElements;
use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Cms\Plugin\Plugin;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Twig\Variables\CraftVariable;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\User\Data\Permission;
use CraftCms\Cms\View\Events\TemplateGlobalsResolving;
use Illuminate\Support\Facades\Event;
use JustinHoltWeb\FreeNav\Console\Commands\MenusClearCacheCommand;
use JustinHoltWeb\FreeNav\Console\Commands\MenusExportCommand;
use JustinHoltWeb\FreeNav\Console\Commands\MenusImportCommand;
use JustinHoltWeb\FreeNav\Console\Commands\MenusIndexCommand;
use JustinHoltWeb\FreeNav\Console\Commands\MenusResaveNodesCommand;
use JustinHoltWeb\FreeNav\Console\Commands\MigrateFromNavigationCommand;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Enums\Preset;
use JustinHoltWeb\FreeNav\FieldLayoutElements\BadgeField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\CssClassesField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\CustomAttributesField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\CustomUrlField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\IconField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\NewWindowField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\UrlSuffixField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\VisibilityRulesField;
use JustinHoltWeb\FreeNav\Fields\MenuField;
use JustinHoltWeb\FreeNav\Gql\Interfaces\NodeInterface;
use JustinHoltWeb\FreeNav\Gql\Queries\NodeQuery as GqlNodeQuery;
use JustinHoltWeb\FreeNav\Helpers\GqlHelper;
use JustinHoltWeb\FreeNav\Models\Settings;
use JustinHoltWeb\FreeNav\Services\Breadcrumbs;
use JustinHoltWeb\FreeNav\Services\MenuCache;
use JustinHoltWeb\FreeNav\Services\Menus;
use JustinHoltWeb\FreeNav\Services\MenuTransfer;
use JustinHoltWeb\FreeNav\Services\Nodes;
use JustinHoltWeb\FreeNav\Services\NodeTypes;
use JustinHoltWeb\FreeNav\Services\Renderer;
use JustinHoltWeb\FreeNav\Services\VerbbImporter;
use JustinHoltWeb\FreeNav\Variables\FreeNavVariable;
use Override;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * FreeNav — a navigation menu builder.
 *
 * @method Settings getSettings()
 */
class FreeNav extends Plugin
{
    /**
     * FreeNav 5's schema was 1.0.1; the bump runs the Craft 6 upgrade migration.
     */
    public string $schemaVersion = '6.0.0';

    /**
     * Sites upgrade from the last FreeNav 5 release, so its migrations — and its custom-attribute
     * guard — are in place, and `database/migrations/Install.php` can describe the final schema.
     */
    public string $minVersionRequired = '5.2.1';

    public bool $hasCpSection = true;

    public bool $hasCpSettings = true;

    protected array $elementTypes = [
        Node::class,
    ];

    protected array $fieldTypes = [
        MenuField::class,
    ];

    protected array $gqlTypes = [
        NodeInterface::class,
    ];

    protected array $gqlQueries = [
        GqlNodeQuery::class,
    ];

    protected array $commands = [
        MenusIndexCommand::class,
        MenusResaveNodesCommand::class,
        MenusClearCacheCommand::class,
        MenusExportCommand::class,
        MenusImportCommand::class,
        MigrateFromNavigationCommand::class,
    ];

    /**
     * The render presets. A project's own `templates/_freenav/_presets/_<name>.twig` is found
     * first, so a preset can be overridden by creating that file.
     */
    protected array $siteTemplateRoots = [
        '_freenav' => __DIR__.'/../resources/site',
    ];

    #[Override]
    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    public function register(): void
    {
        parent::register();

        // Each memoises something for the request (menus, node types, linked element IDs).
        foreach ([
            Menus::class, Nodes::class, NodeTypes::class, Renderer::class, Breadcrumbs::class,
            MenuCache::class, MenuTransfer::class, VerbbImporter::class,
        ] as $service) {
            $this->app->singleton($service);
        }
    }

    public function boot(): void
    {
        $this->app->bind(Settings::class, fn () => $this->getSettings());

        $this->registerProjectConfigHandlers();
        $this->registerElementEvents();
        $this->registerTemplateVariable();
        $this->registerGql();

        Event::listen(RunningGarbageCollection::class, function (RunningGarbageCollection $event): void {
            $event->garbageCollection->runActions([
                [DeletePartialElements::class, ['elementType' => Node::class, 'table' => 'freenav_nodes']],
            ]);
        });
    }

    // Services, as FreeNav 5 exposed them. In new code, resolve the class from the container.
    // -------------------------------------------------------------------------

    public function getMenus(): Menus
    {
        return $this->app->make(Menus::class);
    }

    public function getNodes(): Nodes
    {
        return $this->app->make(Nodes::class);
    }

    public function getNodeTypes(): NodeTypes
    {
        return $this->app->make(NodeTypes::class);
    }

    public function getRenderer(): Renderer
    {
        return $this->app->make(Renderer::class);
    }

    public function getBreadcrumbs(): Breadcrumbs
    {
        return $this->app->make(Breadcrumbs::class);
    }

    public function getMenuCache(): MenuCache
    {
        return $this->app->make(MenuCache::class);
    }

    public function getMenuTransfer(): MenuTransfer
    {
        return $this->app->make(MenuTransfer::class);
    }

    // Control panel
    // -------------------------------------------------------------------------

    #[Override]
    public function getCpNavItem(): NavItem|array|null
    {
        $item = parent::getCpNavItem();

        if (! $item instanceof NavItem) {
            return $item;
        }

        $item->label('FreeNav');
        $subnav = [
            new NavItem()->label(t('Menus', category: 'free-nav'))->href('free-nav/menus'),
        ];

        $user = currentUserElement();
        $menus = $this->getMenus();

        if ($user && $menus->canManageMenus($user) && $menus->allowAdminChanges()) {
            $subnav[] = new NavItem()->label(t('Import', category: 'free-nav'))->href('free-nav/import');
        }

        if ($user?->admin && $menus->allowAdminChanges()) {
            $subnav[] = new NavItem()->label(t('Settings', category: 'free-nav'))->href('settings/plugins/free-nav');
        }

        return $item->subnav($subnav);
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        $presetOptions = array_map(
            fn (Preset $preset) => ['label' => $preset->label(), 'value' => $preset->value],
            Preset::cases(),
        );

        return Ui::make([
            Heading::make('freenav-cache', t('Cache', category: 'free-nav')),
            Field::make(t('Enable Caching', category: 'free-nav'), Lightswitch::make('cacheEnabled'))
                ->instructions(t('Cache rendered menu HTML for better performance.', category: 'free-nav')),
            Field::make(t('Cache Duration', category: 'free-nav'), Number::make('cacheDuration')->min(0)->suffix(t('seconds', category: 'free-nav')))
                ->instructions(t('How long to cache menu HTML (in seconds). 0 keeps it until something changes.', category: 'free-nav')),

            Heading::make('freenav-rendering', t('Rendering', category: 'free-nav')),
            Field::make(t('Enable ARIA Attributes', category: 'free-nav'), Lightswitch::make('ariaEnabled'))
                ->instructions(t('Automatically add ARIA attributes to rendered menus for accessibility.', category: 'free-nav')),
            Field::make(t('Default Preset', category: 'free-nav'), Choice::make('defaultPreset')->options($presetOptions))
                ->instructions(t('The default rendering preset when none is specified.', category: 'free-nav')),
            Field::make(t('Active Class', category: 'free-nav'), Text::make('activeClass'))
                ->instructions(t('CSS class applied to active menu items.', category: 'free-nav')),
            Field::make(t('Has Children Class', category: 'free-nav'), Text::make('hasChildrenClass'))
                ->instructions(t('CSS class applied to items with children.', category: 'free-nav')),

            Heading::make('freenav-api', t('API', category: 'free-nav')),
            Field::make(t('Enable REST API', category: 'free-nav'), Lightswitch::make('restApiEnabled'))
                ->instructions(t('Enable the built-in REST API endpoints. They are public, and apply each node’s visibility rules.', category: 'free-nav')),
        ]);
    }

    /**
     * Per-menu permissions stand on their own. Craft drops a nested permission whose parent isn't
     * granted, so until 5.1.5, when they were nested under the all-menus ones, granting "edit nodes
     * in Main" was impossible without granting "edit nodes" everywhere. The names are unchanged, so
     * existing grants still apply.
     */
    #[Override]
    protected function getPermissions(): array
    {
        $permissions = [
            new Permission(
                key: 'freeNav-manageMenus',
                label: t('Create, edit and delete all menus', category: 'free-nav'),
            ),
            new Permission(
                key: 'freeNav-editNodes',
                label: t('Edit nodes in all menus', category: 'free-nav'),
                nested: collect([
                    new Permission(
                        key: 'freeNav-deleteNodes',
                        label: t('Delete nodes in all menus', category: 'free-nav'),
                    ),
                ]),
            ),
        ];

        foreach ($this->getMenus()->getAllMenus() as $menu) {
            $permissions[] = new Permission(
                key: 'freeNav-manageMenu:'.$menu->uid,
                label: t('Manage “{name}”', ['name' => $menu->name], category: 'free-nav'),
            );
            $permissions[] = new Permission(
                key: 'freeNav-editNodes:'.$menu->uid,
                label: t('Edit nodes in “{name}”', ['name' => $menu->name], category: 'free-nav'),
                nested: collect([
                    new Permission(
                        key: 'freeNav-deleteNodes:'.$menu->uid,
                        label: t('Delete nodes in “{name}”', ['name' => $menu->name], category: 'free-nav'),
                    ),
                ]),
            );
        }

        return $permissions;
    }

    /**
     * Node layouts can use FreeNav's node settings; the URL and the title are always in them.
     */
    #[Override]
    protected function getNativeFields(): ?\Closure
    {
        return function (FieldLayout $fieldLayout, array $fields): array {
            if ($fieldLayout->type === Node::class) {
                array_push($fields,
                    TitleField::class,
                    CustomUrlField::class,
                    NewWindowField::class,
                    CssClassesField::class,
                    UrlSuffixField::class,
                    IconField::class,
                    BadgeField::class,
                    CustomAttributesField::class,
                    VisibilityRulesField::class,
                );
            }

            return $fields;
        };
    }

    /**
     * Menus are stored under a top-level `freeNav` key Craft knows nothing about — it only clears
     * `plugins.free-nav` — so it's removed here, or reinstalling would find menus whose tables are
     * gone. Events are muted: the tables the handlers write to were dropped a moment ago.
     */
    #[Override]
    protected function afterUninstall(): void
    {
        $projectConfig = $this->app->make(\CraftCms\Cms\ProjectConfig\ProjectConfig::class);
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        try {
            $projectConfig->remove('freeNav', 'Remove FreeNav’s menus');
        } finally {
            $projectConfig->muteEvents = $muteEvents;
        }
    }

    // -------------------------------------------------------------------------

    private function registerProjectConfigHandlers(): void
    {
        $menus = $this->getMenus();

        ProjectConfig::onAdd(Menus::CONFIG_PATH.'.{uid}', $menus->handleChangedMenu(...))
            ->onUpdate(Menus::CONFIG_PATH.'.{uid}', $menus->handleChangedMenu(...))
            ->onRemove(Menus::CONFIG_PATH.'.{uid}', $menus->handleDeletedMenu(...));
    }

    /**
     * Linked nodes follow their element: its title when they haven't been given their own, and a
     * deleted element turns them into custom URL nodes.
     */
    private function registerElementEvents(): void
    {
        Event::listen(ElementSaving::class, function (ElementSaving $event): void {
            if (! $event->element instanceof Node) {
                $this->getNodes()->rememberTitleBeforeSave($event->element);
            }
        });

        Event::listen(ElementSaved::class, function (ElementSaved $event): void {
            if (! $event->element instanceof Node) {
                $this->getNodes()->syncNodeFromElement($event->element);
            }
        });

        Event::listen(ElementDeleted::class, function (ElementDeleted $event): void {
            if (! $event->element instanceof Node) {
                $this->getNodes()->handleDeletedElement($event->element);
            }
        });
    }

    /**
     * `craft.freenav` in Twig, as in FreeNav 5, and a `freenav` global that Blade templates can use
     * too.
     */
    private function registerTemplateVariable(): void
    {
        CraftVariable::macro('freenav', fn () => app(FreeNavVariable::class));

        Event::listen(TemplateGlobalsResolving::class, function (TemplateGlobalsResolving $event): void {
            $event->globals['freenav'] ??= app(FreeNavVariable::class);
        });
    }

    /**
     * One schema scope per menu, so a GraphQL token can be limited to the menus it may read.
     */
    private function registerGql(): void
    {
        Event::listen(GqlSchemaComponentsResolving::class, function (GqlSchemaComponentsResolving $event): void {
            $queries = [];

            foreach ($this->getMenus()->getAllMenus() as $menu) {
                $queries[GqlHelper::COMPONENT.".{$menu->uid}:read"] = [
                    'label' => t('View “{name}” menu nodes', ['name' => $menu->name], category: 'free-nav'),
                ];
            }

            if ($queries !== []) {
                $event->queries['FreeNav'] = $queries;
            }
        });
    }
}
