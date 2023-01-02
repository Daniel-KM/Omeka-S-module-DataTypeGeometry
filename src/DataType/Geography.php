<?php declare(strict_types=1);

namespace DataTypeGeometry\DataType;

use DataTypeGeometry\Doctrine\PHP\Types\Geography\Geography as GenericGeography;
use Laminas\Form\Element;
use Laminas\View\Renderer\PhpRenderer;

class Geography extends AbstractDataType
{
    /**
     * The default srid.
     *
     * This is used in GPS and Google Earth and expert tools.
     * 3857 is used in web maps like OpenStreetMap and derivative like Google Maps, OpenStreetMap, Bing, ArcGIS, ESRI.
     *
     * @link http://www.opengis.net/def/crs/OGC/1.3/CRS84
     * @link https://epsg.io/3857
     * @link https://epsg.io/4326
     *
     * @var int
     */
    const DEFAULT_SRID = 4326;

    public function getName()
    {
        return 'geography';
    }

    public function getLabel()
    {
        return 'Geography'; // @translate
    }

    public function form(PhpRenderer $view)
    {
        $translate = $view->plugin('translate');
        $escapeAttr = $view->plugin('escapeHtmlAttr');
        $validity = 'Please enter a valid wkt for the geography.'; // @translate

        $element = new Element\Textarea('geography');
        $element->setAttributes([
            'class' => 'value to-require geography',
            'data-value-key' => '@value',
            'data-invalid-message' => $validity,
            // 'placeholder' => 'POINT (2.294497 48.858252)',
        ]);

        return '<div class="error invalid-value" data-custom-validity="' . $escapeAttr($translate($validity)) . '"></div>'
            . $view->formTextarea($element);
    }

    /**
     * @todo Check if the numbers inside the wkt are true latitude and longitude.
     *
     * {@inheritDoc}
     * @see \DataTypeGeometry\DataType\AbstractDataType::isValid()
     */
    // public function isValid(array $valueObject)
    // {
    //     return parent::isValid($valueObject)
    //         ? test
    //         : false;
    // }

    public function getEntityClass(): string
    {
        return \DataTypeGeometry\Entity\DataTypeGeography::class;
    }

    /**
     * Convert a string into a geography representation.
     *
     * @param string $value Accept AbstractGeometry and geometry array too.
     * @throws \InvalidArgumentException
     */
    public function getGeometryFromValue($value): \CrEOF\Spatial\PHP\Types\Geography\GeographyInterface
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Empty geography.'); // @translate
        }
        if (is_string($value)) {
            $value = strtoupper((string) $value);
        }
        try {
            return (new GenericGeography($value))->getGeometry();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid geography: %s', // @translate
                $value
            ));
        }
    }
}
