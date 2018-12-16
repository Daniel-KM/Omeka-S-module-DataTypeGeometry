<?php
namespace DataTypeGeometry\Entity;

use CrEOF\Spatial\PHP\Types\Geography\GeographyInterface;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Property;
use Omeka\Entity\Resource;

/**
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(name="idx_value", columns={"value"}, flags={"spatial"})
 *     }
 * )
 */
class DataTypeGeography extends AbstractEntity
{
    /**
     * @var int
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var \Omeka\Entity\Resource
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Resource"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $resource;

    /**
     * @var \Omeka\Entity\Property
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Property"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $property;

    /**
     * @var GeographyInterface
     * @Column(
     *     type="geometry:geography",
     *     nullable=false
     * )
     * InnoDb requires a geometry to be a non-null value. Anyway, it's a value.
     */
    protected $value;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @param Resource $resource
     * @return self
     */
    public function setResource(Resource $resource)
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * @return \Omeka\Entity\Resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param Property $property
     * @return self
     */
    public function setProperty(Property $property)
    {
        $this->property = $property;
        return $this;
    }

    /**
     * @return \Omeka\Entity\Property
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @param GeographyInterface $value
     * @return self
     */
    public function setValue(GeographyInterface $value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return \CrEOF\Spatial\PHP\Types\Geography\GeographyInterface
     */
    public function getValue()
    {
        return $this->value;
    }
}
