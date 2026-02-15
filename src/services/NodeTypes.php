<?php

namespace justinholt\freenav\services;

use Craft;
use craft\base\Element;
use justinholt\freenav\enums\NodeType;
use justinholt\freenav\events\RegisterLinkableElementEvent;
use justinholt\freenav\events\RegisterNodeTypeEvent;
use yii\base\Component;

class NodeTypes extends Component
{
    public const EVENT_REGISTER_NODE_TYPES = 'registerNodeTypes';
    public const EVENT_REGISTER_LINKABLE_ELEMENTS = 'registerLinkableElements';

    private ?array $_types = null;
    private ?array $_linkableElements = null;

    public function getRegisteredTypes(): array
    {
        if ($this->_types !== null) {
            return $this->_types;
        }

        $this->_types = NodeType::cases();

        // Filter out product type if Commerce not installed
        $this->_types = array_filter($this->_types, function (NodeType $type) {
            if ($type === NodeType::Product) {
                return class_exists('craft\\commerce\\elements\\Product');
            }
            return true;
        });

        // Allow plugins to register additional types
        if ($this->hasEventHandlers(self::EVENT_REGISTER_NODE_TYPES)) {
            $event = new RegisterNodeTypeEvent(['types' => $this->_types]);
            $this->trigger(self::EVENT_REGISTER_NODE_TYPES, $event);
            $this->_types = $event->types;
        }

        return $this->_types;
    }

    public function getLinkableElementTypes(): array
    {
        if ($this->_linkableElements !== null) {
            return $this->_linkableElements;
        }

        $this->_linkableElements = [];

        foreach ($this->getRegisteredTypes() as $type) {
            if ($type->isElement()) {
                $elementClass = $type->elementType();
                if ($elementClass && class_exists($elementClass)) {
                    $this->_linkableElements[$type->value] = [
                        'nodeType' => $type,
                        'elementType' => $elementClass,
                        'label' => $type->label(),
                    ];
                }
            }
        }

        // Allow plugins to register additional linkable elements
        if ($this->hasEventHandlers(self::EVENT_REGISTER_LINKABLE_ELEMENTS)) {
            $event = new RegisterLinkableElementEvent(['elementTypes' => $this->_linkableElements]);
            $this->trigger(self::EVENT_REGISTER_LINKABLE_ELEMENTS, $event);
            $this->_linkableElements = $event->elementTypes;
        }

        return $this->_linkableElements;
    }

    public function getTypeLabel(NodeType $type): string
    {
        return $type->label();
    }

    public function getTypeOptions(): array
    {
        $options = [];

        foreach ($this->getRegisteredTypes() as $type) {
            $options[] = [
                'label' => $type->label(),
                'value' => $type->value,
                'isElement' => $type->isElement(),
                'hasUrl' => $type->hasUrl(),
                'color' => $type->color(),
            ];
        }

        return $options;
    }
}
