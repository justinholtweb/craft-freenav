<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Http\Controllers;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Element\Exceptions\UnsupportedSiteException;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\ElementSelect;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Enums\NodeType;
use JustinHoltWeb\FreeNav\Http\ViewModels\MenuTreeViewModel;
use JustinHoltWeb\FreeNav\Models\Menu;
use JustinHoltWeb\FreeNav\Services\Menus;
use JustinHoltWeb\FreeNav\Services\Nodes;
use JustinHoltWeb\FreeNav\Services\NodeTypes;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * A menu's nodes.
 *
 * The tree is Craft's own element index in structure view — drag-and-drop, nesting, status,
 * search and delete all come from core, gated by the source's `structureEditable` and the node's
 * `canSave()`/`canDelete()`. Nodes are edited in Craft's element editor, with the menu's field
 * layout. What's left here is the tree route and the add-node form, which decides the things the
 * editor can't change: the menu, the node type and the linked element.
 */
class NodesController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly Menus $menus,
        private readonly Nodes $nodes,
        private readonly NodeTypes $nodeTypes,
    ) {}

    public function tree(ElementIndexRequest $request, int $menuId): InertiaResponse|Response
    {
        $menu = $this->menu($menuId);
        $user = $this->user();
        $canEdit = $this->menus->canEditNodes($user, $menu);

        abort_unless($canEdit || $this->menus->canManageMenu($user, $menu), 403, 'User not authorized to build this menu.');

        // Nodes can only be saved to sites the menu is enabled for, so don't open the tree on any
        // other site.
        $siteHandle = $request->input('site');

        if (is_string($siteHandle) && ($site = Sites::getSiteByHandle($siteHandle)) && ! $menu->isEnabledForSite((int) $site->id)) {
            $fallback = Sites::getSiteById($this->nodes->getDefaultSiteId($menu));

            if ($fallback && $fallback->id !== $site->id) {
                return redirect(cp_url("free-nav/menus/{$menu->id}/build", ['site' => $fallback->handle]));
            }
        }

        $atLimit = $menu->maxNodes && $this->nodes->findNodesInMenu((int) $menu->id)->count() >= $menu->maxNodes;

        return Inertia::render('elements/Index', [new MenuTreeViewModel($request, $menu)])
            ->with([
                'indexUrl' => $request->url(),
                'newElement' => $canEdit && ! $atLimit ? [
                    'label' => t('Add node', category: 'free-nav'),
                    'href' => cp_url("free-nav/menus/{$menu->id}/nodes/new"),
                ] : null,
            ]);
    }

    public function create(Request $request, int $menuId): CpScreenResponse
    {
        $menu = $this->menu($menuId);
        $this->requireEditNodes($menu);

        return $this->screen($menu, [
            'nodeType' => NodeType::Entry->value,
            'siteId' => (string) $this->nodes->getDefaultSiteId($menu),
            'newWindow' => false,
            'enabled' => true,
        ]);
    }

    /**
     * Rebuilds the form when the node type changes, so only the inputs that type uses show.
     */
    public function refresh(Request $request, int $menuId): JsonResponse
    {
        $menu = $this->menu($menuId);
        $this->requireEditNodes($menu);

        $values = (array) $request->input('values', []);

        return response()->json([
            'ui' => app(UiResolver::class)->resolve($this->formUi($menu, $values), new UiContext(values: $values, refreshable: true)),
        ]);
    }

    /**
     * Adds a node. Answers JSON when asked (`Accept: application/json`), otherwise returns to the
     * tree.
     */
    public function store(Request $request, int $menuId): Response
    {
        $menu = $this->menu($menuId);
        $this->requireEditNodes($menu);

        $values = $request->all();
        $type = NodeType::tryFrom((string) $request->input('nodeType', NodeType::Custom->value));

        if (! $type || ! in_array($type, $this->nodeTypes->getRegisteredTypes(), true)) {
            return $this->failure($request, $menu, $values, ['nodeType' => [t('Choose a node type.', category: 'free-nav')]]);
        }

        if ($menu->maxNodes && $this->nodes->findNodesInMenu((int) $menu->id)->count() >= $menu->maxNodes) {
            return $this->failure($request, $menu, $values, [], t('Maximum number of nodes ({max}) reached.', ['max' => $menu->maxNodes], category: 'free-nav'));
        }

        $linkedElementId = null;

        if ($type->isElement()) {
            $linkedElementId = self::firstId($request->input('linkedElementIds.'.$type->value) ?? $request->input('linkedElementId'));

            if (! $linkedElementId) {
                return $this->failure($request, $menu, $values, ['linkedElementIds.'.$type->value => [t('Choose what this node links to.', category: 'free-nav')]]);
            }
        }

        $parentId = $request->integer('parentId') ?: null;
        $parent = $parentId ? $this->nodes->findNodeInMenu($menu, $parentId) : null;

        if ($parentId && ! $parent) {
            return $this->failure($request, $menu, $values, ['parentId' => [t('That parent isn’t in this menu.', category: 'free-nav')]]);
        }

        $node = $this->nodes->makeNode($menu, [
            'title' => trim((string) $request->input('title', '')),
            'nodeType' => $type->value,
            'linkedElementId' => $linkedElementId,
            'customUrl' => $type->isElement() || $type === NodeType::Passive ? null : $request->input('customUrl'),
            'newWindow' => $request->boolean('newWindow'),
            'enabled' => $request->has('enabled') ? $request->boolean('enabled') : true,
            'siteId' => $request->integer('siteId') ?: null,
        ]);

        // Only an element of the node type's class that this user may view: posting an ID is
        // otherwise a way to link to, and copy the title of, a hidden entry.
        if ($linkedElementId && ! $this->nodes->linkableElement($type, $linkedElementId, (int) $node->siteId, currentUser())) {
            return $this->failure($request, $menu, $values, ['linkedElementIds.'.$type->value => [t('That element doesn’t exist on this site, or you can’t view it.', category: 'free-nav')]]);
        }

        try {
            $saved = Elements::saveElement($node);
        } catch (UnsupportedSiteException $e) { // @phpstan-ignore catch.neverThrown (thrown from deep inside the save)
            $site = Sites::getSiteById($e->siteId);

            return $this->failure($request, $menu, $values, [], t('“{menu}” isn’t enabled for the {site} site. Enable it in the menu’s settings, or switch sites.', [
                'menu' => $menu->name,
                'site' => $site->name ?? $e->siteId,
            ], category: 'free-nav'));
        }

        if (! $saved) {
            return $this->failure($request, $menu, $values, $node->errors()->getMessages());
        }

        $this->nodes->placeNode($menu, $node, $parent);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'node' => [
                    'id' => $node->id,
                    'title' => $node->title,
                    'url' => $node->getUrl(),
                    'nodeType' => $node->nodeType,
                    'enabled' => $node->enabled,
                    'cpEditUrl' => $node->getCpEditUrl(),
                ],
            ]);
        }

        return $this->asSuccess(t('Node added.', category: 'free-nav'), redirect: cp_url("free-nav/menus/{$menu->id}/build"));
    }

    // -------------------------------------------------------------------------

    /**
     * Validation problems go back to the form, against their fields; anything else is a flash.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, array<string>>  $errors
     */
    private function failure(Request $request, Menu $menu, array $values, array $errors, ?string $message = null): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message ?? t('Couldn’t add node.', category: 'free-nav'),
                'errors' => $errors,
            ], 400);
        }

        // Inertia keeps the form as it was and shows these against their fields.
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->asFailure($message ?? t('Couldn’t add node.', category: 'free-nav'));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function screen(Menu $menu, array $values): CpScreenResponse
    {
        $context = new UiContext(values: $values, refreshable: true);
        $title = t('Add a node to {menu}', ['menu' => $menu->name], category: 'free-nav');
        $treeUrl = cp_url("free-nav/menus/{$menu->id}/build");

        $ui = $this->formUi($menu, $values);

        return new CpScreenResponse()
            ->title($title)
            ->crumbs([
                new ActionItem()->label(t('Menus', category: 'free-nav'))->href(cp_url('free-nav/menus')),
                new ActionItem()->label((string) $menu->name)->href($treeUrl),
            ])
            ->redirectUrl($treeUrl)
            ->ui($ui, $context, [
                'submit' => ['method' => 'post', 'url' => cp_url("free-nav/menus/{$menu->id}/nodes")],
                'refreshUrl' => cp_url("free-nav/menus/{$menu->id}/nodes/ui"),
            ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function formUi(Menu $menu, array $values): Ui
    {
        $type = NodeType::tryFrom((string) ($values['nodeType'] ?? '')) ?? NodeType::Entry;
        $typeOptions = array_map(
            fn (array $option) => ['label' => $option['label'], 'value' => $option['value']],
            $this->nodeTypes->getTypeOptions(),
        );

        $nodes = [
            Field::make(t('Node Type', category: 'free-nav'), Choice::make('nodeType')->options($typeOptions)->reactive())
                ->required(),
            Field::make(t('Title', category: 'free-nav'), Text::make('title'))
                ->instructions($type->isElement()
                    ? t('Leave blank to use the linked element’s title.', category: 'free-nav')
                    : t('The text of the link.', category: 'free-nav')),
        ];

        foreach ($this->nodeTypes->getLinkableElementTypes() as $value => $linkable) {
            $nodes[] = Field::make($linkable['label'], ElementSelect::make(['linkedElementIds', $value])
                ->elementType($linkable['elementType'])
                ->single())
                ->required()
                ->visible($type->value === $value);
        }

        $nodes[] = Field::make(t('URL', category: 'free-nav'), Text::make('customUrl')->placeholder('https://'))
            ->instructions(t('A web address, a path, an anchor, or a mailto:, tel: or sms: link. Environment variables and aliases must resolve to an http(s) address.', category: 'free-nav'))
            ->visible(in_array($type, [NodeType::Custom, NodeType::Site], true));

        $nodes[] = Field::make(t('Parent', category: 'free-nav'), Choice::make('parentId')->options($this->parentOptions($menu)))
            ->instructions(t('You can also drag the node into place in the tree afterwards.', category: 'free-nav'));

        $siteIds = $menu->getEnabledSiteIds();

        if (count($siteIds) > 1) {
            $siteOptions = [];

            foreach ($siteIds as $siteId) {
                $site = Sites::getSiteById($siteId);

                if ($site) {
                    $siteOptions[] = ['label' => (string) $site->name, 'value' => (string) $site->id];
                }
            }

            $nodes[] = Field::make(t('Site', category: 'free-nav'), Choice::make('siteId')->options($siteOptions))
                ->instructions(t('The site the node is created on. The menu’s propagation method decides which other sites get it.', category: 'free-nav'));
        }

        $nodes[] = Field::make(t('Open in New Window', category: 'free-nav'), Lightswitch::make('newWindow'))
            ->visible($type->hasUrl());
        $nodes[] = Field::make(t('Enabled', category: 'free-nav'), Lightswitch::make('enabled'));

        return Ui::make($nodes);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function parentOptions(Menu $menu): array
    {
        $options = [['label' => '—', 'value' => '']];

        foreach ($this->nodes->findNodesInMenu((int) $menu->id)->all() as $node) {
            if ($menu->maxLevels && ($node->level ?? 1) >= $menu->maxLevels) {
                continue;
            }

            $options[] = [
                'label' => str_repeat('— ', max(0, ($node->level ?? 1) - 1)).$node->title,
                'value' => (string) $node->id,
            ];
        }

        return $options;
    }

    private static function firstId(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function requireEditNodes(Menu $menu): void
    {
        abort_unless($this->menus->canEditNodes($this->user(), $menu), 403, 'User not authorized to edit nodes in this menu.');
    }

    private function menu(int $menuId): Menu
    {
        return $this->menus->getMenuById($menuId) ?? abort(404, 'Menu not found');
    }

    private function user(): User
    {
        return currentUserElement() ?? abort(403);
    }
}
