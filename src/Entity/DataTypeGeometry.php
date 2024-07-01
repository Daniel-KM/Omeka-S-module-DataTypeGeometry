<?php declare(strict_types=1);

namespace DataTypeGeometry\Entity;

use LongitudeOne\Spatial\PHP\Types\Geometry\GeometryInterface;
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
class DataTypeGeometry extends AbstractEntity
{
    /**
     * @var int
     *
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var \Omeka\Entity\Resource
     *
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
     *
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
     * @var \LongitudeOne\Spatial\PHP\Types\Geometry\GeometryInterface
     *
     * @Column(
     *     type="geometry",
     *     nullable=false
     * )
     *
     * InnoDb requires a geometry to be a non-null value. Anyway, it's a value.
     */
    protected $value;

    public function getId()
    {
        return $this->id;
    }

    public function setResource(Resource $resource): self
    {
        $this->resource = $resource;
        return $this;
    }

    public function getResource(): Resource
    {
        return $this->resource;
    }

    public function setProperty(Property $property): self
    {
        $this->property = $property;
        return $this;
    }

    public function getProperty(): Property
    {
        return $this->property;
    }

    /**
     * @param \LongitudeOne\Spatial\PHP\Types\Geometry\GeometryInterface $value
     */
    public function setValue($value): self
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return \LongitudeOne\Spatial\PHP\Types\Geometry\GeometryInterface
     */
    public function getValue()
    {
        return $this->value;
    }
}
