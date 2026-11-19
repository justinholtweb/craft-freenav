<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Http\Request;
use JustinHoltWeb\FreeNav\Services\Menus;
use JustinHoltWeb\FreeNav\Services\MenuTransfer;
use JustinHoltWeb\FreeNav\Services\VerbbImporter;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * JSON export and import, and the Verbb Navigation migration.
 *
 * Exporting needs the menu's manage permission. Importing and migrating create menus, which are
 * project config, so they need "manage menus" and `allowAdminChanges`.
 */
class ImportExportController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly Menus $menus,
        private readonly MenuTransfer $transfer,
        private readonly VerbbImporter $verbb,
    ) {}

    public function export(int $menuId): Response
    {
        $menu = $this->menus->getMenuById($menuId) ?? abort(404, 'Menu not found');

        abort_unless($this->menus->canManageMenu($this->user(), $menu), 403, 'User not authorized to export this menu.');

        $json = $this->transfer->encode($this->transfer->exportMenu($menu));

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$menu->handle.'-export.json"',
        ]);
    }

    public function index(): CpScreenResponse
    {
        $this->requireManageMenus();

        return new CpScreenResponse()
            ->title(t('Import', category: 'free-nav'))
            ->contentTemplate('free-nav/tools/_import', [
                'importUrl' => cp_url('free-nav/import'),
                'migrateUrl' => cp_url('free-nav/import/navigation'),
                'verbbAvailable' => $this->verbb->tablesExist(),
            ]);
    }

    public function import(Request $request): Response
    {
        $this->requireManageMenus();

        $file = $request->file('importFile');

        if (! $file || ! $file->isValid()) {
            return $this->asFailure(t('No file uploaded.', category: 'free-nav'));
        }

        $data = $this->transfer->decode((string) file_get_contents($file->getRealPath()));

        if (! $data) {
            return $this->asFailure(t('Invalid import file format.', category: 'free-nav'));
        }

        // A menu with the same handle is left alone; the import becomes a copy beside it.
        $result = $this->transfer->importMenu($data, renameOnConflict: true, user: currentUser());

        if (! $result->getSuccess() || ! $result->menu) {
            return $this->asFailure(implode(' ', $result->errors));
        }

        $notice = t('Menu imported successfully.', category: 'free-nav');

        if ($result->warnings) {
            $notice .= ' '.implode(' ', $result->warnings);
        }

        return $this->asSuccess($notice, redirect: cp_url("free-nav/menus/{$result->menu->id}/build"));
    }

    public function migrateFromNavigation(): Response
    {
        $this->requireManageMenus();

        if (! $this->verbb->tablesExist()) {
            return $this->asFailure(t('Verbb Navigation tables not found.', category: 'free-nav'));
        }

        $navs = $this->verbb->navs();

        if ($navs === []) {
            return $this->asSuccess(t('No navigations found to migrate.', category: 'free-nav'), redirect: cp_url('free-nav/import'));
        }

        $migrated = 0;
        $skipped = 0;

        foreach ($navs as $nav) {
            $this->verbb->migrateNav($nav, currentUser()) ? $migrated++ : $skipped++;
        }

        $message = t('{count} navigation(s) migrated.', ['count' => $migrated], category: 'free-nav');

        if ($skipped > 0) {
            $message .= ' '.t('{count} skipped (handle already exists).', ['count' => $skipped], category: 'free-nav');
        }

        return $this->asSuccess($message, redirect: cp_url('free-nav/menus'));
    }

    private function requireManageMenus(): void
    {
        abort_unless($this->menus->canManageMenus($this->user()), 403, 'User not authorized to manage menus.');
        abort_unless($this->menus->allowAdminChanges(), 403, 'Menus can only be changed where admin changes are allowed.');
    }

    private function user(): User
    {
        return currentUserElement() ?? abort(403);
    }
}
