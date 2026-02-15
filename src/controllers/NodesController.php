<?php

namespace justinholt\freenav\controllers;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;
use justinholt\freenav\elements\Node;
use justinholt\freenav\FreeNav;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class NodesController extends Controller
{
    public function actionAdd(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $menuId = $request->getRequiredBodyParam('menuId');

        $menu = FreeNav::getInstance()->getMenus()->getMenuById($menuId);

        if (!$menu) {
            throw new NotFoundHttpException('Menu not found');
        }

        // Check max nodes
        if ($menu->maxNodes) {
            $currentCount = Node::find()->menuId($menuId)->status(null)->count();
            if ($currentCount >= $menu->maxNodes) {
                return $this->asFailure(Craft::t('free-nav', 'Maximum number of nodes ({max}) reached.', [
                    'max' => $menu->maxNodes,
                ]));
            }
        }

        $nodeData = $request->getBodyParam('node', []);
        if (!is_array($nodeData)) {
            $nodeData = Json::decodeIfJson($nodeData) ?: [];
        }

        $nodes = FreeNav::getInstance()->getNodes()->addNodes($menu, [$nodeData]);

        if (empty($nodes)) {
            return $this->asFailure(Craft::t('free-nav', 'Couldn't add node.'));
        }

        $node = $nodes[0];

        return $this->asJson([
            'success' => true,
            'node' => [
                'id' => $node->id,
                'title' => $node->title,
                'url' => $node->getUrl(),
                'nodeType' => $node->nodeType,
                'enabled' => $node->enabled,
                'level' => $node->level,
            ],
        ]);
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $nodeId = $request->getRequiredBodyParam('nodeId');

        $node = Node::find()->id($nodeId)->status(null)->one();

        if (!$node) {
            throw new NotFoundHttpException('Node not found');
        }

        $node->title = $request->getBodyParam('title', $node->title);
        $node->url = $request->getBodyParam('url', $node->url);
        $node->classes = $request->getBodyParam('classes', $node->classes);
        $node->urlSuffix = $request->getBodyParam('urlSuffix', $node->urlSuffix);
        $node->newWindow = (bool)$request->getBodyParam('newWindow', $node->newWindow);
        $node->icon = $request->getBodyParam('icon', $node->icon);
        $node->badge = $request->getBodyParam('badge', $node->badge);

        $customAttributes = $request->getBodyParam('customAttributes');
        if ($customAttributes !== null) {
            $node->customAttributes = is_string($customAttributes) ? Json::decodeIfJson($customAttributes) : $customAttributes;
        }

        $visibilityRules = $request->getBodyParam('visibilityRules');
        if ($visibilityRules !== null) {
            $node->visibilityRules = is_string($visibilityRules) ? Json::decodeIfJson($visibilityRules) : $visibilityRules;
        }

        if (!Craft::$app->getElements()->saveElement($node)) {
            return $this->asFailure(Craft::t('free-nav', 'Couldn't save node.'));
        }

        return $this->asJson([
            'success' => true,
            'node' => [
                'id' => $node->id,
                'title' => $node->title,
                'url' => $node->getUrl(),
                'nodeType' => $node->nodeType,
            ],
        ]);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $nodeId = Craft::$app->getRequest()->getRequiredBodyParam('nodeId');

        $node = Node::find()->id($nodeId)->status(null)->one();

        if (!$node) {
            throw new NotFoundHttpException('Node not found');
        }

        Craft::$app->getElements()->deleteElement($node);

        return $this->asSuccess(Craft::t('free-nav', 'Node deleted.'));
    }

    public function actionGetParentOptions(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $menuId = Craft::$app->getRequest()->getRequiredBodyParam('menuId');
        $excludeNodeId = Craft::$app->getRequest()->getBodyParam('excludeNodeId');

        $menu = FreeNav::getInstance()->getMenus()->getMenuById($menuId);

        if (!$menu) {
            throw new NotFoundHttpException('Menu not found');
        }

        $exclude = null;
        if ($excludeNodeId) {
            $exclude = Node::find()->id($excludeNodeId)->status(null)->one();
        }

        $options = FreeNav::getInstance()->getNodes()->getParentOptions($menu, $exclude);

        return $this->asJson(['options' => $options]);
    }

    public function actionToggleVisibility(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $nodeId = Craft::$app->getRequest()->getRequiredBodyParam('nodeId');
        $enabled = (bool)Craft::$app->getRequest()->getRequiredBodyParam('enabled');

        $node = Node::find()->id($nodeId)->status(null)->one();

        if (!$node) {
            throw new NotFoundHttpException('Node not found');
        }

        $node->enabled = $enabled;
        Craft::$app->getElements()->saveElement($node, false);

        return $this->asSuccess();
    }

    public function actionMoveNode(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $nodeId = $request->getRequiredBodyParam('nodeId');
        $parentId = $request->getBodyParam('parentId');
        $prevId = $request->getBodyParam('prevId');

        $node = Node::find()->id($nodeId)->status(null)->one();

        if (!$node) {
            throw new NotFoundHttpException('Node not found');
        }

        $menu = $node->getMenu();
        $structureId = $menu->structureId;

        if ($parentId) {
            $parentNode = Craft::$app->getElements()->getElementById($parentId);
            if ($prevId) {
                $prevNode = Craft::$app->getElements()->getElementById($prevId);
                Craft::$app->getStructures()->moveAfter($structureId, $node, $prevNode);
            } else {
                Craft::$app->getStructures()->prepend($structureId, $node, $parentNode);
            }
        } else {
            if ($prevId) {
                $prevNode = Craft::$app->getElements()->getElementById($prevId);
                Craft::$app->getStructures()->moveAfter($structureId, $node, $prevNode);
            } else {
                Craft::$app->getStructures()->prependToRoot($structureId, $node);
            }
        }

        return $this->asSuccess();
    }
}
