<?php

namespace justinholt\freenav\controllers;

use Craft;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use justinholt\freenav\assetbundles\FreeNavAsset;
use justinholt\freenav\elements\Node;
use justinholt\freenav\enums\Propagation;
use justinholt\freenav\FreeNav;
use justinholt\freenav\models\Menu;
use justinholt\freenav\models\MenuSiteSettings;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class MenusController extends Controller
{
    public function actionIndex(): Response
    {
        $menus = FreeNav::getInstance()->getMenus()->getEditableMenus();

        return $this->renderTemplate('free-nav/menus/_index', [
            'menus' => $menus,
        ]);
    }

    public function actionEdit(?int $menuId = null, ?Menu $menu = null): Response
    {
        if ($menu === null) {
            if ($menuId !== null) {
                $menu = FreeNav::getInstance()->getMenus()->getMenuById($menuId);

                if (!$menu) {
                    throw new NotFoundHttpException('Menu not found');
                }
            } else {
                $menu = new Menu();
            }
        }

        $isNew = !$menu->id;

        if (!$isNew) {
            $title = $menu->name;
        } else {
            $title = Craft::t('free-nav', 'Create a new menu');
        }

        $sites = Craft::$app->getSites()->getAllSites();
        $propagationOptions = [];
        foreach (Propagation::cases() as $case) {
            $propagationOptions[] = [
                'label' => $case->label(),
                'value' => $case->value,
            ];
        }

        // Get existing site settings or create defaults
        $siteSettings = $menu->getSiteSettings();
        if (empty($siteSettings)) {
            foreach ($sites as $site) {
                $siteSettings[$site->id] = new MenuSiteSettings([
                    'siteId' => $site->id,
                    'enabled' => true,
                ]);
            }
        }

        return $this->renderTemplate('free-nav/menus/_edit', [
            'menu' => $menu,
            'title' => $title,
            'isNew' => $isNew,
            'sites' => $sites,
            'siteSettings' => $siteSettings,
            'propagationOptions' => $propagationOptions,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $menuId = $request->getBodyParam('menuId');

        if ($menuId) {
            $menu = FreeNav::getInstance()->getMenus()->getMenuById($menuId);
            if (!$menu) {
                throw new NotFoundHttpException('Menu not found');
            }
        } else {
            $menu = new Menu();
        }

        $menu->name = $request->getBodyParam('name', $menu->name);
        $menu->handle = $request->getBodyParam('handle', $menu->handle);
        $menu->instructions = $request->getBodyParam('instructions', $menu->instructions);
        $menu->propagationMethod = $request->getBodyParam('propagationMethod', $menu->propagationMethod);
        $menu->maxNodes = $request->getBodyParam('maxNodes') ?: null;
        $menu->maxLevels = $request->getBodyParam('maxLevels') ?: null;
        $menu->defaultPlacement = $request->getBodyParam('defaultPlacement', $menu->defaultPlacement);

        // Site settings
        $sitesData = $request->getBodyParam('sites', []);
        $siteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $settings = new MenuSiteSettings();
            $settings->siteId = $site->id;
            $settings->enabled = !empty($sitesData[$site->id]['enabled']);
            $siteSettings[$site->id] = $settings;
        }

        $menu->setSiteSettings($siteSettings);

        if (!FreeNav::getInstance()->getMenus()->saveMenu($menu)) {
            Craft::$app->getSession()->setError(Craft::t('free-nav', 'Couldn't save menu.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'menu' => $menu,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('free-nav', 'Menu saved.'));

        return $this->redirectToPostedUrl($menu);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $menuId = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $menu = FreeNav::getInstance()->getMenus()->getMenuById($menuId);

        if (!$menu) {
            throw new NotFoundHttpException('Menu not found');
        }

        FreeNav::getInstance()->getMenus()->deleteMenu($menu);

        return $this->asSuccess(Craft::t('free-nav', 'Menu deleted.'));
    }

    public function actionBuild(int $menuId): Response
    {
        $menu = FreeNav::getInstance()->getMenus()->getMenuById($menuId);

        if (!$menu) {
            throw new NotFoundHttpException('Menu not found');
        }

        $this->view->registerAssetBundle(FreeNavAsset::class);

        $nodes = FreeNav::getInstance()->getNodes()->getNodesByMenuId($menuId);
        $nodeTypes = FreeNav::getInstance()->getNodeTypes()->getTypeOptions();
        $parentOptions = FreeNav::getInstance()->getNodes()->getParentOptions($menu);

        return $this->renderTemplate('free-nav/menus/_build', [
            'menu' => $menu,
            'nodes' => $nodes,
            'nodeTypes' => $nodeTypes,
            'parentOptions' => $parentOptions,
            'title' => $menu->name,
        ]);
    }

    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRawBody())['ids'] ?? [];

        FreeNav::getInstance()->getMenus()->reorderMenus($ids);

        return $this->asSuccess();
    }

    public function actionDuplicate(): Response
    {
        $this->requirePostRequest();

        $menuId = Craft::$app->getRequest()->getRequiredBodyParam('menuId');
        $menu = FreeNav::getInstance()->getMenus()->getMenuById($menuId);

        if (!$menu) {
            throw new NotFoundHttpException('Menu not found');
        }

        $newMenu = FreeNav::getInstance()->getMenus()->duplicateMenu($menu);

        Craft::$app->getSession()->setNotice(Craft::t('free-nav', 'Menu duplicated.'));

        return $this->redirect(UrlHelper::cpUrl('free-nav/menus/' . $newMenu->id));
    }

    public function actionSettings(): Response
    {
        $settings = FreeNav::getInstance()->getSettings();

        return $this->renderTemplate('free-nav/settings/_index', [
            'settings' => $settings,
        ]);
    }

    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $settings = FreeNav::getInstance()->getSettings();

        $settings->cacheEnabled = (bool)$request->getBodyParam('cacheEnabled', $settings->cacheEnabled);
        $settings->cacheDuration = (int)$request->getBodyParam('cacheDuration', $settings->cacheDuration);
        $settings->ariaEnabled = (bool)$request->getBodyParam('ariaEnabled', $settings->ariaEnabled);
        $settings->defaultPreset = $request->getBodyParam('defaultPreset', $settings->defaultPreset);
        $settings->activeClass = $request->getBodyParam('activeClass', $settings->activeClass);
        $settings->hasChildrenClass = $request->getBodyParam('hasChildrenClass', $settings->hasChildrenClass);
        $settings->restApiEnabled = (bool)$request->getBodyParam('restApiEnabled', $settings->restApiEnabled);

        if (!Craft::$app->getPlugins()->savePluginSettings(FreeNav::getInstance(), $settings->toArray())) {
            Craft::$app->getSession()->setError(Craft::t('free-nav', 'Couldn't save settings.'));
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('free-nav', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
