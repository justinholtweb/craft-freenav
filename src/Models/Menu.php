<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Models;

use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\FieldLayoutTab;
use CraftCms\Cms\FieldLayout\LayoutElements\TitleField;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Structures;
use DateTimeInterface;
use Illuminate\Validation\Rule;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\Enums\Propagation;
use JustinHoltWeb\FreeNav\FieldLayoutElements\BadgeField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\CraftIconField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\CssClassesField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\CustomAttributesField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\CustomUrlField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\IconField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\NewWindowField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\UrlSuffixField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\VisibilityRulesField;
use JustinHoltWeb\FreeNav\Services\Menus;

use function CraftCms\Cms\t;

/**
 * A menu definition. Stored in project config under `freeNav.menus.{uid}`; `freenav_menus` is the
 * runtime copy the project-config handler writes.
 */
class Menu extends Model
{
    public ?int $id = null;

    public ?int $structureId = null;

    public ?int $fieldLayoutId = null;

    public ?string $name = '';

    public ?string $handle = '';

    public ?string $instructions = null;

    public ?string $propagationMethod = 'all';

    public ?int $maxNodes = null;

    public ?int $maxLevels = null;

    public ?string $defaultPlacement = 'end';

    public ?string $permissions = null;

    public int $sortOrder = 0;

    public DateTimeInterface|string|null $dateDeleted = null;

    public DateTimeInterface|string|null $dateCreated = null;

    public DateTimeInterface|string|null $dateUpdated = null;

    public ?string $uid = null;

    /** @var array<int, MenuSiteSettings>|null */
    private ?array $siteSettings = null;

    private ?FieldLayout $fieldLayout = null;

    public function getRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            'propagationMethod' => ['required', Rule::in(array_column(Propagation::cases(), 'value'))],
            'maxNodes' => ['nullable', 'integer', 'min:1'],
            'maxLevels' => ['nullable', 'integer', 'min:1'],
            'sortOrder' => ['integer'],
            'defaultPlacement' => ['required', Rule::in(['beginning', 'end'])],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => t('Name', category: 'free-nav'),
            'handle' => t('Handle', category: 'free-nav'),
            'maxNodes' => t('Max Nodes', category: 'free-nav'),
            'maxLevels' => t('Max Levels', category: 'free-nav'),
        ];
    }

    protected function checks(): void
    {
        if ($this->handle) {
            $existing = app(Menus::class)->getMenuByHandle($this->handle);

            if ($existing && $existing->uid !== $this->uid) {
                $this->addError('handle', t('Handle “{handle}” is already in use.', ['handle' => $this->handle], category: 'free-nav'));
            }
        }
    }

    public function getPropagationMethod(): Propagation
    {
        return Propagation::tryFrom((string) $this->propagationMethod) ?? Propagation::All;
    }

    /**
     * The IDs of the sites this menu is enabled for, in site order.
     *
     * @return int[]
     */
    public function getEnabledSiteIds(): array
    {
        $enabled = [];

        foreach ($this->getSiteSettings() as $siteSettings) {
            if ($siteSettings->enabled && $siteSettings->siteId && Sites::getSiteById($siteSettings->siteId)) {
                $enabled[] = $siteSettings->siteId;
            }
        }

        // A menu with no site settings at all is treated as a primary-site menu
        if ($enabled === []) {
            $enabled[] = (int) Sites::getPrimarySite()->id;
        }

        return $enabled;
    }

    public function isEnabledForSite(int $siteId): bool
    {
        return in_array($siteId, $this->getEnabledSiteIds(), true);
    }

    public function getFieldLayout(): FieldLayout
    {
        if ($this->fieldLayout !== null) {
            return $this->fieldLayout;
        }

        if ($this->fieldLayoutId) {
            $this->fieldLayout = Fields::getLayoutById($this->fieldLayoutId);
        }

        return $this->fieldLayout ??= self::defaultFieldLayout();
    }

    public function setFieldLayout(?FieldLayout $fieldLayout): void
    {
        $this->fieldLayout = $fieldLayout;
    }

    /**
     * The layout a new menu starts with: every node setting FreeNav 5's builder panel offered, in
     * the same order. FreeNav 5 had no layout designer, so every upgraded menu gets this too.
     */
    public static function defaultFieldLayout(): FieldLayout
    {
        $layout = new FieldLayout(['type' => Node::class]);
        $tab = new FieldLayoutTab(['layout' => $layout, 'name' => t('Node', category: 'free-nav')]);
        $tab->setElements([
            new TitleField,
            new CustomUrlField,
            new NewWindowField,
            new CssClassesField,
            new UrlSuffixField,
            new CraftIconField,
            new IconField,
            new BadgeField,
            new CustomAttributesField,
            new VisibilityRulesField,
        ]);
        $layout->setTabs([$tab]);

        return $layout;
    }

    /**
     * @return array<int, MenuSiteSettings>
     */
    public function getSiteSettings(): array
    {
        if ($this->siteSettings !== null) {
            return $this->siteSettings;
        }

        return $this->siteSettings = $this->id ? app(Menus::class)->getMenuSiteSettings($this->id) : [];
    }

    /**
     * @param  array<int, MenuSiteSettings>  $siteSettings
     */
    public function setSiteSettings(array $siteSettings): void
    {
        $this->siteSettings = $siteSettings;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
            'handle' => $this->handle,
            'instructions' => $this->instructions,
            'propagationMethod' => $this->propagationMethod,
            'maxNodes' => $this->maxNodes,
            'maxLevels' => $this->maxLevels,
            'defaultPlacement' => $this->defaultPlacement,
            'structure' => [
                'uid' => $this->structureId ? Structures::getStructureById($this->structureId)?->uid : null,
                'maxLevels' => $this->maxLevels,
            ],
        ];

        // Every menu stores its layout, the default one included (the upgrade migration writes it for
        // menus from FreeNav 5, which had no layout designer). Layout elements need saved UIDs before
        // Craft's editor will show them.
        $fieldLayout = $this->getFieldLayout();
        $fieldLayoutConfig = $fieldLayout->getConfig();

        if ($fieldLayoutConfig) {
            $config['fieldLayout'] = ['uid' => $fieldLayout->uid] + $fieldLayoutConfig;
        }

        $config['sites'] = [];

        foreach ($this->getSiteSettings() as $siteSettings) {
            $siteUid = $siteSettings->siteId ? Sites::getSiteById($siteSettings->siteId)?->uid : null;

            if ($siteUid) {
                $config['sites'][$siteUid] = [
                    'enabled' => $siteSettings->enabled,
                ];
            }
        }

        return $config;
    }
}
