<?php

namespace justinholt\freenav\models;

use Craft;
use craft\base\Model;
use craft\models\FieldLayout;
use justinholt\freenav\elements\Node;
use justinholt\freenav\enums\Propagation;
use justinholt\freenav\FreeNav;

class Menu extends Model
{
    public ?int $id = null;
    public ?int $structureId = null;
    public ?int $fieldLayoutId = null;
    public string $name = '';
    public string $handle = '';
    public ?string $instructions = null;
    public string $propagationMethod = 'all';
    public ?int $maxNodes = null;
    public ?int $maxLevels = null;
    public string $defaultPlacement = 'end';
    public ?string $permissions = null;
    public int $sortOrder = 0;
    public ?string $dateDeleted = null;
    public ?string $dateCreated = null;
    public ?string $dateUpdated = null;
    public ?string $uid = null;

    /** @var MenuSiteSettings[] */
    private array $_siteSettings = [];

    private ?FieldLayout $_fieldLayout = null;

    public function defineRules(): array
    {
        return [
            [['name', 'handle'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['handle'], 'string', 'max' => 255],
            [['handle'], 'match', 'pattern' => '/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            [['propagationMethod'], 'in', 'range' => array_column(Propagation::cases(), 'value')],
            [['maxNodes', 'maxLevels', 'sortOrder'], 'integer'],
            [['defaultPlacement'], 'in', 'range' => ['beginning', 'end']],
        ];
    }

    public function getPropagationMethod(): Propagation
    {
        return Propagation::from($this->propagationMethod);
    }

    public function getFieldLayout(): ?FieldLayout
    {
        if ($this->_fieldLayout !== null) {
            return $this->_fieldLayout;
        }

        if ($this->fieldLayoutId) {
            $this->_fieldLayout = Craft::$app->getFields()->getLayoutById($this->fieldLayoutId);
        }

        if ($this->_fieldLayout === null) {
            $this->_fieldLayout = new FieldLayout(['type' => Node::class]);
        }

        return $this->_fieldLayout;
    }

    public function setFieldLayout(?FieldLayout $fieldLayout): void
    {
        $this->_fieldLayout = $fieldLayout;
    }

    /**
     * @return MenuSiteSettings[]
     */
    public function getSiteSettings(): array
    {
        if ($this->_siteSettings) {
            return $this->_siteSettings;
        }

        if ($this->id) {
            $this->_siteSettings = FreeNav::getInstance()->getMenus()->getMenuSiteSettings($this->id);
        }

        return $this->_siteSettings;
    }

    /**
     * @param MenuSiteSettings[] $siteSettings
     */
    public function setSiteSettings(array $siteSettings): void
    {
        $this->_siteSettings = $siteSettings;
    }

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
                'uid' => $this->structureId ? Craft::$app->getStructures()->getStructureById($this->structureId)?->uid : null,
                'maxLevels' => $this->maxLevels,
            ],
        ];

        // Field layout
        $fieldLayout = $this->getFieldLayout();
        if ($fieldLayout && $fieldLayout->uid) {
            $fieldLayoutConfig = $fieldLayout->getConfig();
            if ($fieldLayoutConfig) {
                $config['fieldLayout'] = $fieldLayoutConfig;
            }
        }

        // Site settings
        $config['sites'] = [];
        foreach ($this->getSiteSettings() as $siteSettings) {
            $siteUid = Craft::$app->getSites()->getSiteById($siteSettings->siteId)?->uid;
            if ($siteUid) {
                $config['sites'][$siteUid] = [
                    'enabled' => $siteSettings->enabled,
                ];
            }
        }

        return $config;
    }
}
