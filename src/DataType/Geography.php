<?php
namespace DataTypeGeometry\DataType;

use DataTypeGeometry\Doctrine\PHP\Types\Geography\Geography as GenericGeography;
use Zend\Form\Element;
use Zend\View\Renderer\PhpRenderer;

class Geography extends AbstractDataType
{
    /**
     * The default srid.
     * @link http://www.opengis.net/def/crs/OGC/1.3/CRS84
     * @link https://epsg.io/4326
     *
     * @var integer
     */
    const DEFAULT_SRID = 4326;

    public function getName()
    {
        return 'geometry:geography';
    }

    public function getLabel()
    {
        return 'Geography'; // @translate
    }

    public function form(PhpRenderer $view)
    {
        $element = new Element\Textarea('geography');
        $element->setAttributes([
            'class' => 'value to-require geography',
            'data-value-key' => '@value',
            'placeholder' => 'POINT (2.294497 48.858252)',
        ]);
        return $view->formTextarea($element);
    }

    /**
     * @toto Check if the numbers inside the wkt are true latitude and longitude.
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

    public function getEntityClass()
    {
        return \DataTypeGeometry\Entity\DataTypeGeography::class;
    }

    /**
     * Convert a string into a geometry representation.
     *
     * @param string $value Accept AbstractGeometry and geometry array too.
     * @throws \InvalidArgumentException
     * @return \CrEOF\Spatial\PHP\Types\Geography\GeographyInterface
     */
    public function getGeometryFromValue($value)
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Empty geography.'); // @translate
        }
        if (is_string($value)) {
            $value = strtoupper($value);
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
