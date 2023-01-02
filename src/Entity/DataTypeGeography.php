<?php declare(strict_types=1);

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
     * @var GeographyInterface
     *
     * @Column(
     *     type="geography",
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

    public function getResource(): \Omeka\Entity\Resource
    {
        return $this->resource;
    }

    public function setProperty(Property $property): self
    {
        $this->property = $property;
        return $this;
    }

    public function getProperty(): \Omeka\Entity\Property
    {
        return $this->property;
    }

    public function setValue(GeographyInterface $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getValue(): \CrEOF\Spatial\PHP\Types\Geography\GeographyInterface
    {
        return $this->value;
    }
}
