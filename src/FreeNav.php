<?php

namespace justinholt\freenav;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\Asset;
use craft\events\ConfigEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Gc;
use craft\services\Gql;
use craft\services\ProjectConfig;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use justinholt\freenav\elements\Node;
use justinholt\freenav\fields\MenuField;
use justinholt\freenav\gql\interfaces\NodeInterface;
use justinholt\freenav\gql\queries\NodeQuery as GqlNodeQuery;
use justinholt\freenav\models\Settings;
use justinholt\freenav\services\Breadcrumbs;
use justinholt\freenav\services\MenuCache;
use justinholt\freenav\services\Menus;
use justinholt\freenav\services\Nodes;
use justinholt\freenav\services\NodeTypes;
use justinholt\freenav\services\Renderer;
use justinholt\freenav\variables\FreeNavVariable;
use yii\base\Event;

/**
 * @property-read Menus $menus
 * @property-read Nodes $nodes
 * @property-read NodeTypes $nodeTypes
 * @property-read Renderer $renderer
 * @property-read Breadcrumbs $breadcrumbs
 * @property-read MenuCache $menuCache
 * @method Settings getSettings()
 */
class FreeNav extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'menus' => Menus::class,
                'nodes' => Nodes::class,
                'nodeTypes' => NodeTypes::class,
                'renderer' => Renderer::class,
                'breadcrumbs' => Breadcrumbs::class,
                'menuCache' => MenuCache::class,
            ],
        ];
    }

    public function getMenus(): Menus
    {
        return $this->get('menus');
    }

    public function getNodes(): Nodes
    {
        return $this->get('nodes');
    }

    public function getNodeTypes(): NodeTypes
    {
        return $this->get('nodeTypes');
    }

    public function getRenderer(): Renderer
    {
        return $this->get('renderer');
    }

    public function getBreadcrumbs(): Breadcrumbs
    {
        return $this->get('breadcrumbs');
    }

    public function getMenuCache(): MenuCache
    {
        return $this->get('menuCache');
    }

    public function init(): void
    {
        parent::init();

        $this->_registerElementTypes();
        $this->_registerFieldTypes();
        $this->_registerVariable();
        $this->_registerProjectConfigListeners();
        $this->_registerElementEventListeners();
        $this->_registerGarbageCollection();

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerMap['migrate'] = \justinholt\freenav\console\controllers\MigrateController::class;
        }

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpUrlRules();
        }

        if (Craft::$app->getEdition() === Craft::Pro) {
            $this->_registerPermissions();
            $this->_registerGraphQl();
        }
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if ($item === null) {
            return null;
        }

        $item['label'] = 'FreeNav';
        $item['subnav'] = [
            'menus' => [
                'label' => Craft::t('free-nav', 'Menus'),
                'url' => 'free-nav/menus',
            ],
        ];

        if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $item['subnav']['settings'] = [
                'label' => Craft::t('free-nav', 'Settings'),
                'url' => 'free-nav/settings',
            ];
        }

        return $item;
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('free-nav/settings'));
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    private function _registerElementTypes(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Node::class;
            }
        );
    }

    private function _registerFieldTypes(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = MenuField::class;
            }
        );
    }

    private function _registerVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $event->sender->set('freenav', FreeNavVariable::class);
            }
        );
    }

    private function _registerCpUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['free-nav'] = 'free-nav/menus/index';
                $event->rules['free-nav/menus'] = 'free-nav/menus/index';
                $event->rules['free-nav/menus/new'] = 'free-nav/menus/edit';
                $event->rules['free-nav/menus/<menuId:\d+>'] = 'free-nav/menus/edit';
                $event->rules['free-nav/menus/<menuId:\d+>/build'] = 'free-nav/menus/build';
                $event->rules['free-nav/settings'] = 'free-nav/menus/settings';
            }
        );
    }

    private function _registerProjectConfigListeners(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $projectConfig->onAdd('freeNav.menus.{uid}', [$this->getMenus(), 'handleChangedMenu']);
        $projectConfig->onUpdate('freeNav.menus.{uid}', [$this->getMenus(), 'handleChangedMenu']);
        $projectConfig->onRemove('freeNav.menus.{uid}', [$this->getMenus(), 'handleDeletedMenu']);
    }

    private function _registerElementEventListeners(): void
    {
        // Sync node titles/URLs when linked elements change
        Event::on(
            Element::class,
            Element::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                /** @var Element $element */
                $element = $event->sender;
                if (!$element instanceof Node) {
                    $this->getNodes()->syncNodeFromElement($element);
                }
            }
        );

        // Handle deleted elements
        Event::on(
            Element::class,
            Element::EVENT_AFTER_DELETE,
            function (Event $event) {
                /** @var Element $element */
                $element = $event->sender;
                if (!$element instanceof Node) {
                    $this->getNodes()->handleDeletedElement($element);
                }
            }
        );
    }

    private function _registerGarbageCollection(): void
    {
        Event::on(
            Gc::class,
            Gc::EVENT_RUN,
            function () {
                Craft::$app->getGc()->deletePartialElements(
                    Node::class,
                    '{{%freenav_nodes}}',
                    'id',
                );
            }
        );
    }

    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $menus = $this->getMenus()->getAllMenus();

                $menuPermissions = [];
                foreach ($menus as $menu) {
                    $menuPermissions['freeNav-manageMenu:' . $menu->uid] = [
                        'label' => Craft::t('free-nav', 'Manage "{name}"', ['name' => $menu->name]),
                    ];
                }

                $editNodePermissions = [];
                foreach ($menus as $menu) {
                    $editNodePermissions['freeNav-editNodes:' . $menu->uid] = [
                        'label' => Craft::t('free-nav', '"{name}"', ['name' => $menu->name]),
                    ];
                }

                $deleteNodePermissions = [];
                foreach ($menus as $menu) {
                    $deleteNodePermissions['freeNav-deleteNodes:' . $menu->uid] = [
                        'label' => Craft::t('free-nav', '"{name}"', ['name' => $menu->name]),
                    ];
                }

                $event->permissions[] = [
                    'heading' => Craft::t('free-nav', 'FreeNav'),
                    'permissions' => [
                        'freeNav-manageMenus' => [
                            'label' => Craft::t('free-nav', 'Manage menus'),
                            'nested' => $menuPermissions,
                        ],
                        'freeNav-editNodes' => [
                            'label' => Craft::t('free-nav', 'Edit nodes'),
                            'nested' => $editNodePermissions,
                        ],
                        'freeNav-deleteNodes' => [
                            'label' => Craft::t('free-nav', 'Delete nodes'),
                            'nested' => $deleteNodePermissions,
                        ],
                    ],
                ];
            }
        );
    }

    private function _registerGraphQl(): void
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_TYPES,
            function (RegisterGqlTypesEvent $event) {
                $event->types[] = NodeInterface::class;
            }
        );

        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_QUERIES,
            function (RegisterGqlQueriesEvent $event) {
                $event->queries = array_merge(
                    $event->queries,
                    GqlNodeQuery::getQueries()
                );
            }
        );

        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS,
            function (RegisterGqlSchemaComponentsEvent $event) {
                $menus = $this->getMenus()->getAllMenus();

                $queries = [];
                foreach ($menus as $menu) {
                    $queries["freeNavMenus.{$menu->uid}:read"] = [
                        'label' => Craft::t('free-nav', 'View "{name}" menu nodes', ['name' => $menu->name]),
                    ];
                }

                $event->queries['FreeNav'] = $queries;
            }
        );
    }
}
