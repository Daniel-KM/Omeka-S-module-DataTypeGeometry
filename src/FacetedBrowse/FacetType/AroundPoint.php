<?php declare(strict_types=1);

namespace DataTypeGeometry\FacetedBrowse\FacetType;

use FacetedBrowse\Api\Representation\FacetedBrowseFacetRepresentation;
use FacetedBrowse\FacetType\FacetTypeInterface;
use Laminas\Form\Element as LaminasElement;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;

/**
 * Facet on a geographic distance: items located within a radius of a point.
 *
 * Each predefined zone is a "latitude,longitude,radius" triple. The selected
 * zone is applied with the geometry search query "geo[around]".
 */
class AroundPoint implements FacetTypeInterface
{
    protected $formElements;

    public function __construct(ServiceLocatorInterface $formElements)
    {
        $this->formElements = $formElements;
    }

    public function getLabel(): string
    {
        return 'Around a point (geography)'; // @translate
    }

    public function getResourceTypes(): array
    {
        return ['items', 'item_sets', 'media'];
    }

    public function getMaxFacets(): ?int
    {
        // The "geo[around]" query accepts a single point at a time.
        return 1;
    }

    public function prepareDataForm(PhpRenderer $view): void
    {
        $view->headScript()->appendFile($view->assetUrl('js/faceted-browse/facet-data-form/around-point.js', 'DataTypeGeometry'));
    }

    public function renderDataForm(PhpRenderer $view, array $data): string
    {
        $unit = $this->formElements->get(LaminasElement\Select::class);
        $unit->setName('unit');
        $unit->setOptions([
            'label' => 'Radius unit', // @translate
            'value_options' => [
                'km' => 'Kilometers', // @translate
                'm' => 'Meters', // @translate
                'mi' => 'Miles', // @translate
            ],
        ]);
        $unit->setAttributes([
            'id' => 'around-point-unit',
            'value' => $data['unit'] ?? 'km',
        ]);

        $values = $this->formElements->get(LaminasElement\Textarea::class);
        $values->setName('values');
        $values->setOptions([
            'label' => 'Values', // @translate
            'info' => 'Enter the zones, separated by a new line. For each line, enter "latitude,longitude,radius", optionally followed by a space and a human-readable label.', // @translate
        ]);
        $values->setAttributes([
            'id' => 'around-point-values',
            'style' => 'height: 300px;',
            'value' => $data['values'] ?? null,
            'placeholder' => "48.8566,2.3522,10 Paris\n51.5074,-0.1278,10 London",
        ]);

        return $view->partial('common/faceted-browse/facet-data-form/around-point', [
            'elementUnit' => $unit,
            'elementValues' => $values,
        ]);
    }

    public function prepareFacet(PhpRenderer $view): void
    {
        $view->headScript()->appendFile($view->assetUrl('js/faceted-browse/facet-render/around-point.js', 'DataTypeGeometry'));
    }

    public function renderFacet(PhpRenderer $view, FacetedBrowseFacetRepresentation $facet): string
    {
        $values = $facet->data('values');
        $values = explode("\n", (string) $values);
        $values = array_map('trim', $values);
        $values = array_filter(array_unique($values), 'strlen');

        $pointKeyValues = [];
        foreach ($values as $value) {
            if (preg_match('/^([^\s]+)\s+(.+)/', $value, $matches)) {
                $point = $matches[1];
                $label = $matches[2];
            } else {
                $point = $value;
                $label = $value;
            }
            // Validate "latitude,longitude,radius".
            $parts = explode(',', $point);
            if (count($parts) !== 3 || !is_numeric($parts[0]) || !is_numeric($parts[1]) || !is_numeric($parts[2])) {
                continue;
            }
            $pointKeyValues[$point] = $label;
        }

        $elementValues = $this->formElements->get(LaminasElement\Select::class);
        $elementValues->setName('around_point');
        $elementValues->setAttribute('class', 'around-point-value');
        $elementValues->setAttribute('style', 'width: 90%;');
        $elementValues->setAttribute('data-unit', $facet->data('unit') ?: 'km');
        $elementValues->setEmptyOption('Select a zone…'); // @translate
        $elementValues->setValueOptions($pointKeyValues);

        return $view->partial('common/faceted-browse/facet-render/around-point', [
            'facet' => $facet,
            'elementValues' => $elementValues,
        ]);
    }
}
