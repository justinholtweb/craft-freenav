<?php

namespace justinholt\freenav\controllers;

use Craft;
use craft\errors\UnsupportedSiteException;
use craft\helpers\Cp;
use craft\helpers\Json;
use craft\web\Controller;
use justinholt\freenav\elements\Node;
use justinholt\freenav\FreeNav;
use yii\web\BadRequestHttpException;
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
            $currentCount = FreeNav::getInstance()->getNodes()->findNodesInMenu($menuId)->count();
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

        try {
            $nodes = FreeNav::getInstance()->getNodes()->addNodes($menu, [$nodeData]);
        } catch (UnsupportedSiteException $e) {
            $site = Craft::$app->getSites()->getSiteById($e->siteId);

            return $this->asFailure(Craft::t('free-nav', '“{menu}” isn\'t enabled for the {site} site. Enable it in the menu\'s settings, or switch sites.', [
                'menu' => $menu->name,
                'site' => $site->name ?? $e->siteId,
            ]));
        }

        if (empty($nodes)) {
            return $this->asFailure(Craft::t('free-nav', 'Couldn\'t add node.'));
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

    /**
     * Renders the element-selection input for a linkable node type so the
     * builder UI can inject a working entry/category/asset/product picker.
     */
    public function actionElementSelectHtml(): Response
    {
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $nodeType = (string)$request->getRequiredParam('nodeType');
        $siteId = $request->getParam('siteId');
        $selectedId = $request->getParam('selectedId');

        $linkable = FreeNav::getInstance()->getNodeTypes()->getLinkableElementTypes();
        if (!isset($linkable[$nodeType])) {
            throw new BadRequestHttpException("“{$nodeType}” is not a linkable node type.");
        }

        /** @var class-string<\craft\base\ElementInterface> $elementType */
        $elementType = $linkable[$nodeType]['elementType'];

        $criteria = [];
        if ($siteId) {
            $criteria['siteId'] = (int)$siteId;
        }

        $elements = [];
        if ($selectedId) {
            $element = Craft::$app->getElements()->getElementById(
                (int)$selectedId,
                $elementType,
                $siteId ? (int)$siteId : null,
            );
            if ($element) {
                $elements[] = $element;
            }
        }

        $view = $this->getView();

        $html = Cp::elementSelectHtml([
            'id' => 'freenav-linked-element',
            'name' => 'linkedElement',
            'elementType' => $elementType,
            'single' => true,
            'criteria' => $criteria,
            'elements' => $elements,
            'selectionLabel' => Craft::t('free-nav', 'Choose an element'),
        ]);

        return $this->asJson([
            'html' => $html,
            'headHtml' => $view->getHeadHtml(),
            'bodyHtml' => $view->getBodyHtml(),
        ]);
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $nodeId = $request->getRequiredBodyParam('nodeId');

        $node = $this->_getNode($nodeId);

        if (!$node) {
            throw new NotFoundHttpException('Node not found');
        }

        $node->title = $request->getBodyParam('title', $node->title);
        $node->customUrl = $request->getBodyParam('customUrl', $node->customUrl);
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
            return $this->asFailure(Craft::t('free-nav', 'Couldn\'t save node.'));
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

        $node = $this->_getNode($nodeId);

        if (!$node) {
            throw new NotFoundHttpException('Node not found');
        }

        Craft::$app->getElements()->deleteElement($node);

        return $this->asSuccess(Craft::t('free-nav', 'Node deleted.'));
    }

    public function actionGetNode(): Response
    {
        $this->requireAcceptsJson();

        $nodeId = Craft::$app->getRequest()->getRequiredParam('nodeId');

        $node = $this->_getNode($nodeId);

        if (!$node) {
            throw new NotFoundHttpException('Node not found');
        }

        return $this->asJson([
            'id' => $node->id,
            'title' => $node->title,
            'nodeType' => $node->nodeType,
            'customUrl' => $node->customUrl,
            'url' => $node->getUrl(),
            'classes' => $node->classes,
            'urlSuffix' => $node->urlSuffix,
            'newWindow' => $node->newWindow,
            'icon' => $node->icon,
            'badge' => $node->badge,
            'parentId' => $node->getParentId(),
        ]);
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
            $exclude = $this->_getNode($excludeNodeId);
        }

        $options = FreeNav::getInstance()->getNodes()->getParentOptions($menu, $exclude, $this->_requestedSiteId());

        return $this->asJson(['options' => $options]);
    }

    public function actionToggleVisibility(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $nodeId = Craft::$app->getRequest()->getRequiredBodyParam('nodeId');
        $enabled = (bool)Craft::$app->getRequest()->getRequiredBodyParam('enabled');

        $node = $this->_getNode($nodeId);

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

        $node = $this->_getNode($nodeId);

        if (!$node) {
            throw new NotFoundHttpException('Node not found');
        }

        $menu = $node->getMenu();
        $nodes = FreeNav::getInstance()->getNodes();

        $parent = $nodes->findNodeInMenu($menu, $parentId, $node->siteId);
        $prevSibling = $nodes->findNodeInMenu($menu, $prevId, $node->siteId);

        if (!$nodes->moveNode($menu, $node, $parent, $prevSibling)) {
            return $this->asFailure(Craft::t('free-nav', 'Couldn\'t move node.'));
        }

        return $this->asSuccess();
    }

    /**
     * A node only has rows for the sites its menu propagates to, so look it up across
     * all sites rather than against whichever site the CP happens to be on.
     */
    private function _getNode(mixed $nodeId): ?Node
    {
        return Node::find()
            ->id($nodeId)
            ->siteId('*')
            ->unique()
            ->preferSites([$this->_requestedSiteId()])
            ->status(null)
            ->one();
    }

    /**
     * The site the builder is working in. Craft doesn't apply ?site= to the current
     * site on CP requests, so this has to go through Cp::requestedSite().
     */
    private function _requestedSiteId(): int
    {
        return Cp::requestedSite()?->id ?? Craft::$app->getSites()->getCurrentSite()->id;
    }
}
