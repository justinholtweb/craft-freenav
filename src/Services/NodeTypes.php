<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Services;

use Illuminate\Support\Facades\Event;
use JustinHoltWeb\FreeNav\Enums\NodeType;
use JustinHoltWeb\FreeNav\Events\RegisterLinkableElements;
use JustinHoltWeb\FreeNav\Events\RegisterNodeTypes;

class NodeTypes
{
    /** @var NodeType[]|null */
    private ?array $types = null;

    /** @var array<string, array{nodeType: NodeType, elementType: class-string, label: string}>|null */
    private ?array $linkableElements = null;

    /**
     * The node types new nodes can be. Categories (gone from Craft 6) and products (until Commerce 6
     * is installed) aren't offered.
     *
     * @return NodeType[]
     */
    public function getRegisteredTypes(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $types = array_values(array_filter(NodeType::cases(), fn (NodeType $type) => $type->isAvailable()));

        if (Event::hasListeners(RegisterNodeTypes::class)) {
            event($event = new RegisterNodeTypes($types));
            $types = $event->types;
        }

        return $this->types = $types;
    }

    /**
     * @return array<string, array{nodeType: NodeType, elementType: class-string, label: string}>
     */
    public function getLinkableElementTypes(): array
    {
        if ($this->linkableElements !== null) {
            return $this->linkableElements;
        }

        $linkable = [];

        foreach ($this->getRegisteredTypes() as $type) {
            $elementClass = $type->elementType();

            if ($type->isElement() && $elementClass && class_exists($elementClass)) {
                $linkable[$type->value] = [
                    'nodeType' => $type,
                    'elementType' => $elementClass,
                    'label' => $type->label(),
                ];
            }
        }

        if (Event::hasListeners(RegisterLinkableElements::class)) {
            event($event = new RegisterLinkableElements($linkable));
            $linkable = $event->elementTypes;
        }

        return $this->linkableElements = $linkable;
    }

    public function getTypeLabel(NodeType $type): string
    {
        return $type->label();
    }

    /**
     * @return list<array{label: string, value: string, isElement: bool, hasUrl: bool, color: string}>
     */
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
