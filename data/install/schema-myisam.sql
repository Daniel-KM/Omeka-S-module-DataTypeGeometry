CREATE TABLE data_type_geography (
    id INT AUTO_INCREMENT NOT NULL,
    resource_id INT NOT NULL,
    property_id INT NOT NULL,
    value GEOMETRY NOT NULL COMMENT '(DC2Type:geometry:geography)',
    INDEX IDX_9107FC8E89329D25 (resource_id),
    INDEX IDX_9107FC8E549213EC (property_id),
    SPATIAL INDEX idx_value (value),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = MyISAM;
CREATE TABLE data_type_geometry (
    id INT AUTO_INCREMENT NOT NULL,
    resource_id INT NOT NULL,
    property_id INT NOT NULL,
    value GEOMETRY NOT NULL COMMENT '(DC2Type:geometry:geometry)',
    INDEX IDX_A9EF3D7D89329D25 (resource_id),
    INDEX IDX_A9EF3D7D549213EC (property_id),
    SPATIAL INDEX idx_value (value),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = MyISAM;
ALTER TABLE data_type_geography ADD CONSTRAINT FK_9107FC8E89329D25 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;
ALTER TABLE data_type_geography ADD CONSTRAINT FK_9107FC8E549213EC FOREIGN KEY (property_id) REFERENCES property (id) ON DELETE CASCADE;
ALTER TABLE data_type_geometry ADD CONSTRAINT FK_A9EF3D7D89329D25 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;
ALTER TABLE data_type_geometry ADD CONSTRAINT FK_A9EF3D7D549213EC FOREIGN KEY (property_id) REFERENCES property (id) ON DELETE CASCADE;
