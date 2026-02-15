<?php

namespace justinholt\freenav\controllers;

use Craft;
use craft\web\Controller;
use justinholt\freenav\elements\Node;
use justinholt\freenav\FreeNav;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ApiController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function beforeAction($action): bool
    {
        if (!FreeNav::getInstance()->getSettings()->restApiEnabled) {
            throw new NotFoundHttpException('REST API is disabled.');
        }

        return parent::beforeAction($action);
    }

    public function actionGetMenus(): Response
    {
        $menus = FreeNav::getInstance()->getMenus()->getAllMenus();

        $data = [];
        foreach ($menus as $menu) {
            $data[] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'handle' => $menu->handle,
                'maxLevels' => $menu->maxLevels,
                'maxNodes' => $menu->maxNodes,
            ];
        }

        return $this->asJson(['menus' => $data]);
    }

    public function actionGetMenu(): Response
    {
        $handle = Craft::$app->getRequest()->getRequiredQueryParam('handle');

        $menu = FreeNav::getInstance()->getMenus()->getMenuByHandle($handle);

        if (!$menu) {
            throw new NotFoundHttpException('Menu not found');
        }

        $nodes = Node::find()
            ->menuHandle($handle)
            ->status('enabled')
            ->all();

        $nodeData = [];
        foreach ($nodes as $node) {
            $nodeData[] = $this->_serializeNode($node);
        }

        return $this->asJson([
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'handle' => $menu->handle,
                'maxLevels' => $menu->maxLevels,
            ],
            'nodes' => $nodeData,
        ]);
    }

    public function actionGetBreadcrumbs(): Response
    {
        $uri = Craft::$app->getRequest()->getQueryParam('uri', '');

        $breadcrumbs = FreeNav::getInstance()->getBreadcrumbs()->generate([
            'uri' => $uri,
        ]);

        return $this->asJson(['breadcrumbs' => $breadcrumbs]);
    }

    private function _serializeNode(Node $node): array
    {
        return [
            'id' => $node->id,
            'title' => $node->title,
            'url' => $node->getUrl(),
            'nodeType' => $node->nodeType,
            'level' => $node->level,
            'classes' => $node->classes,
            'urlSuffix' => $node->urlSuffix,
            'customAttributes' => $node->getCustomAttributesArray(),
            'newWindow' => $node->newWindow,
            'icon' => $node->icon,
            'badge' => $node->badge,
            'active' => $node->isActive(),
            'enabled' => $node->enabled,
            'children' => array_map(
                fn($child) => $this->_serializeNode($child),
                $node->getChildren()->all()
            ),
        ];
    }
}
