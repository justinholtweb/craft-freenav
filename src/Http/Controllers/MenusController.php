<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Http\Controllers;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\FieldLayoutDesigner;
use CraftCms\Cms\Ui\Controls\Hidden;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Callout;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Enums\Propagation;
use JustinHoltWeb\FreeNav\Models\Menu;
use JustinHoltWeb\FreeNav\Models\MenuSiteSettings;
use JustinHoltWeb\FreeNav\Services\Menus;
use JustinHoltWeb\FreeNav\Services\Nodes;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * Menu definitions: the list, the settings screen (with the menu's node field layout), reorder,
 * duplicate and delete.
 *
 * Every route here is behind `accessPlugin-free-nav` (routes/cp.php); each action checks the
 * menu permission it needs through `Menus`. Writes to menu definitions are project config, so
 * they also need `allowAdminChanges`.
 */
class MenusController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly Menus $menus,
        private readonly Nodes $nodes,
    ) {}

    public function index(): CpScreenResponse
    {
        $user = $this->user();
        $canCreate = $this->menus->canManageMenus($user) && $this->menus->allowAdminChanges();
        $rows = [];

        foreach ($this->menus->getEditableMenus($user) as $menu) {
            $manageable = $this->menus->canManageMenu($user, $menu);
            $treeUrl = cp_url("free-nav/menus/{$menu->id}/build");
            $count = $this->nodes->getNodeCount((int) $menu->id, $this->siteIdFor($menu));

            $rows[] = [
                'id' => $menu->id,
                'name' => ['label' => (string) $menu->name, 'url' => $treeUrl],
                'handle' => ['html' => Html::tag('code', Html::encode((string) $menu->handle))],
                'nodes' => ['label' => t('{count} nodes', ['count' => $count], category: 'free-nav'), 'url' => $treeUrl],
                'settings' => $manageable ? ['label' => t('Settings', category: 'free-nav'), 'url' => cp_url("free-nav/menus/{$menu->id}")] : '',
                'export' => $manageable ? ['label' => t('Export JSON', category: 'free-nav'), 'url' => cp_url("free-nav/menus/{$menu->id}/export")] : '',
                '_deletable' => $canCreate,
                '_deleteUrl' => cp_url("free-nav/menus/{$menu->id}"),
                '_deleteConfirmMessage' => t('Delete “{name}” and all of its nodes?', ['name' => $menu->name], category: 'free-nav'),
            ];
        }

        $table = Table::make('freenav-menus')
            ->columns([
                ['key' => 'name', 'label' => t('Name', category: 'free-nav'), 'width' => '2fr'],
                ['key' => 'handle', 'label' => t('Handle', category: 'free-nav')],
                ['key' => 'nodes', 'label' => t('Nodes', category: 'free-nav')],
                ['key' => 'settings', 'label' => t('Settings', category: 'free-nav')],
                ['key' => 'export', 'label' => t('Export', category: 'free-nav')],
            ])
            ->rows($rows)
            ->emptyMessage(t('No menus exist yet.', category: 'free-nav'));

        if ($canCreate) {
            $table
                ->createAction(t('New menu', category: 'free-nav'), cp_url('free-nav/menus/new'))
                ->createActionInPageHeader()
                ->reorderable(cp_url('free-nav/menus/reorder'), t('New order saved.', category: 'free-nav'))
                ->deletable()
                ->bulkActions([
                    ['label' => t('Duplicate', category: 'free-nav'), 'url' => cp_url('free-nav/menus/duplicate'), 'allowMultiple' => false],
                ]);
        }

        return new CpScreenResponse()
            ->title(t('Menus', category: 'free-nav'))
            ->ui(Ui::make([$table]));
    }

    public function create(): CpScreenResponse
    {
        abort_unless($this->menus->canManageMenus($this->user()), 403, 'User not authorized to manage menus.');

        $menu = new Menu;
        $menu->setSiteSettings($this->menus->allSitesSettings());

        return $this->screen($menu);
    }

    public function edit(int $menuId): CpScreenResponse
    {
        $menu = $this->menu($menuId);
        abort_unless($this->menus->canManageMenu($this->user(), $menu), 403, 'User not authorized to manage this menu.');

        return $this->screen($menu);
    }

    public function save(Request $request): Response|CpScreenResponse
    {
        $user = $this->user();
        $menuId = $request->integer('menuId');

        if ($menuId) {
            $menu = $this->menu($menuId);
            abort_unless($this->menus->canManageMenu($user, $menu), 403, 'User not authorized to manage this menu.');
        } else {
            abort_unless($this->menus->canManageMenus($user), 403, 'User not authorized to manage menus.');
            $menu = new Menu;
        }

        $this->requireAdminChanges();

        $menu->name = $request->string('name')->trim()->toString();
        $menu->handle = $request->string('handle')->trim()->toString();
        $menu->instructions = $request->input('instructions') ?: null;
        $menu->propagationMethod = (string) $request->input('propagationMethod', $menu->propagationMethod);
        $menu->maxNodes = $request->filled('maxNodes') ? $request->integer('maxNodes') : null;
        $menu->maxLevels = $request->filled('maxLevels') ? $request->integer('maxLevels') : null;
        $menu->defaultPlacement = (string) $request->input('defaultPlacement', $menu->defaultPlacement);

        $sites = (array) $request->input('sites', []);
        $siteSettings = [];

        foreach (Sites::getAllSites() as $site) {
            $siteSettings[(int) $site->id] = new MenuSiteSettings([
                'siteId' => (int) $site->id,
                'enabled' => filter_var($sites[$site->id]['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        $menu->setSiteSettings($siteSettings);

        if ($request->has('fieldLayout')) {
            $fieldLayout = Fields::assembleLayoutFromPost();
            $fieldLayout->type = Node::class;
            $fieldLayout->id = $menu->fieldLayoutId;
            $menu->setFieldLayout($fieldLayout);
        }

        if (! $this->menus->saveMenu($menu)) {
            // Inertia keeps the form as it was and shows these against their fields.
            throw ValidationException::withMessages($menu->errors()->getMessages());
        }

        return $this->asSuccess(t('Menu saved.', category: 'free-nav'), redirect: cp_url('free-nav/menus'));
    }

    public function destroy(int $menuId): Response
    {
        abort_unless($this->menus->canManageMenus($this->user()), 403, 'User not authorized to manage menus.');
        $this->requireAdminChanges();

        $this->menus->deleteMenu($this->menu($menuId));

        return $this->asSuccess(t('Menu deleted.', category: 'free-nav'), redirect: cp_url('free-nav/menus'));
    }

    /**
     * The menus table's drag-and-drop order. Posts `{ids: [...]}`.
     */
    public function reorder(Request $request): JsonResponse
    {
        abort_unless($this->menus->canManageMenus($this->user()), 403, 'User not authorized to manage menus.');
        $this->requireAdminChanges();

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', [])), fn (int $id) => $id > 0));
        $this->menus->reorderMenus($ids);

        return response()->json(['success' => true]);
    }

    /**
     * Duplicates a menu: from the menus table's actions (`{ids: [id]}`) or a menu's settings
     * screen (`{menuId}`).
     */
    public function duplicate(Request $request): Response
    {
        abort_unless($this->menus->canManageMenus($this->user()), 403, 'User not authorized to manage menus.');
        $this->requireAdminChanges();

        $menuId = $request->integer('menuId') ?: (int) (((array) $request->input('ids', []))[0] ?? 0);
        $newMenu = $this->menus->duplicateMenu($this->menu($menuId));

        if (! $newMenu) {
            return $this->asFailure(t('Couldn’t duplicate the menu.', category: 'free-nav'));
        }

        return $this->asSuccess(t('Menu duplicated.', category: 'free-nav'), redirect: cp_url('free-nav/menus/'.$newMenu->id));
    }

    // -------------------------------------------------------------------------

    private function screen(Menu $menu): CpScreenResponse
    {
        $isNew = ! $menu->id;
        $editable = $this->menus->allowAdminChanges();
        $title = $isNew ? t('Create a new menu', category: 'free-nav') : (string) $menu->name;

        $siteValues = [];

        foreach (Sites::getAllSites() as $site) {
            $settings = $menu->getSiteSettings()[(int) $site->id] ?? null;
            $siteValues[(string) $site->id] = ['enabled' => $settings ? $settings->enabled : $isNew];
        }

        $fieldLayout = $menu->getFieldLayout();

        $values = [
            'menuId' => $menu->id,
            'name' => $menu->name,
            'handle' => $menu->handle,
            'instructions' => $menu->instructions,
            'propagationMethod' => $menu->propagationMethod,
            'maxLevels' => $menu->maxLevels,
            'maxNodes' => $menu->maxNodes,
            'defaultPlacement' => $menu->defaultPlacement,
            'sites' => $siteValues,
            'fieldLayout' => [
                'id' => $fieldLayout->id,
                'uid' => $fieldLayout->uid,
                ...($fieldLayout->getConfig() ?? []),
            ],
        ];

        $propagationOptions = array_map(
            fn (Propagation $case) => ['label' => $case->label(), 'value' => $case->value],
            Propagation::cases(),
        );

        $siteNodes = [];

        foreach (Sites::getAllSites() as $site) {
            $siteNodes[] = Field::make((string) $site->name, Lightswitch::make(['sites', (string) $site->id, 'enabled']));
        }

        $ui = Ui::make([
            ...($editable ? [] : [Callout::make('freenav-readonly', t('Menus can only be changed where admin changes are allowed.', category: 'free-nav'))->variant('warning')]),
            Field::make(null, Hidden::make('menuId')),
            Field::make(t('Name', category: 'free-nav'), Text::make('name'))
                ->instructions(t('What this menu will be called in the control panel.', category: 'free-nav'))
                ->required(),
            Field::make(t('Handle', category: 'free-nav'), Text::make('handle'))
                ->instructions(t('How you’ll refer to this menu in templates.', category: 'free-nav'))
                ->required(),
            Field::make(t('Instructions', category: 'free-nav'), Textarea::make('instructions')->rows(2))
                ->instructions(t('Helper text to guide the author.', category: 'free-nav')),
            Heading::make('freenav-structure', t('Structure', category: 'free-nav')),
            Field::make(t('Propagation Method', category: 'free-nav'), Choice::make('propagationMethod')->options($propagationOptions))
                ->instructions(t('Which sites should nodes be saved to?', category: 'free-nav')),
            Field::make(t('Max Levels', category: 'free-nav'), Number::make('maxLevels')->min(1))
                ->instructions(t('The maximum number of levels this menu can have.', category: 'free-nav')),
            Field::make(t('Max Nodes', category: 'free-nav'), Number::make('maxNodes')->min(1))
                ->instructions(t('The maximum number of nodes allowed in this menu.', category: 'free-nav')),
            Field::make(t('Default Placement', category: 'free-nav'), Choice::make('defaultPlacement')->options([
                ['label' => t('Beginning', category: 'free-nav'), 'value' => 'beginning'],
                ['label' => t('End', category: 'free-nav'), 'value' => 'end'],
            ]))->instructions(t('Where should new nodes be placed by default?', category: 'free-nav')),
            Heading::make('freenav-sites', t('Sites', category: 'free-nav')),
            ...$siteNodes,
            Heading::make('freenav-layout', t('Node Fields', category: 'free-nav'))
                ->description(t('The fields editors see when they edit a node in this menu.', category: 'free-nav')),
            Field::make(null, FieldLayoutDesigner::make('fieldLayout')->elementType(Node::class)),
        ]);

        $context = new UiContext(
            values: $values,
            errors: $menu->errors()->getMessages(),
            mode: $editable ? ControlMode::Editable : ControlMode::ReadOnly,
        );

        $response = new CpScreenResponse()
            ->title($title)
            ->crumbs([
                new ActionItem()->label(t('Menus', category: 'free-nav'))->href(cp_url('free-nav/menus')),
                new ActionItem()->label($title),
            ])
            ->redirectUrl(cp_url('free-nav/menus'))
            ->ui($ui, $context, $editable ? ['submit' => ['method' => 'post', 'url' => cp_url('free-nav/menus')]] : []);

        if (! $isNew) {
            $response->addAltAction(t('Edit nodes', category: 'free-nav'), [
                'href' => cp_url("free-nav/menus/{$menu->id}/build"),
            ]);
            $response->addAltAction(t('Export JSON', category: 'free-nav'), [
                'href' => cp_url("free-nav/menus/{$menu->id}/export"),
            ]);

            if ($editable && $this->menus->canManageMenus($this->user())) {
                $response->addAltAction(t('Duplicate', category: 'free-nav'), [
                    'action' => [
                        'type' => 'http',
                        'method' => 'POST',
                        'url' => cp_url('free-nav/menus/duplicate'),
                        'body' => ['menuId' => $menu->id],
                    ],
                ]);
                $response->addAltAction(t('Delete', category: 'free-nav'), [
                    'variant' => 'danger',
                    'action' => [
                        'type' => 'http',
                        'method' => 'DELETE',
                        'url' => cp_url("free-nav/menus/{$menu->id}"),
                        'confirm' => t('Delete “{name}” and all of its nodes?', ['name' => $menu->name], category: 'free-nav'),
                    ],
                ]);
            }
        }

        return $response;
    }

    private function menu(int $menuId): Menu
    {
        return $this->menus->getMenuById($menuId) ?? abort(404, 'Menu not found');
    }

    private function user(): User
    {
        return currentUserElement() ?? abort(403);
    }

    /**
     * Nodes belong to a site, so a count has to name one: the current site when the menu is
     * enabled for it, otherwise the menu's first site — the one its tree opens on.
     */
    private function siteIdFor(Menu $menu): int
    {
        return $this->nodes->getDefaultSiteId($menu);
    }

    private function requireAdminChanges(): void
    {
        abort_unless($this->menus->allowAdminChanges(), 403, 'Menus can only be changed where admin changes are allowed.');
    }
}
