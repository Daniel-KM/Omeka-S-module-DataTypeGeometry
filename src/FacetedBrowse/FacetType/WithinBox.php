<?php declare(strict_types=1);

namespace DataTypeGeometry\FacetedBrowse\FacetType;

use FacetedBrowse\Api\Representation\FacetedBrowseFacetRepresentation;
use FacetedBrowse\FacetType\FacetTypeInterface;
use Laminas\Form\Element as LaminasElement;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;

/**
 * Facet on a geographic bounding box: items located within a box.
 *
 * Each predefined box is a "topLat,leftLong,bottomLat,rightLong" quadruple. The
 * selected box is applied with the geometry search query "geo[mapbox]".
 */
class WithinBox implements FacetTypeInterface
{
    protected $formElements;

    public function __construct(ServiceLocatorInterface $formElements)
    {
        $this->formElements = $formElements;
    }

    public function getLabel(): string
    {
        return 'Within a box (geography)'; // @translate
    }

    public function getResourceTypes(): array
    {
        return ['items', 'item_sets', 'media'];
    }

    public function getMaxFacets(): ?int
    {
        // The "geo[mapbox]" query accepts a single box at a time.
        return 1;
    }

    public function prepareDataForm(PhpRenderer $view): void
    {
        $view->headScript()->appendFile($view->assetUrl('js/faceted-browse/facet-data-form/within-box.js', 'DataTypeGeometry'));
    }

    public function renderDataForm(PhpRenderer $view, array $data): string
    {
        $values = $this->formElements->get(LaminasElement\Textarea::class);
        $values->setName('values');
        $values->setOptions([
            'label' => 'Values', // @translate
            'info' => 'Enter the boxes, separated by a new line. For each line, enter "topLatitude,leftLongitude,bottomLatitude,rightLongitude", optionally followed by a space and a human-readable label.', // @translate
        ]);
        $values->setAttributes([
            'id' => 'within-box-values',
            'style' => 'height: 300px;',
            'value' => $data['values'] ?? null,
            'placeholder' => "49,2,48.5,2.6 Paris area\n51.7,-0.5,51.2,0.3 London area",
        ]);

        return $view->partial('common/faceted-browse/facet-data-form/within-box', [
            'elementValues' => $values,
        ]);
    }

    public function prepareFacet(PhpRenderer $view): void
    {
        $view->headScript()->appendFile($view->assetUrl('js/faceted-browse/facet-render/within-box.js', 'DataTypeGeometry'));
    }

    public function renderFacet(PhpRenderer $view, FacetedBrowseFacetRepresentation $facet): string
    {
        $values = $facet->data('values');
        $values = explode("\n", (string) $values);
        $values = array_map('trim', $values);
        $values = array_filter(array_unique($values), 'strlen');

        $boxKeyValues = [];
        foreach ($values as $value) {
            if (preg_match('/^([^\s]+)\s+(.+)/', $value, $matches)) {
                $box = $matches[1];
                $label = $matches[2];
            } else {
                $box = $value;
                $label = $value;
            }
            // Validate "topLat,leftLong,bottomLat,rightLong".
            $parts = explode(',', $box);
            if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) {
                continue;
            }
            $boxKeyValues[$box] = $label;
        }

        $elementValues = $this->formElements->get(LaminasElement\Select::class);
        $elementValues->setName('within_box');
        $elementValues->setAttribute('class', 'within-box-value');
        $elementValues->setAttribute('style', 'width: 90%;');
        $elementValues->setEmptyOption('Select a box…'); // @translate
        $elementValues->setValueOptions($boxKeyValues);

        return $view->partial('common/faceted-browse/facet-render/within-box', [
            'facet' => $facet,
            'elementValues' => $elementValues,
        ]);
    }
}
